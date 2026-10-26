<?php

namespace App\Domain\Mail;

use Illuminate\Support\Facades\Cache;
use RuntimeException;

class EmailQuota
{
    /** Reserve before attempting delivery; failed/uncertain submissions still count. */
    public function reserve(int $recipients): int
    {
        $limit = config('email_delivery.limit');
        if ($recipients < 1 || $recipients > $limit) {
            throw new RuntimeException('Email recipient count exceeds the daily quota.');
        }

        $store = config('email_delivery.cache_store');
        if (! app()->environment('testing') && config('cache.stores.'.$store.'.driver') !== 'redis') {
            throw new RuntimeException('Email quota requires a shared Redis cache store.');
        }
        $cache = Cache::store($store);

        return $cache->lock('mail:quota:lock', 10)->block(3, function () use ($cache, $recipients, $limit) {
            $now = now()->timestamp;
            $window = config('email_delivery.window_seconds');
            $slots = array_values(array_filter($cache->get('mail:quota:slots', []), fn ($time) => $time > $now - $window));
            sort($slots);
            if (count($slots) + $recipients > $limit) {
                return max(1, $slots[count($slots) + $recipients - $limit - 1] + $window - $now);
            }
            $slots = [...$slots, ...array_fill(0, $recipients, $now)];
            if (! $cache->put('mail:quota:slots', $slots, $window + 60)) {
                throw new RuntimeException('Unable to persist email quota.');
            }

            return 0;
        });
    }
}
