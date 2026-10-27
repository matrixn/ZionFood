<?php

namespace App\Domain\Reservations;

use App\Domain\Tenancy\TenantAccess;
use App\Models\Reservation;
use App\Models\ReservationHistory;
use App\Models\RestaurantLocation;
use App\Models\User;
use App\Notifications\ReservationNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

class TransitionReservation
{
    public function execute(Reservation $context, string $next, int $version, ?User $actor = null): Reservation
    {
        if ($actor) {
            app(TenantAccess::class)->requireFeature($actor, $context->location, 'reservations', true);
        }

        return DB::transaction(function () use ($context, $next, $version, $actor) {
            RestaurantLocation::whereKey($context->location_id)->lockForUpdate()->firstOrFail();
            $booking = Reservation::whereKey($context->id)->lockForUpdate()->firstOrFail();
            abort_unless($booking->version === $version, 409, __('The reservation changed. Refresh before trying again.'));
            if ($booking->status === 'pending' && $booking->hold_expires_at?->isPast()) {
                app(Availability::class)->fail('The pending reservation has expired.');
            }
            if (! $actor && ($next !== 'cancelled' || ($booking->status === 'confirmed' && $booking->cancellation_deadline->isPast()))) {
                app(Availability::class)->fail('Online cancellation is no longer available. Contact the restaurant.');
            }
            $allowed = ['pending' => ['confirmed', 'rejected', 'cancelled'], 'confirmed' => ['seated', 'cancelled', 'no_show'], 'seated' => ['completed', 'cancelled']];
            if (! in_array($next, $allowed[$booking->status] ?? [], true)) {
                app(Availability::class)->fail('This reservation status change is not allowed.');
            }
            if (in_array($next, ['seated', 'no_show', 'completed'], true) && $booking->starts_at->isFuture()) {
                app(Availability::class)->fail('This reservation has not started yet.');
            }
            $old = $booking->status;
            $booking->update(['status' => $next, 'version' => $booking->version + 1, 'hold_expires_at' => null]);
            ReservationHistory::create(['reservation_id' => $booking->id, 'from_status' => $old, 'to_status' => $next, 'actor_id' => $actor?->id]);
            if ($booking->guest_email) {
                Notification::route('mail', $booking->guest_email)->notify(new ReservationNotification($booking->id, $booking->version));
            }

            return $booking;
        }, 3);
    }
}
