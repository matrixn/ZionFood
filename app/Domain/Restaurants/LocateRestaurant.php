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
        $url = (string) config('geocoding.url');
        $parts = parse_url($url);
        if (! $parts || ($parts['scheme'] ?? '') !== 'https' || empty($parts['host']) || isset($parts['user']) || isset($parts['pass']) || isset($parts['query']) || isset($parts['fragment'])) {
            $this->unavailable();
        }
        $query = ['q' => implode(', ', array_filter([Str::squish($address['address']), Str::squish($address['city']), $address['postal_code'] ?? ''])), 'countrycode' => strtoupper($address['country']), 'limit' => 5];
        if (app()->getLocale() === 'en') {
            $query['lang'] = 'en';
        }
        $key = 'geocoding:result:'.hash('sha256', $url.json_encode($query));
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
                $results = [];
                foreach (array_slice($data['features'], 0, 5) as $feature) {
                    $coords = $feature['geometry']['coordinates'] ?? null;
                    $p = $feature['properties'] ?? null;
                    if (($feature['geometry']['type'] ?? '') !== 'Point' || ! is_array($coords) || count($coords) !== 2 || ! is_array($p) || strtoupper($p['countrycode'] ?? '') !== $query['countrycode'] || ! is_numeric($coords[0] ?? null) || ! is_numeric($coords[1] ?? null)) {
                        continue;
                    }
                    [$lng, $lat] = array_map('floatval', $coords);
                    if (! is_finite($lng) || ! is_finite($lat) || abs($lng) > 180 || abs($lat) > 85) {
                        continue;
                    }
                    $values = array_filter(array_map(fn ($k) => is_string($p[$k] ?? null) ? trim($p[$k]) : '', ['name', 'street', 'housenumber', 'postcode', 'city', 'district', 'country']));
                    if (! $values) {
                        continue;
                    }
                    $results[] = ['latitude' => $lat, 'longitude' => $lng, 'label' => mb_substr(implode(', ', array_unique($values)), 0, 500), 'approximate' => empty($p['housenumber'])];
                }
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
