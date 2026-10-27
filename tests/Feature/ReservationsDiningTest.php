<?php

use App\Domain\Dining\TableSessions;
use App\Domain\Orders\Actions\PlaceOrderAction;
use App\Domain\Reservations\CreateReservation;
use App\Domain\Reservations\TransitionReservation;
use App\Domain\Restaurants\SyncDiningTables;
use App\Models\DiningTable;
use App\Models\Reservation;
use App\Models\StorefrontDomain;
use App\Notifications\ReservationNotification;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    Notification::fake();
    $this->travelTo(now('UTC')->setDate(2026, 11, 2)->setTime(8, 0));
});

function seatedVenue(string $slug = 'reservation-venue', string $mode = 'tables', string $confirmation = 'automatic'): array
{
    [$owner, $location, $item] = tenantFixture($slug);
    $profile = ['seating' => ['mode' => $mode, 'capacity' => 8, 'tables' => [['code' => 'T1', 'area' => 'Salon', 'seats' => 4], ['code' => 'T2', 'area' => 'Terasă', 'seats' => 4]]], 'services' => ['qr_requires_staff' => true], 'reservations' => ['planned' => true, 'confirmation' => $confirmation, 'duration_minutes' => 90, 'interval_minutes' => 30, 'minimum_notice_minutes' => 60, 'advance_days' => 30, 'min_party_size' => 1, 'max_party_size' => 8, 'cancellation_hours' => 0, 'hold_minutes' => 15]];
    $location->update(['setup_profile' => $profile]);
    $location->fulfillment()->update(['reservations_enabled' => true, 'dine_in_enabled' => true]);
    app(SyncDiningTables::class)->execute($location, $profile['seating']);

    return [$owner, $location->fresh(), $item];
}
function bookingPayload(int $party = 4, string $time = '2026-11-03T12:00'): array
{
    return ['guest_name' => 'Guest', 'guest_phone' => '0712345678', 'party_size' => $party, 'starts_at' => $time];
}

it('allocates tables atomically and replays the same reservation without using capacity twice', function () {
    [, $location] = seatedVenue();
    $action = app(CreateReservation::class);
    $key = (string) Str::uuid();
    $first = $action->execute($location, bookingPayload(), $key);
    $second = $action->execute($location, bookingPayload(), $key);
    expect($first['reservation']->id)->toBe($second['reservation']->id)->and($second['replayed'])->toBeTrue()->and(Reservation::count())->toBe(1);
    $other = $action->execute($location, bookingPayload(), (string) Str::uuid());
    expect($first['reservation']->dining_table_id)->not->toBe($other['reservation']->dining_table_id);
    expect(fn () => $action->execute($location, bookingPayload(), (string) Str::uuid()))->toThrow(ValidationException::class);
});

it('uses actual concurrent capacity rather than adding disjoint overlaps together', function () {
    [, $location] = seatedVenue('capacity-window', 'capacity');
    $action = app(CreateReservation::class);
    $action->execute($location, bookingPayload(4, '2026-11-03T10:00'), (string) Str::uuid());
    $action->execute($location, bookingPayload(4, '2026-11-03T12:00'), (string) Str::uuid());
    $result = $action->execute($location, bookingPayload(4, '2026-11-03T11:00'), (string) Str::uuid());
    expect($result['reservation']->dining_table_id)->toBeNull()->and(Reservation::count())->toBe(3);
});

it('releases expired pending holds even before the scheduler marks their history', function () {
    [, $location] = seatedVenue('manual-holds', 'capacity', 'manual');
    $action = app(CreateReservation::class);
    $first = $action->execute($location, bookingPayload(8), (string) Str::uuid())['reservation'];
    expect($first->status)->toBe('pending');
    expect(fn () => $action->execute($location, bookingPayload(1), (string) Str::uuid()))->toThrow(ValidationException::class);
    $this->travel(16)->minutes();
    $action->execute($location, bookingPayload(8), (string) Str::uuid());
    $this->artisan('zionfood:reservations-expire')->assertSuccessful();
    expect($first->fresh()->status)->toBe('expired')->and($first->fresh()->history()->count())->toBe(2);
});

it('rejects invalid hours, past times and tables too small for the party', function () {
    [, $location] = seatedVenue('booking-rules');
    $action = app(CreateReservation::class);
    foreach ([bookingPayload(9), bookingPayload(6), bookingPayload(2, '2026-11-01T12:00'), bookingPayload(2, '2026-11-03T12:17'), bookingPayload(2, '2026-11-03T23:30')] as $data) {
        expect(fn () => $action->execute($location, $data, (string) Str::uuid()))->toThrow(ValidationException::class);
    }
});

it('protects reservation tokens across locations and supports versioned customer cancellation', function () {
    [, $location] = seatedVenue('customer-booking');
    $booking = app(CreateReservation::class)->execute($location, bookingPayload(), (string) Str::uuid());
    $id = $booking['reservation']->public_id;
    $this->getJson('/api/v1/public/reservations/'.$id)->assertNotFound();
    $this->withToken($booking['token'])->getJson('/api/v1/public/reservations/'.$id)->assertOk()->assertJsonPath('can_cancel', true)->assertJsonMissingPath('guest_phone');
    $this->postJson('/api/v1/public/reservations/'.$id.'/cancel', ['version' => 1])->assertOk()->assertJsonPath('status', 'cancelled');
    expect($booking['reservation']->fresh()->history()->count())->toBe(2);
});

it('prevents staff from another tenant from changing or viewing reservations', function () {
    [, $location] = seatedVenue('owned-bookings');
    [$foreign] = tenantFixture('foreign-bookings');
    $booking = app(CreateReservation::class)->execute($location, bookingPayload(), (string) Str::uuid())['reservation'];
    $this->actingAs($foreign)->get('/app/locations/'.$location->public_id.'/reservations')->assertForbidden();
    $this->post('/app/reservations/'.$booking->public_id.'/status', ['status' => 'cancelled', 'version' => 1])->assertForbidden();
});

it('preserves bookings while independently disabling further reservations', function () {
    [$owner, $location] = seatedVenue('disabled-bookings');
    $booking = app(CreateReservation::class)->execute($location, bookingPayload(), (string) Str::uuid())['reservation'];
    $location->fulfillment()->update(['reservations_enabled' => false]);
    expect(fn () => app(CreateReservation::class)->execute($location, bookingPayload(), (string) Str::uuid()))->toThrow(ValidationException::class);
    expect(app(TransitionReservation::class)->execute($booking, 'cancelled', 1, $owner)->status)->toBe('cancelled');
});

it('requires an open table session and snapshots its identity into kitchen orders', function () {
    [$owner, $location, $item] = seatedVenue('dining-orders');
    $table = DiningTable::where('location_id', $location->id)->first();
    expect(fn () => app(TableSessions::class)->open($table))->toThrow(ValidationException::class);
    $session = app(TableSessions::class)->open($table, $owner);
    $data = [...checkoutData($item), 'fulfillment' => 'dine_in', 'table_session_token' => Crypt::decryptString($session->token_encrypted)];
    $order = app(PlaceOrderAction::class)->execute($location, $data, (string) Str::uuid())['order'];
    expect($order->table_code)->toBe($table->code)->and($order->dining_session_id)->toBe($session->id)->and($order->delivery_fee)->toBe(0)->and($order->total)->toBe(7000);
    app(TableSessions::class)->close($owner, $table);
    expect(fn () => app(PlaceOrderAction::class)->execute($location, $data, (string) Str::uuid()))->toThrow(ValidationException::class);
});

it('invalidates old QR links on rotation and enforces the mapped storefront location', function () {
    [$owner, $location] = seatedVenue('table-rotation');
    $table = DiningTable::where('location_id', $location->id)->first();
    $token = Crypt::decryptString($table->qr_token_encrypted);
    app(TableSessions::class)->open($table, $owner);
    $this->postJson('/api/v1/public/tables/'.$table->public_id.'/connect', ['qr_token' => $token])->assertOk()->assertJsonStructure(['token', 'expires_at']);
    app(TableSessions::class)->rotate($owner, $table);
    $this->postJson('/api/v1/public/tables/'.$table->public_id.'/connect', ['qr_token' => $token])->assertNotFound();
});

it('allows direct QR sessions only when that location explicitly opts in', function () {
    [, $location] = seatedVenue('direct-qr');
    $profile = $location->setup_profile;
    $profile['services']['qr_requires_staff'] = false;
    $location->update(['setup_profile' => $profile]);
    $table = DiningTable::where('location_id', $location->id)->first();
    expect(app(TableSessions::class)->open($table)->opened_by)->toBeNull();
});

it('queues localized encrypted reservation notifications and skips obsolete updates', function () {
    [, $location] = seatedVenue('booking-mail');
    $result = app(CreateReservation::class)->execute($location, [...bookingPayload(), 'guest_email' => 'guest@example.test'], (string) Str::uuid());
    Notification::assertSentOnDemand(ReservationNotification::class);
    $notice = new ReservationNotification($result['reservation']->id, 1);
    expect($notice)->toBeInstanceOf(ShouldBeEncrypted::class)->and($notice->shouldSend(new stdClass, 'mail'))->toBeTrue();
    $result['reservation']->update(['version' => 2]);
    expect($notice->shouldSend(new stdClass, 'mail'))->toBeFalse();
});

it('enforces mapped storefronts for reservation receipts and QR proofs', function () {
    [, $location] = seatedVenue('mapped-booking');
    [, $foreign] = tenantFixture('mapped-foreign');
    $result = app(CreateReservation::class)->execute($location, bookingPayload(), (string) Str::uuid());
    $host = StorefrontDomain::where('location_id', $foreign->id)->firstOrFail()->hostname;
    $this->withToken($result['token'])->getJson('http://'.$host.'/api/v1/public/reservations/'.$result['reservation']->public_id)->assertNotFound();
    $table = DiningTable::where('location_id', $location->id)->firstOrFail();
    $this->postJson('http://'.$host.'/api/v1/public/tables/'.$table->public_id.'/connect', ['qr_token' => Crypt::decryptString($table->qr_token_encrypted)])->assertNotFound();
});

it('rejects stale reservation updates and protects seating while bookings exist', function () {
    [$owner, $location] = seatedVenue('booking-versions');
    $result = app(CreateReservation::class)->execute($location, bookingPayload(), (string) Str::uuid());
    expect(fn () => app(SyncDiningTables::class)->assertEditable($location))->toThrow(ValidationException::class);
    $this->actingAs($owner)->post('/app/reservations/'.$result['reservation']->public_id.'/status', ['status' => 'cancelled', 'version' => 2])->assertConflict();
    expect($result['reservation']->fresh()->status)->toBe('confirmed');
    app(TransitionReservation::class)->execute($result['reservation'], 'cancelled', 1, $owner);
    app(SyncDiningTables::class)->assertEditable($location);
    expect($result['reservation']->fresh()->version)->toBe(2);
});

it('keeps a permanent QR paused until staff explicitly reopen its table menu', function () {
    [$owner, $location, $item] = seatedVenue('permanent-paused-qr');
    $profile = $location->setup_profile;
    $profile['services']['qr_requires_staff'] = false;
    $location->update(['setup_profile' => $profile]);
    $table = DiningTable::where('location_id', $location->id)->firstOrFail();
    $proof = Crypt::decryptString($table->qr_token_encrypted);
    $session = app(TableSessions::class)->open($table, null, $proof);
    app(TableSessions::class)->close($owner, $table);
    expect(fn () => app(TableSessions::class)->open($table, null, $proof))->toThrow(ValidationException::class);
    expect(fn () => app(PlaceOrderAction::class)->execute($location, [...checkoutData($item), 'fulfillment' => 'dine_in', 'table_session_token' => Crypt::decryptString($session->token_encrypted)], (string) Str::uuid()))->toThrow(ValidationException::class);
    app(TableSessions::class)->open($table, $owner);
    expect(app(TableSessions::class)->open($table, null, $proof)->id)->not->toBe($session->id);
});

it('enforces the configured QR closing cutoff on both connection and checkout', function () {
    [$owner, $location, $item] = seatedVenue('qr-cutoff');
    $table = DiningTable::where('location_id', $location->id)->firstOrFail();
    $session = app(TableSessions::class)->open($table, $owner);
    $location->hours()->update(['opens_at' => '10:00', 'closes_at' => '22:00']);
    $location->fulfillment()->update(['dine_in_cutoff_minutes' => 60]);
    $this->travelTo(CarbonImmutable::parse('2026-11-02 20:59:59', $location->timezone));
    $data = [...checkoutData($item), 'fulfillment' => 'dine_in', 'table_session_token' => Crypt::decryptString($session->token_encrypted)];
    // Refresh session expiry so this test isolates the closing window, not the eight-hour session lifetime.
    $session->update(['expires_at' => now()->addHour()]);
    app(PlaceOrderAction::class)->execute($location, $data, (string) Str::uuid());
    $this->travel(1)->seconds();
    expect(fn () => app(PlaceOrderAction::class)->execute($location, $data, (string) Str::uuid()))->toThrow(ValidationException::class);
    expect(fn () => app(TableSessions::class)->open($table, null, Crypt::decryptString($table->qr_token_encrypted)))->toThrow(ValidationException::class);
    $location->fulfillment()->update(['dine_in_cutoff_minutes' => 30]);
    expect(app(PlaceOrderAction::class)->execute($location, $data, (string) Str::uuid())['order']->fulfillment)->toBe('dine_in');
});

it('saves the restaurant location cutoff in settings without enabling other services', function () {
    [$owner, $location] = seatedVenue('cutoff-settings');
    $this->actingAs($owner)->put('/app/locations/'.$location->public_id.'/settings', ['name' => $location->name, 'address' => $location->address, 'accepting_orders' => true, 'pickup_enabled' => true, 'delivery_enabled' => false, 'dine_in_cutoff_minutes' => 60, 'hours' => [], 'zones' => []])->assertSessionHasNoErrors();
    expect($location->fulfillment()->first()->dine_in_cutoff_minutes)->toBe(60)->and($location->fulfillment()->first()->delivery_enabled)->toBeFalse();
});

it('returns a host-safe restaurant link after a reservation including mapped custom domains', function () {
    [, $location] = seatedVenue('reservation-return');
    $booking = app(CreateReservation::class)->execute($location, bookingPayload(), (string) Str::uuid());
    $url = '/api/v1/public/reservations/'.$booking['reservation']->public_id;
    $this->withToken($booking['token'])->getJson($url)->assertOk()->assertJsonPath('menu_url', $location->storefront_path)->assertJsonPath('display_settings.datetime_format', 'd.m.Y H:i');
    StorefrontDomain::create(['organization_id' => $location->organization_id, 'location_id' => $location->id, 'hostname' => 'return.zionfood.test', 'verification_status' => 'verified', 'kind' => 'custom']);
    $this->getJson('http://return.zionfood.test'.$url)->assertOk()->assertJsonPath('menu_url', '/');
});
