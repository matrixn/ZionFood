<?php

namespace App\Domain\Localization;

use MaxMind\Db\Reader;
use Throwable;

class GeoIpDatabase
{
    public function country(?string $ip): ?string
    {
        if (! is_string($ip)) {
            return null;
        }
        // Normalize IPv4-mapped IPv6 before the public-address check.
        $packed = @inet_pton($ip);
        if ($packed !== false && strlen($packed) === 16 && substr($packed, 0, 12) === str_repeat("\0", 10)."\xff\xff") {
            $ip = inet_ntop(substr($packed, 12));
        }
        if (! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_GLOBAL_RANGE) || ! is_file(config('geoip.database_path'))) {
            return null;
        }
        $reader = null;
        try {
            $reader = new Reader(config('geoip.database_path'));
            $country = $reader->get($ip)['country']['iso_code'] ?? null;

            return is_string($country) && preg_match('/^[A-Z]{2}$/D', $country) ? $country : null;
        } catch (Throwable) {
            // No raw IP logging and no network request on visitor paths.
            return null;
        } finally {
            $reader?->close();
        }
    }

    public function status(): array
    {
        $path = config('geoip.database_path');
        $reader = null;
        try {
            $reader = new Reader($path);
            $epoch = (int) $reader->metadata()->buildEpoch;

            return ['ready' => true, 'built_at' => gmdate('Y-m-d', $epoch), 'stale' => $epoch < now('UTC')->subDays(62)->timestamp];
        } catch (Throwable) {
            return ['ready' => false, 'built_at' => null, 'stale' => false];
        } finally {
            $reader?->close();
        }
    }
}
