<?php

use App\Domain\Dining\TableSessions;
use App\Domain\Orders\Actions\GetOrderingAvailability;
use App\Domain\Orders\Actions\PlaceOrderAction;
use App\Domain\Orders\LocationSchedule;
use App\Domain\Restaurants\LocationPreferences;
use App\Models\DiningTable;
use App\Models\StorefrontDomain;
use Carbon\Carbon;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->travelTo(Carbon::parse('2026-10-29 12:00:00', 'Europe/Bucharest'));
});

it('exposes opening and closing notices before checkout with the exact local schedule boundaries', function () {
    [, $location] = tenantFixture('availability-hours');
    $location->hours()->update(['opens_at' => '10:00', 'closes_at' => '22:00']);
    $url = '/api/v1/public/locations/'.$location->public_id.'/availability';
    $this->getJson($url)->assertOk()->assertHeader('Cache-Control', 'no-store, private')->assertJsonPath('can_order', true)->assertJsonPath('schedule.is_open', true)->assertJsonPath('deadline_at', '2026-10-29T22:00:00+02:00');
    $this->travelTo(now('Europe/Bucharest')->setTime(9, 59, 59));
    $this->getJson($url)->assertJsonPath('can_order', false)->assertJsonPath('schedule.next_opens_at', '2026-10-29T10:00:00+02:00');
    $this->travel(1)->seconds();
    $this->getJson($url)->assertJsonPath('can_order', true);
    $this->travelTo(now('Europe/Bucharest')->setTime(22, 0));
    $this->getJson($url)->assertJsonPath('can_order', false)->assertJsonPath('reason', 'outside_hours')->assertJsonPath('schedule.next_opens_at', '2026-10-30T10:00:00+02:00');
    $this->get($location->storefront_path)->assertInertia(fn (Assert $page) => $page->where('orderingAvailability.can_order', false)->where('orderingAvailability.schedule.is_open', false));
});

it('blocks actual online checkout by default while closed and allows only cart preparation after opt out without hiding the closure', function () {
    [$owner, $location, $item] = tenantFixture('availability-override');
    $location->hours()->delete();
    expect($location->ordering_settings['block_unavailable_online_orders'])->toBeTrue();
    $this->postJson('/api/v1/public/locations/'.$location->public_id.'/checkout', [...checkoutData($item), 'block_unavailable_online_orders' => false], ['Idempotency-Key' => (string) Str::uuid()])->assertUnprocessable()->assertJsonValidationErrors('checkout');
    expect(fn () => placeFixture($location, $item))->toThrow(ValidationException::class);
    app(LocationPreferences::class)->save($owner, $location, [...LocationPreferences::DEFAULTS, 'revision' => 0, 'block_unavailable_online_orders' => false]);
    $location->refresh();
    expect(fn () => placeFixture($location, $item))->toThrow(ValidationException::class);
    $location->fulfillment()->update(['delivery_enabled' => true]);
    $zone = $location->zones()->create(['name' => 'Local test', 'postal_prefix' => 'AB', 'fee' => 700, 'minimum_order' => 0]);
    expect(fn () => app(PlaceOrderAction::class)->execute($location->fresh(), [...checkoutData($item), 'fulfillment' => 'delivery', 'delivery_address' => 'Test street 1', 'postal_code' => 'AB12', 'delivery_zone_id' => $zone->id], (string) Str::uuid()))->toThrow(ValidationException::class);
    $this->getJson('/api/v1/public/locations/'.$location->public_id.'/availability')->assertJsonPath('can_order', false)->assertJsonPath('can_add_to_cart', true)->assertJsonPath('schedule.is_open', false)->assertJsonPath('guard_enabled', false)->assertJsonPath('schedule.next_opens_at', null);
    app(LocationPreferences::class)->save($owner, $location, [...LocationPreferences::DEFAULTS, 'revision' => 1]);
    expect($location->fresh()->ordering_settings['block_unavailable_online_orders'])->toBeFalse();
    $location->update(['accepting_orders' => false]);
    expect(fn () => placeFixture($location, $item))->toThrow(ValidationException::class);
});

it('requires a manually open active table only when the QR service is enabled and ignores foreign or inactive tables', function () {
    [$owner, $location, $item] = seatedVenue('availability-tables');
    [, $foreign] = seatedVenue('availability-foreign');
    DiningTable::where('location_id', $location->id)->update(['ordering_paused' => true]);
    $state = app(GetOrderingAvailability::class)->execute($location->fresh());
    expect($state['reason'])->toBe('no_open_tables');
    expect(fn () => placeFixture($location, $item))->toThrow(ValidationException::class);
    $table = DiningTable::where('location_id', $location->id)->firstOrFail();
    $table->update(['is_active' => false, 'ordering_paused' => false]);
    expect(app(GetOrderingAvailability::class)->execute($location->fresh())['can_order'])->toBeFalse();
    $table->update(['is_active' => true]);
    expect(placeFixture($location, $item)['order']->fulfillment)->toBe('pickup');
    app(TableSessions::class)->close($owner, $table);
    $location->fulfillment()->update(['dine_in_enabled' => false]);
    expect(app(GetOrderingAvailability::class)->execute($location->fresh())['can_order'])->toBeTrue();
    $location->fulfillment()->update(['dine_in_enabled' => true]);
    app(LocationPreferences::class)->save($owner, $location->fresh(), [...LocationPreferences::DEFAULTS, 'revision' => 0, 'block_unavailable_online_orders' => false]);
    $state = app(GetOrderingAvailability::class)->execute($location->fresh());
    expect($state['can_order'])->toBeFalse()->and($state['can_add_to_cart'])->toBeTrue();
    expect(fn () => placeFixture($location, $item))->toThrow(ValidationException::class);
});

it('retains QR schedule cutoff and manual table closure even with the online override disabled', function () {
    [$owner, $location, $item] = seatedVenue('availability-qr');
    $location->hours()->update(['opens_at' => '10:00', 'closes_at' => '22:00']);
    $location->fulfillment()->update(['dine_in_cutoff_minutes' => 30]);
    app(LocationPreferences::class)->save($owner, $location, [...LocationPreferences::DEFAULTS, 'revision' => 0, 'block_unavailable_online_orders' => false]);
    $table = DiningTable::where('location_id', $location->id)->firstOrFail();
    $session = app(TableSessions::class)->open($table, $owner);
    $input = [...checkoutData($item), 'fulfillment' => 'dine_in', 'table_session_token' => Crypt::decryptString($session->token_encrypted)];
    $url = '/api/v1/public/tables/'.$table->public_id.'/availability';
    $this->getJson($url)->assertOk()->assertJsonPath('guard_enabled', true)->assertJsonPath('deadline_at', '2026-10-29T21:30:00+02:00');
    $this->travelTo(now('Europe/Bucharest')->setTime(21, 29, 59));
    $this->getJson($url)->assertJsonPath('can_order', true);
    $this->travel(1)->seconds();
    $this->getJson($url)->assertJsonPath('reason', 'cutoff')->assertJsonPath('can_order', false);
    expect(fn () => app(PlaceOrderAction::class)->execute($location, $input, (string) Str::uuid()))->toThrow(ValidationException::class);
    $this->get('/table/'.$table->public_id)->assertInertia(fn (Assert $page) => $page->component('storefront/TableUnavailable')->where('orderingAvailability.reason', 'cutoff'));
});

it('rechecks live state after the menu cache is populated and preserves successful idempotent checkout replay', function () {
    [, $location, $item] = tenantFixture('availability-cache');
    $data = checkoutData($item);
    $key = (string) Str::uuid();
    $first = app(PlaceOrderAction::class)->execute($location, $data, $key);
    $this->getJson('/api/v1/public/locations/'.$location->public_id.'/menu')->assertJsonPath('orderingAvailability.can_order', true);
    $location->hours()->delete();
    $this->getJson('/api/v1/public/locations/'.$location->public_id.'/menu')->assertJsonPath('orderingAvailability.can_order', false);
    $replay = app(PlaceOrderAction::class)->execute($location, $data, $key);
    expect($replay['replayed'])->toBeTrue()->and($replay['order']->id)->toBe($first['order']->id);
    expect(fn () => app(PlaceOrderAction::class)->execute($location, $data, (string) Str::uuid()))->toThrow(ValidationException::class);
});

it('scopes public availability to the mapped location and validates preference writes', function () {
    [$owner, $location] = seatedVenue('availability-scope');
    [, $foreign] = seatedVenue('availability-other');
    $table = DiningTable::where('location_id', $foreign->id)->firstOrFail();
    StorefrontDomain::create(['organization_id' => $location->organization_id, 'location_id' => $location->id, 'hostname' => 'availability.example.test', 'verification_status' => 'verified']);
    $this->getJson('https://availability.example.test/api/v1/public/locations/'.$foreign->public_id.'/availability')->assertNotFound();
    $this->getJson('https://availability.example.test/api/v1/public/tables/'.$table->public_id.'/availability')->assertNotFound();
    $this->actingAs($owner)->putJson('http://localhost/app/locations/'.$location->public_id.'/general-settings', [...LocationPreferences::DEFAULTS, 'revision' => 0, 'block_unavailable_online_orders' => 'no'])->assertUnprocessable();
    $this->putJson('http://localhost/app/locations/'.$location->public_id.'/general-settings', [...LocationPreferences::DEFAULTS, 'revision' => 0, 'block_unavailable_online_orders' => 0])->assertRedirect();
    expect($location->fresh()->ordering_settings['block_unavailable_online_orders'])->toBeFalse();
});

it('calculates the next opening with the restaurant timezone across a daylight saving boundary', function () {
    [, $location] = tenantFixture('availability-dst');
    $this->travelTo(Carbon::parse('2026-10-24T22:00:00+03:00'));
    $location->hours()->delete();
    $location->hours()->create(['weekday' => 7, 'opens_at' => '09:00', 'closes_at' => '18:00']);
    $schedule = app(LocationSchedule::class)->status($location->fresh());
    expect($schedule['is_open'])->toBeFalse()->and($schedule['next_opens_at'])->toBe('2026-10-25T09:00:00+02:00');
});
