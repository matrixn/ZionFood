<?php

use App\Domain\Integrations\Actions\DeliverOutboxMessage;
use App\Domain\Mail\EmailLedger;
use App\Domain\Mail\IndexQueuedEmails;
use App\Domain\Marketing\LandingSeo;
use App\Domain\Orders\ReleaseScheduledOrders;
use App\Jobs\DeliverOutbox;
use App\Jobs\DeliverWebhook;
use App\Jobs\DownloadGloriaFoodPhotos;
use App\Models\CommunicationLog;
use App\Models\EmailDelivery;
use App\Models\ImportBatch;
use App\Models\OutboxMessage;
use App\Models\Reservation;
use App\Models\ReservationHistory;
use App\Models\RestaurantLocation;
use App\Models\User;
use App\Models\WebhookDelivery;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

Artisan::command('zionfood:outbox', function () {
    app(DeliverOutboxMessage::class)->due()->orderBy('id')->chunkById(100, function ($messages) {
        foreach ($messages as $message) {
            DeliverOutbox::dispatch($message->id);
        }
    });
    $this->info('Due, non-exhausted events queued.');
});
Schedule::command('zionfood:outbox')->everyMinute()->withoutOverlapping();
Artisan::command('zionfood:outbox-retry {event}', function () {
    $event = strtoupper($this->argument('event'));
    if (! preg_match('/^[0-9A-HJKMNP-TV-Z]{26}$/', $event)) {
        $this->error('Provide one valid event ULID.');

        return 1;
    }
    if (! OutboxMessage::where('event_id', $event)->exists()) {
        $this->error('Event not found.');

        return 1;
    }
    if (! app(DeliverOutboxMessage::class)->retry($event)) {
        $this->error('Only an exhausted, undelivered event can be retried.');

        return 1;
    }
    $this->info('Event retry queued; lifetime attempts and payload preserved.');
});

Artisan::command('zionfood:reservations-expire', function () {
    Reservation::where('status', 'pending')->where('hold_expires_at', '<=', now())->chunkById(100, function ($bookings) {
        foreach ($bookings as $context) {
            DB::transaction(function () use ($context) {
                RestaurantLocation::whereKey($context->location_id)->lockForUpdate()->firstOrFail();
                $booking = Reservation::whereKey($context->id)->lockForUpdate()->firstOrFail();
                if ($booking->status !== 'pending' || $booking->hold_expires_at->isFuture()) {
                    return;
                }
                $booking->update(['status' => 'expired', 'version' => $booking->version + 1]);
                ReservationHistory::create(['reservation_id' => $booking->id, 'from_status' => 'pending', 'to_status' => 'expired']);
                OutboxMessage::create(['event_id' => (string) Str::ulid(), 'organization_id' => $booking->organization_id, 'location_id' => $booking->location_id, 'type' => 'reservation.updated', 'payload' => ['reservation_id' => $booking->public_id, 'version' => $booking->version]]);
            }, 3);
        }
    });
    $this->info('Expired reservation holds released.');
});
Schedule::command('zionfood:reservations-expire')->everyMinute()->withoutOverlapping();
Artisan::command('zionfood:webhooks', function () {
    WebhookDelivery::where(function ($query) {
        $query->where(function ($q) {
            $q->whereIn('status', ['pending', 'retrying'])->where('next_attempt_at', '<=', now());
        })->orWhere(function ($q) {
            $q->where('status', 'delivering')->where('locked_until', '<=', now());
        });
    })->chunkById(100, function ($deliveries) {
        foreach ($deliveries as $delivery) {
            DeliverWebhook::dispatch($delivery->id);
        }
    });
    $this->info('Due webhook deliveries queued.');
});
Schedule::command('zionfood:webhooks')->everyMinute()->withoutOverlapping();
Artisan::command('zionfood:admin {email}', function () {
    $user = User::where('email', $this->argument('email'))->first();
    if (! $user) {
        $this->error('Register the account first.');

        return 1;
    }
    $user->forceFill(['is_platform_admin' => true, 'email_verified_at' => now()])->save();
    $this->info('Platform administrator access granted.');
});

Artisan::command('zionfood:imports-prune', function () {
    ImportBatch::where('status', 'enriching')->where('updated_at', '<', now()->subHour())->pluck('id')->each(fn ($id) => (new DownloadGloriaFoodPhotos($id))->failed(null));
    ImportBatch::where('status', 'queued')->where('created_at', '<', now()->subHour())->update(['credential' => null, 'status' => 'failed', 'failure_code' => 'queue_expired']);
    ImportBatch::whereIn('status', ['ready', 'failed'])->where('created_at', '<', now()->subDays(max(1, (int) config('imports.retention_days'))))->chunkById(100, function ($batches) {
        foreach ($batches as $b) {
            DB::transaction(function () use ($b) {
                $locked = ImportBatch::whereKey($b->id)->lockForUpdate()->firstOrFail();
                if (! in_array($locked->status, ['ready', 'failed'], true)) {
                    return;
                }DB::table('import_snapshots')->where('batch_id', $b->id)->delete();
                DB::afterCommit(fn () => Storage::disk('local')->deleteDirectory('imports/'.$locked->public_id));
                $locked->update(['credential' => null, 'status' => 'expired']);
            });
        }
    });
    $this->info('Expired import credentials and unapproved snapshots cleared.');
});
Schedule::command('zionfood:imports-prune')->hourly()->withoutOverlapping();

Artisan::command('zionfood:mail-index {--limit=10000}', function () {
    $limit = max(1, min(10000, (int) $this->option('limit')));
    $result = app(IndexQueuedEmails::class)->execute($limit);
    $this->info(json_encode($result));
});
Schedule::command('zionfood:mail-index')->everyMinute()->withoutOverlapping();
Artisan::command('zionfood:mail-prune', function () {
    EmailDelivery::whereIn('state', EmailLedger::TERMINAL)->where('updated_at', '<', now()->subDays(config('email_delivery.ledger_retention_days')))->delete();
    $this->info('Expired terminal email metadata removed.');
});
Schedule::command('zionfood:mail-prune')->daily()->withoutOverlapping();

Artisan::command('zionfood:seo-check', function () {
    $seo = app(LandingSeo::class);
    $ro = $seo->meta('ro');
    $en = $seo->meta('en');
    $this->info('Canonical origin: '.$seo->baseUrl());
    $this->info('Romanian rendered: '.($ro['ready'] ? 'yes' : 'no'));
    $this->info('English rendered: '.($en['ready'] ? 'yes' : 'no'));
    $this->info('Indexing: '.$ro['robots']);
    $this->info('Sitemap: '.$seo->baseUrl().'/sitemap.xml');

    return $ro['ready'] && $en['ready'] && str_starts_with($ro['robots'], 'index') ? 0 : 1;
});

Schedule::call(fn () => app(ReleaseScheduledOrders::class)->execute())->name('release-scheduled-orders')->everyMinute()->withoutOverlapping();
Schedule::call(function () {
    CommunicationLog::where('created_at', '<', now()->subDays(config('wordpress.log_retention_days')))->delete();
    // Keep request hashes longer than encrypted response bodies so a stale retry cannot recreate a mutation.
    DB::table('wordpress_requests')->where('expires_at', '<', now())->whereNotNull('response')->update(['response' => null]);
    DB::table('wordpress_requests')->where('created_at', '<', now()->subDays(config('wordpress.request_retention_days')))->delete();
})->name('prune-wordpress-communications')->daily()->withoutOverlapping();
