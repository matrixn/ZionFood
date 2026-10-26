<?php

namespace App\Domain\Reservations;

use App\Models\Reservation;
use App\Models\ReservationHistory;
use App\Models\RestaurantLocation;
use App\Notifications\ReservationNotification;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class CreateReservation
{
    public function execute(RestaurantLocation $context, array $input, string $key): array
    {
        $data = Validator::make($input, ['guest_name' => 'required|string|max:150', 'guest_phone' => ['required', 'string', 'max:35', 'regex:/^\+?[0-9][0-9 ().-]{5,33}[0-9]$/'], 'guest_email' => 'nullable|email|max:254', 'party_size' => 'required|integer|between:1,100', 'starts_at' => 'required|date_format:Y-m-d\TH:i', 'notes' => 'nullable|string|max:1000'])->validate();
        Validator::make(['key' => $key], ['key' => 'required|string|min:16|max:100|regex:/^[A-Za-z0-9_-]+$/'])->validate();
        ksort($data);
        $hash = hash('sha256', json_encode($data, JSON_THROW_ON_ERROR));

        return DB::transaction(function () use ($context, $data, $key, $hash) {
            $location = RestaurantLocation::whereKey($context->id)->lockForUpdate()->firstOrFail();
            $existing = Reservation::where('location_id', $location->id)->where('idempotency_key', $key)->first();
            if ($existing) {
                abort_unless(hash_equals($existing->request_hash, $hash), 409, __('The booking request has changed.'));

                return ['reservation' => $existing, 'token' => Crypt::decryptString($existing->token_encrypted), 'replayed' => true];
            }
            app(Availability::class)->ensureEnabled($location);
            [$start, $end, $rules] = app(Availability::class)->interval($location, $data['starts_at'], $data['party_size']);
            $table = app(Availability::class)->allocate($location, $start, $end, $data['party_size']);
            $token = Str::random(64);
            $status = ($rules['confirmation'] ?? 'manual') === 'automatic' ? 'confirmed' : 'pending';
            $booking = Reservation::create([...$data, 'public_id' => (string) Str::ulid(), 'organization_id' => $location->organization_id, 'location_id' => $location->id, 'dining_table_id' => $table?->id, 'starts_at' => $start, 'ends_at' => $end, 'status' => $status, 'locale' => app()->getLocale(), 'hold_expires_at' => $status === 'pending' ? now()->addMinutes($rules['hold_minutes'] ?? 15) : null, 'cancellation_deadline' => $start->subHours($rules['cancellation_hours'] ?? 24), 'timezone' => $location->timezone, 'idempotency_key' => $key, 'request_hash' => $hash, 'token_hash' => hash('sha256', $token), 'token_encrypted' => Crypt::encryptString($token)]);
            ReservationHistory::create(['reservation_id' => $booking->id, 'to_status' => $status]);
            if ($booking->guest_email) {
                Notification::route('mail', $booking->guest_email)->notify(new ReservationNotification($booking->id, $booking->version));
            }

            return ['reservation' => $booking, 'token' => $token, 'replayed' => false];
        }, 3);
    }
}
