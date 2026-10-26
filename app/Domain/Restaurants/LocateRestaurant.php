<?php

namespace App\Domain\Restaurants;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LocateRestaurant
{
    public function execute(array $address): array
    {
        $query = ['q' => implode(', ', array_filter([Str::squish($address['address']), Str::squish($address['city']), $address['postal_code'] ?? ''])), 'countrycode' => strtoupper($address['country']), 'limit' => 5];
        $results = [];
        foreach ($this->features((string) config('geocoding.url'), $query) as $feature) {
            $point = $this->point($feature);
            if (! $point) {
                continue;
            }
            $p = $feature['properties'];
            if (! is_string($p['countrycode'] ?? null) || strtoupper($p['countrycode']) !== $query['countrycode']) {
                continue;
            }
            $values = array_filter(array_map(fn ($k) => $this->text($p, $k), ['name', 'street', 'housenumber', 'postcode', 'city', 'district', 'country']));
            if ($values) {
                $results[] = [...$point, 'label' => mb_substr(implode(', ', array_unique($values)), 0, 500), 'approximate' => empty($p['housenumber'])];
            }
        }

        return $results;
    }

    public function reverse(array $point): ?array
    {
        $query = ['lat' => $point['latitude'], 'lon' => $point['longitude'], 'radius' => 0.2, 'limit' => 5];
        $nearest = null;
        $distance = 201.0;
        foreach ($this->features((string) config('geocoding.reverse_url'), $query) as $feature) {
            $found = $this->point($feature);
            if (! $found) {
                continue;
            }
            $dLat = deg2rad($found['latitude'] - $point['latitude']);
            $dLng = deg2rad($found['longitude'] - $point['longitude']);
            $a = sin($dLat / 2) ** 2 + cos(deg2rad($point['latitude'])) * cos(deg2rad($found['latitude'])) * sin($dLng / 2) ** 2;
            $meters = 6371000 * 2 * asin(sqrt(min(1, max(0, $a))));
            if ($meters > 200 || $meters >= $distance) {
                continue;
            }
            $p = $feature['properties'];
            $street = $this->text($p, 'street');
            if (! $street && ($p['osm_key'] ?? null) === 'highway') {
                $street = $this->text($p, 'name');
            }
            $city = $this->text($p, 'city') ?: $this->text($p, 'town') ?: $this->text($p, 'village') ?: $this->text($p, 'locality');
            if (! $street && ! $city) {
                continue;
            }
            $number = $this->text($p, 'housenumber');
            $country = strtoupper($this->text($p, 'countrycode'));
            $nearest = [
                'address' => mb_substr(trim($street.' '.($street ? $number : '')), 0, 255),
                'city' => mb_substr($city, 0, 100),
                'country' => preg_match('/^[A-Z]{2}$/', $country) ? $country : '',
                'postal_code' => mb_substr($this->text($p, 'postcode'), 0, 20),
                'missing' => array_values(array_filter([
                    $street ? null : 'address', $number ? null : 'house_number',
                    $city ? null : 'city', preg_match('/^[A-Z]{2}$/', $country) ? null : 'country',
                    $this->text($p, 'postcode') ? null : 'postal_code',
                ])),
            ];
            $distance = $meters;
        }

        return $nearest;
    }

    private function text(array $properties, string $key): string
    {
        return is_string($properties[$key] ?? null) ? Str::squish($properties[$key]) : '';
    }

    private function point(mixed $feature): ?array
    {
        if (! is_array($feature) || ! is_array($feature['properties'] ?? null)) {
            return null;
        }
        $coords = $feature['geometry']['coordinates'] ?? null;
        if (($feature['geometry']['type'] ?? '') !== 'Point' || ! is_array($coords) || count($coords) !== 2 || ! is_numeric($coords[0] ?? null) || ! is_numeric($coords[1] ?? null)) {
            return null;
        }
        [$lng, $lat] = array_map('floatval', $coords);

        return is_finite($lng) && is_finite($lat) && abs($lng) <= 180 && abs($lat) <= 85 ? ['latitude' => $lat, 'longitude' => $lng] : null;
    }

    private function features(string $url, array $query): array
    {
        $parts = parse_url($url);
        if (! $parts || ($parts['scheme'] ?? '') !== 'https' || empty($parts['host']) || isset($parts['user']) || isset($parts['pass']) || isset($parts['query']) || isset($parts['fragment'])) {
            $this->unavailable();
        }
        if (app()->getLocale() === 'en') {
            $query['lang'] = 'en';
        }
        $key = 'geocoding:features:v2:'.hash('sha256', $url.json_encode($query));
        $cached = Cache::get($key);
        if (is_array($cached)) {
            return $cached;
        }
        $lock = Cache::lock('geocoding:provider-lock', 30);
        if (! $lock->get()) {
            $this->busy();
        }
        try {
            $cached = Cache::get($key);
            if (is_array($cached)) {
                return $cached;
            }
            if (! Cache::add('geocoding:provider-cooldown', true, 2)) {
                $this->busy();
            }
            try {
                $response = Http::acceptJson()->withUserAgent('ZionFood/'.config('zionfood.version').' (+https://github.com/matrixn/ZionFood)')->connectTimeout(3)->timeout(8)->withOptions(['allow_redirects' => false, 'proxy' => '', 'verify' => true, 'stream' => true, 'read_timeout' => 3])->get($url, $query);
                if (! $response->successful()) {
                    throw new \RuntimeException('Provider rejected request.');
                }
                $body = $response->toPsrResponse()->getBody();
                try {
                    $raw = '';
                    $deadline = hrtime(true) + 8_000_000_000;
                    while (! $body->eof()) {
                        if (hrtime(true) > $deadline) {
                            throw new \RuntimeException('Provider response timeout.');
                        }
                        $chunk = $body->read(min(8192, 131073 - strlen($raw)));
                        if ($chunk === '' && ! $body->eof()) {
                            throw new \RuntimeException('Incomplete provider response.');
                        }
                        $raw .= $chunk;
                        if (strlen($raw) > 131072) {
                            throw new \RuntimeException('Oversized provider response.');
                        }
                    }
                } finally {
                    $body->close();
                }
                $data = json_decode($raw, true, 32, JSON_THROW_ON_ERROR);
                if (! is_array($data['features'] ?? null)) {
                    throw new \RuntimeException('Malformed provider response.');
                }
                $results = array_slice($data['features'], 0, 5);
            } catch (\Throwable $e) {
                Log::warning('Restaurant geocoding provider unavailable.', ['exception_class' => $e::class]);
                $this->unavailable();
            }
            Cache::put($key, $results, 86400);

            return $results;
        } finally {
            $lock->release();
        }
    }

    private function unavailable(): never
    {
        throw ValidationException::withMessages(['location' => __('Address search is temporarily unavailable. Try again or place the pin on the map.')]);
    }

    private function busy(): never
    {
        throw ValidationException::withMessages(['location' => __('Address search is busy. Wait a few seconds and try again.')]);
    }
}
