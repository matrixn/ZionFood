<?php

namespace App\Domain\Localization;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use MaxMind\Db\Reader;
use RuntimeException;
use Throwable;

class UpdateGeoIp
{
    public const ORIGIN = 'https://download.db-ip.com/free/';

    public function execute(bool $scheduled = false): bool
    {
        return (bool) Cache::lock('geoip-country-update', 90)->get(function () use ($scheduled) {
            $path = config('geoip.database_path');
            $release = now('UTC')->format('Y-m');
            $manifest = is_file($path.'.release.json') ? json_decode(file_get_contents($path.'.release.json'), true) : null;
            if ($scheduled && ($manifest['release'] ?? null) === $release && app(GeoIpDatabase::class)->status()['ready']) {
                return true;
            }
            $compressed = $expanded = null;
            try {
                File::ensureDirectoryExists(dirname($path), 0750);
                $compressed = tempnam(dirname($path), 'geoip-gz-');
                $expanded = tempnam(dirname($path), 'geoip-mmdb-');
                $downloaded = false;
                $deadline = microtime(true) + 55;
                foreach ([$release, now('UTC')->subMonthNoOverflow()->format('Y-m')] as $month) {
                    if ($scheduled && ($manifest['release'] ?? null) === $month && app(GeoIpDatabase::class)->status()['ready']) {
                        return true;
                    }
                    $response = Http::connectTimeout(5)->timeout(25)->withOptions(['stream' => true, 'read_timeout' => 10, 'verify' => true, 'proxy' => '', 'allow_redirects' => false])->get(self::ORIGIN.'dbip-country-lite-'.$month.'.mmdb.gz');
                    if ($response->status() === 404) {
                        continue;
                    }
                    if (! $response->successful()) {
                        throw new RuntimeException('geoip_provider_response');
                    }
                    $body = $response->toPsrResponse()->getBody();
                    $file = fopen($compressed, 'wb');
                    $size = 0;
                    try {
                        while (! $body->eof()) {
                            if (microtime(true) >= $deadline) {
                                throw new RuntimeException('geoip_download_deadline');
                            }
                            $chunk = $body->read(65536);
                            $size += strlen($chunk);
                            if ($size > 16 * 1024 * 1024 || $chunk === '' || fwrite($file, $chunk) !== strlen($chunk)) {
                                throw new RuntimeException('geoip_download_bounds');
                            }
                        }
                    } finally {
                        fclose($file);
                        $body->close();
                    }
                    $release = $month;
                    $downloaded = true;
                    break;
                }
                if (! $downloaded || file_get_contents($compressed, false, null, 0, 2) !== "\x1f\x8b") {
                    throw new RuntimeException('geoip_archive');
                }
                $gzip = gzopen($compressed, 'rb');
                $file = fopen($expanded, 'wb');
                $size = 0;
                try {
                    while (! gzeof($gzip)) {
                        $chunk = @gzread($gzip, 65536);
                        if ($chunk === false || $chunk === '' && ! gzeof($gzip)) {
                            throw new RuntimeException('geoip_archive');
                        }
                        $size += strlen($chunk);
                        if ($size > 32 * 1024 * 1024 || fwrite($file, $chunk) !== strlen($chunk)) {
                            throw new RuntimeException('geoip_expansion_bounds');
                        }
                    }
                } finally {
                    gzclose($gzip);
                    fclose($file);
                }
                $reader = new Reader($expanded);
                try {
                    if (! str_contains($reader->metadata()->databaseType, 'Country') || ! in_array($reader->metadata()->ipVersion, [4, 6], true)) {
                        throw new RuntimeException('geoip_database_type');
                    }
                    $probe = $reader->get('81.2.69.160')['country']['iso_code'] ?? null;
                    if (! is_string($probe) || ! preg_match('/^[A-Z]{2}$/D', $probe)) {
                        throw new RuntimeException('geoip_country_record');
                    }
                } finally {
                    $reader->close();
                }
                chmod($expanded, 0640);
                if (! rename($expanded, $path)) {
                    throw new RuntimeException('geoip_replace');
                }
                try {
                    File::replace($path.'.release.json', json_encode(['release' => $release, 'updated_at' => now('UTC')->toIso8601String()], JSON_THROW_ON_ERROR), 0640);
                } catch (Throwable $e) {
                    // The database is already valid and published; a failed cache marker is not a rollback.
                    Log::warning('GeoIP database updated but release marker could not be saved', ['exception_class' => get_class($e)]);
                }
                Log::info('Country GeoIP database updated', ['provider' => 'db-ip-lite', 'release' => $release]);

                return true;
            } catch (Throwable $e) {
                Log::warning('Country GeoIP update failed; previous database retained', ['provider' => 'db-ip-lite', 'exception_class' => get_class($e)]);

                return false;
            } finally {
                foreach ([$compressed, $expanded] as $temporary) {
                    if (is_string($temporary) && is_file($temporary)) {
                        unlink($temporary);
                    }
                }
            }
        });
    }
}
