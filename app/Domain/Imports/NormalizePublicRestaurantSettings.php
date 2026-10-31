<?php

namespace App\Domain\Imports;

use App\Domain\Delivery\Polygon;
use Illuminate\Support\Facades\Validator;

class NormalizePublicRestaurantSettings
{
    public const FIELDS = ['name', 'address', 'phone', 'hours', 'zones'];

    public function execute(array $source): array
    {
        $fields = [];
        foreach (self::FIELDS as $field) {
            try {
                $fields[$field] = ['available' => true, 'value' => $this->{$field}($source), 'error' => null];
            } catch (\Throwable) {
                $fields[$field] = ['available' => false, 'value' => null, 'error' => $field];
            }
        }

        return $fields;
    }

    private function name(array $source): string
    {
        return Validator::make($source, ['name' => 'required|string|max:150'])->validate()['name'];
    }

    private function phone(array $source): string
    {
        return Validator::make($source, ['phone' => 'required|string|max:30|regex:/^\+?[0-9][0-9 ().-]{5,28}[0-9]$/'])->validate()['phone'];
    }

    private function address(array $source): array
    {
        $account = $source['restaurantAccount'] ?? [];
        $data = ['address' => $source['address'] ?? null, 'city' => $account['city'] ?? null, 'postal_code' => $account['zipcode'] ?? null, 'country' => $account['country_code'] ?? null,
            'latitude' => $source['latitude'] ?? null, 'longitude' => $source['longitude'] ?? null];
        $data = Validator::make($data, ['address' => 'required|string|max:255', 'city' => 'required|string|max:100', 'postal_code' => 'nullable|string|max:20', 'country' => 'required|string|regex:/^[A-Z]{2}$/', 'latitude' => 'nullable|numeric|between:-85,85|required_with:longitude', 'longitude' => 'nullable|numeric|between:-180,180|required_with:latitude'])->validate();
        foreach (['latitude', 'longitude'] as $key) {
            $data[$key] = isset($data[$key]) ? (float) $data[$key] : null;
        }

        return $data;
    }

    private function hours(array $source): array
    {
        $timezone = $source['restaurantAccount']['timezone'] ?? null;
        Validator::make(['timezone' => $timezone, 'hours' => $source['opening_hours'] ?? null], ['timezone' => 'required|timezone', 'hours' => 'required|array|max:100'])->validate();
        $days = [];
        foreach ($source['opening_hours'] as $row) {
            if (($row['type'] ?? '') !== 'opening') {
                // Per-service schedules have no equivalent in the current single location schedule.
                throw new \RuntimeException('hours_service_schedule');
            }
            Validator::make($row, ['day_of_week' => 'required|integer|between:1,127', 'begin_minute' => 'required|integer|between:0,1438', 'end_minute' => 'required|integer|between:1,1439|gt:begin_minute'])->validate();
            for ($day = 1; $day <= 7; $day++) {
                if ($row['day_of_week'] & (1 << ($day - 1))) {
                    $value = ['weekday' => $day, 'opens_at' => sprintf('%02d:%02d', intdiv($row['begin_minute'], 60), $row['begin_minute'] % 60), 'closes_at' => sprintf('%02d:%02d', intdiv($row['end_minute'], 60), $row['end_minute'] % 60)];
                    if (isset($days[$day]) && $days[$day] !== $value) {
                        throw new \RuntimeException('hours_multiple_intervals');
                    }
                    $days[$day] = $value;
                }
            }
        }
        if (! $days || ! empty($source['open_hour_exception'])) {
            throw new \RuntimeException('hours_exceptions');
        }
        ksort($days);

        return ['timezone' => $timezone, 'hours' => array_values($days)];
    }

    private function zones(array $source): array
    {
        Validator::make($source, ['delivery_zones' => 'required|array|max:30'])->validate();
        $zones = [];
        foreach ($source['delivery_zones'] as $zone) {
            if (($zone['usage'] ?? '') !== 'delivery' || ($zone['type'] ?? '') !== 'polygon' || ($zone['delivery_fee_type'] ?? '') !== 'fixed') {
                throw new \RuntimeException('zones_unsupported');
            }
            Validator::make($zone, ['id' => 'required|integer|min:1', 'name' => 'required|string|max:100', 'active' => 'required|boolean', 'shape_json' => 'required|string|max:30000'])->validate();
            $shape = json_decode($zone['shape_json'], true, 10, JSON_THROW_ON_ERROR);
            if (! is_array($shape) || array_keys($shape) !== ['path']) {
                throw new \RuntimeException('zones_unsupported_shape');
            }
            Validator::make($shape, ['path' => 'required|array|min:3|max:200', 'path.*.lat' => 'required|numeric|between:-85,85', 'path.*.lng' => 'required|numeric|between:-180,180'])->validate();
            $ring = array_map(fn ($p) => [(float) $p['lng'], (float) $p['lat']], $shape['path']);
            if (reset($ring) !== end($ring)) {
                $ring[] = $ring[0];
            }
            $geometry = ['type' => 'Polygon', 'coordinates' => [$ring]];
            if (! Polygon::valid($geometry) || isset($zones[$zone['id']])) {
                throw new \RuntimeException('zones_invalid');
            }
            $zones[$zone['id']] = ['source_id' => (string) $zone['id'], 'name' => $zone['name'], 'postal_prefix' => '', 'geometry' => $geometry,
                'fee' => $this->money($zone['delivery_fee'], 1000000), 'minimum_order' => $this->money($zone['minimum_order'], 100000000), 'is_active' => (bool) $zone['active']];
        }

        return array_values($zones);
    }

    private function money(mixed $value, int $maximum): int
    {
        if (! (is_int($value) || is_float($value) || is_string($value)) || ! preg_match('/^\d{1,8}(?:\.\d{1,2})?$/D', (string) $value)) {
            throw new \RuntimeException('zones_money');
        }
        $parts = explode('.', (string) $value);
        $amount = (int) $parts[0] * 100 + (int) str_pad($parts[1] ?? '', 2, '0');
        if ($amount > $maximum) {
            throw new \RuntimeException('zones_money');
        }

        return $amount;
    }
}
