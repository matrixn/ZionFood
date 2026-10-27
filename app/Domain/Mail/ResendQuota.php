<?php

namespace App\Domain\Mail;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Resend\Exceptions\ErrorException;
use Throwable;

class ResendQuota
{
    private function locked(callable $callback): mixed
    {
        DB::table('mail_provider_limits')->insertOrIgnore(['provider' => 'resend', 'utc_day' => now('UTC')->toDateString(), 'daily_limit' => config('email_delivery.resend_daily_limit', 100), 'created_at' => now(), 'updated_at' => now()]);

        return DB::transaction(function () use ($callback) {
            $state = DB::table('mail_provider_limits')->where('provider', 'resend')->lockForUpdate()->first();
            $changes = [];
            if ($state->utc_day !== now('UTC')->toDateString()) {
                $changes = ['utc_day' => now('UTC')->toDateString(), 'reserved' => 0];
            }
            if ($state->paused_until && CarbonImmutable::parse($state->paused_until)->isPast()) {
                $changes += ['pause_reason' => null, 'paused_until' => null];
            }
            if ($changes) {
                DB::table('mail_provider_limits')->where('provider', 'resend')->update($changes);
                foreach ($changes as $key => $value) {
                    $state->$key = $value;
                }
            }

            return $callback($state);
        });
    }

    private function wait(object $state): array
    {
        return $state->pause_reason ? ['reason' => $state->pause_reason, 'seconds' => $state->paused_until ? max(1, CarbonImmutable::parse($state->paused_until)->timestamp - now()->timestamp) : 86400] : ['reason' => null, 'seconds' => 0];
    }

    public function status(): array
    {
        return $this->locked(fn ($s) => ['daily_limit' => $s->daily_limit, 'reserved' => $s->reserved, 'utc_day' => $s->utc_day, 'reason' => $s->pause_reason, 'paused_until' => $s->paused_until ? CarbonImmutable::parse($s->paused_until, 'UTC')->toIso8601String() : null, 'reset_at' => CarbonImmutable::now('UTC')->addDay()->startOfDay()->toIso8601String(), 'revision' => $s->revision, 'usage' => $s->usage ? json_decode($s->usage, true) : null, 'usage_checked_at' => $s->usage_checked_at ? CarbonImmutable::parse($s->usage_checked_at, 'UTC')->toIso8601String() : null, 'usage_error' => $s->usage_error]);
    }

    public function delay(): array
    {
        return $this->locked(fn ($s) => $this->wait($s));
    }

    public function reserve(int $recipients): array
    {
        return $this->locked(function ($state) use ($recipients) {
            $wait = $this->wait($state);
            if ($wait['seconds']) {
                return $wait;
            }
            if ($recipients > $state->daily_limit) {
                return ['reason' => 'message_exceeds_provider_limit', 'seconds' => 0];
            }
            if ($state->reserved + $recipients > $state->daily_limit) {
                $until = CarbonImmutable::now('UTC')->addDay()->startOfDay();
                DB::table('mail_provider_limits')->where('provider', 'resend')->update(['pause_reason' => 'local_daily_limit', 'paused_until' => $until, 'updated_at' => now()]);

                return ['reason' => 'local_daily_limit', 'seconds' => $until->timestamp - now()->timestamp];
            }
            DB::table('mail_provider_limits')->where('provider', 'resend')->increment('reserved', $recipients);

            return ['reason' => null, 'seconds' => 0];
        });
    }

    public function rejection(Throwable $error): ?string
    {
        for ($i = 0; $error && $i < 8; $i++, $error = $error->getPrevious()) {
            if ($error instanceof ErrorException) {
                try {
                    $type = $error->getErrorType();
                } catch (Throwable) {
                    return null;
                }
                if (in_array($type, ['daily_quota_exceeded', 'monthly_quota_exceeded', 'rate_limit_exceeded'], true)) {
                    return $type;
                }
            }
        }

        return null;
    }

    public function pause(string $reason): array
    {
        return $this->locked(function ($state) use ($reason) {
            $until = match ($reason) {
                'daily_quota_exceeded' => CarbonImmutable::now('UTC')->addDay()->startOfDay(),
                'rate_limit_exceeded' => CarbonImmutable::now('UTC')->addMinute(),
                default => ($state->usage && ($reset = json_decode($state->usage, true)['monthly']['resets_at'] ?? null) && CarbonImmutable::parse($reset)->isFuture()) ? CarbonImmutable::parse($reset) : null,
            };
            // A short rate pause cannot shorten a known longer pause.
            if ($state->pause_reason && (! $state->paused_until || ($until && CarbonImmutable::parse($state->paused_until)->greaterThan($until)))) {
                return $this->wait($state);
            }
            DB::table('mail_provider_limits')->where('provider', 'resend')->update(['pause_reason' => $reason, 'paused_until' => $until, 'updated_at' => now()]);

            return ['reason' => $reason, 'seconds' => $until ? max(1, $until->timestamp - now()->timestamp) : 86400];
        });
    }

    public function configure(int $limit, int $revision): void
    {
        $this->locked(function ($state) use ($limit, $revision) {
            abort_unless($state->revision === $revision, 409);
            // Never clear a provider-confirmed quota by editing the local guard.
            $clear = $state->pause_reason === 'local_daily_limit' && $limit > $state->reserved;
            DB::table('mail_provider_limits')->where('provider', 'resend')->update(['daily_limit' => $limit, 'revision' => $revision + 1, ...($clear ? ['pause_reason' => null, 'paused_until' => null] : [])]);
        });
    }

    public function syncUsage(): void
    {
        $lock = Cache::store(config('email_delivery.cache_store'))->lock('mail:resend:send', 90);
        abort_unless($lock->get(), 409, __('Wait for the current email operation to finish.'));
        try {
            if (blank(config('services.resend.key'))) {
                throw new \RuntimeException('missing_key');
            }
            $response = Http::withToken(config('services.resend.key'))->acceptJson()->timeout(8)->connectTimeout(3)->withOptions(['allow_redirects' => false, 'proxy' => '', 'stream' => true])->get('https://api.resend.com/usage');
            if (! $response->successful()) {
                throw new \RuntimeException(in_array($response->status(), [401, 403], true) ? 'usage_not_authorized' : 'usage_unavailable');
            }
            $body = $response->toPsrResponse()->getBody()->read(65537);
            if (strlen($body) > 65536) {
                throw new \RuntimeException('usage_invalid');
            }
            $raw = json_decode($body, true, 32, JSON_THROW_ON_ERROR)['emails'] ?? [];
            $usage = [];
            foreach (['daily', 'monthly'] as $period) {
                $row = $raw[$period] ?? [];
                if (! is_int($row['used'] ?? null) || $row['used'] < 0 || ! array_key_exists('limit', $row) || (! is_null($row['limit']) && (! is_int($row['limit']) || $row['limit'] < 0))) {
                    throw new \RuntimeException('usage_invalid');
                }
                $reset = CarbonImmutable::parse($row['resets_at'] ?? 'invalid');
                if (! $reset->isFuture() || $reset->greaterThan(now()->addDays(370))) {
                    throw new \RuntimeException('usage_invalid');
                }
                $usage[$period] = ['used' => $row['used'], 'limit' => $row['limit'], 'resets_at' => $reset->utc()->toIso8601String()];
            }
            $this->locked(function ($state) use ($usage) {
                $changes = ['usage' => json_encode($usage), 'usage_checked_at' => now(), 'usage_error' => null];
                $daily = $usage['daily'];
                $monthly = $usage['monthly'];
                $changes['reserved'] = max($state->reserved, $daily['used']);
                if ($monthly['limit'] !== null && $monthly['used'] >= $monthly['limit']) {
                    $changes += ['pause_reason' => 'monthly_quota_exceeded', 'paused_until' => CarbonImmutable::parse($monthly['resets_at'])];
                } elseif ($daily['limit'] !== null && $daily['used'] >= $daily['limit']) {
                    $changes += ['pause_reason' => 'daily_quota_exceeded', 'paused_until' => CarbonImmutable::parse($daily['resets_at'])];
                } elseif (in_array($state->pause_reason, ['daily_quota_exceeded', 'monthly_quota_exceeded'], true)) {
                    $changes += ['pause_reason' => null, 'paused_until' => null];
                }
                DB::table('mail_provider_limits')->where('provider', 'resend')->update($changes);
            });
        } catch (Throwable $error) {
            $code = in_array($error->getMessage(), ['missing_key', 'usage_not_authorized', 'usage_unavailable', 'usage_invalid'], true) ? $error->getMessage() : 'usage_unavailable';
            $this->locked(fn () => DB::table('mail_provider_limits')->where('provider', 'resend')->update(['usage_error' => $code]));
        } finally {
            $lock->release();
        }
    }
}
