<?php

use App\Jobs\DeliverOutbox;
use App\Jobs\DeliverWebhook;
use App\Models\OutboxMessage;
use App\Models\Reservation;
use App\Models\ReservationHistory;
use App\Models\RestaurantLocation;
use App\Models\User;
use App\Models\WebhookDelivery;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schedule;

Artisan::command('zionfood:outbox', function () {
    OutboxMessage::whereNull('delivered_at')->orderBy('id')->chunkById(100, function ($messages) {
        foreach ($messages as $message) {
            DeliverOutbox::dispatch($message->id);
        }
    });
    $this->info('Undelivered events queued.');
});
Schedule::command('zionfood:outbox')->everyMinute()->withoutOverlapping();
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
