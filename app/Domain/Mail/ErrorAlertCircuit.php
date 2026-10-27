<?php

namespace App\Domain\Mail;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ErrorAlertCircuit
{
    public const LIMIT = 5;

    public const WINDOW = 3600;

    private function locked(callable $callback): mixed
    {
        DB::table('email_alert_settings')->insertOrIgnore(['id' => 1, 'admissions' => '[]', 'deliveries' => '[]']);

        return DB::transaction(function () use ($callback) {
            $row = DB::table('email_alert_settings')->where('id', 1)->lockForUpdate()->first();
            $admissions = array_values(array_filter(json_decode($row->admissions, true), fn ($at) => $at > now()->timestamp - self::WINDOW));
            $deliveries = array_values(array_filter(json_decode($row->deliveries, true), fn ($entry) => $entry['at'] > now()->timestamp - self::WINDOW));

            return $callback($row, $admissions, $deliveries);
        });
    }

    public function status(): array
    {
        return $this->locked(fn ($row, $admissions, $deliveries) => [
            'enabled' => (bool) $row->enabled, 'environment_enabled' => (bool) config('email_delivery.alerts_enabled'),
            'suspended_at' => $row->suspended_at, 'reason' => $row->reason, 'version' => $row->version,
            'limit' => self::LIMIT, 'window_seconds' => self::WINDOW, 'admitted' => count($admissions),
            'delivery_attempts' => count($deliveries), 'accepted' => count(array_filter($deliveries, fn ($entry) => $entry['accepted'])),
        ]);
    }

    public function admit(): ?int
    {
        return $this->locked(function ($row, $admissions) {
            if (! config('email_delivery.alerts_enabled') || ! $row->enabled || $row->suspended_at) {
                return null;
            }
            if (count($admissions) >= self::LIMIT) {
                $this->suspend('hourly_alert_limit');

                return null;
            }
            $admissions[] = now()->timestamp;
            DB::table('email_alert_settings')->where('id', 1)->update(['admissions' => json_encode($admissions)]);

            return $row->generation;
        });
    }

    public function canDeliver(?int $generation): bool
    {
        return $this->locked(fn ($row) => config('email_delivery.alerts_enabled') && $row->enabled && ! $row->suspended_at && $generation === $row->generation);
    }

    public function reserveDelivery(?int $generation): ?string
    {
        return $this->locked(function ($row, $admissions, $deliveries) use ($generation) {
            if (! config('email_delivery.alerts_enabled') || ! $row->enabled || $row->suspended_at || $generation !== $row->generation) {
                return null;
            }
            if (count($deliveries) >= self::LIMIT) {
                $this->suspend('hourly_delivery_limit');

                return null;
            }
            $id = (string) Str::ulid();
            $deliveries[] = ['id' => $id, 'at' => now()->timestamp, 'accepted' => false];
            DB::table('email_alert_settings')->where('id', 1)->update(['deliveries' => json_encode($deliveries)]);

            return $id;
        });
    }

    public function accepted(string $id): void
    {
        $this->locked(function ($row, $admissions, $deliveries) use ($id) {
            foreach ($deliveries as &$entry) {
                if ($entry['id'] === $id) {
                    $entry['accepted'] = true;
                    $entry['at'] = now()->timestamp;
                }
            }
            DB::table('email_alert_settings')->where('id', 1)->update(['deliveries' => json_encode($deliveries)]);
        });
    }

    private function suspend(string $reason): void
    {
        DB::table('email_alert_settings')->where('id', 1)->update(['suspended_at' => now(), 'reason' => $reason, 'version' => DB::raw('version + 1')]);
        EmailDeliveryLog::write('alerts_suspended', ['reason' => $reason, 'limit' => self::LIMIT], 'warning');
    }

    public function configure(bool $enabled, bool $resume, int $version): void
    {
        $this->locked(function ($row) use ($enabled, $resume, $version) {
            abort_unless($row->version === $version, 409);
            $changes = ['enabled' => $enabled, 'version' => $row->version + 1];
            if ($resume || ! $enabled || ! $row->enabled) {
                $changes += ['generation' => $row->generation + 1, 'admissions' => '[]', 'deliveries' => '[]'];
            }
            if ($resume) {
                $changes += ['suspended_at' => null, 'reason' => null];
            }
            DB::table('email_alert_settings')->where('id', 1)->update($changes);
        });
    }
}
