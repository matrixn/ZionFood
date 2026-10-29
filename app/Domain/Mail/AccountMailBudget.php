<?php

namespace App\Domain\Mail;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class AccountMailBudget
{
    public function limit(): int
    {
        $provider = DB::table('mail_provider_limits')->where('provider', 'resend')->first(['daily_limit', 'usage']);
        $providerLimit = $provider?->daily_limit ?? config('email_delivery.resend_daily_limit');
        $reportedLimit = json_decode($provider?->usage ?? '{}', true)['daily']['limit'] ?? null;
        if (is_int($reportedLimit) && $reportedLimit >= 0) {
            $providerLimit = min((int) $providerLimit, $reportedLimit);
        }
        $capacity = min((int) config('email_delivery.limit'), (int) $providerLimit);

        return max(0, min((int) config('email_delivery.account_daily_limit'), (int) floor($capacity / 2)));
    }

    public function admitRequest(string $ip): int
    {
        return $this->reserve('requests', 1, hash('sha256', $ip));
    }

    public function reserveDelivery(string $source, int $recipients): int
    {
        if (! in_array($source, ['account_registration', 'account_verification', 'password_reset', 'legacy'], true)) {
            return 0;
        }

        return $this->reserve('delivery', $recipients);
    }

    private function reserve(string $bucket, int $count, ?string $ip = null): int
    {
        $store = app()->environment('testing') ? config('cache.default') : config('email_delivery.cache_store');
        if (! app()->environment('testing') && config('cache.stores.'.$store.'.driver') !== 'redis') {
            throw new RuntimeException('Account mail admission requires shared Redis storage.');
        }
        if ($count < 1) {
            throw new RuntimeException('Invalid account mail recipient count.');
        }
        $cache = Cache::store($store);

        return $cache->lock('mail:account:'.$bucket.':lock', 10)->block(3, function () use ($cache, $bucket, $count, $ip) {
            $now = now()->timestamp;
            $key = 'mail:account:'.$bucket.':slots';
            $slots = array_values(array_filter($cache->get($key, []), fn ($slot) => $slot['time'] > $now - 86400));
            if ($ip !== null) {
                $recent = array_values(array_filter($slots, fn ($slot) => $slot['ip'] === $ip && $slot['time'] > $now - 3600));
                if (count($recent) >= max(1, (int) config('email_delivery.account_requests_per_hour'))) {
                    return max(1, $recent[0]['time'] + 3600 - $now);
                }
            }
            $limit = $this->limit();
            if ($count > $limit) {
                return 86400;
            }
            if (count($slots) + $count > $limit) {
                return max(1, $slots[count($slots) + $count - $limit - 1]['time'] + 86400 - $now);
            }
            $slots = [...$slots, ...array_fill(0, $count, ['time' => $now, 'ip' => $ip])];
            if (! $cache->put($key, $slots, 86460)) {
                throw new RuntimeException('Unable to persist account mail budget.');
            }

            return 0;
        });
    }
}
