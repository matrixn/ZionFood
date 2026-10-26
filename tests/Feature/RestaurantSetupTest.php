<?php

use App\Models\OrganizationMembership;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

function setupBasics(): array
{
    return ['revision' => 0, 'restaurant_name' => 'Bistro Nou', 'name' => 'Centru', 'address' => 'Strada Test 1', 'city' => 'Râmnicu Vâlcea', 'country' => 'RO', 'phone' => '0712345678', 'timezone' => 'Europe/Bucharest'];
}

it('restricts all setup pages and writes to tenant managers and owners', function () {
    [$owner, $a] = tenantFixture('setup-a');
    [$other, $b] = tenantFixture('setup-b');
    $this->get('/app/locations/'.$a->public_id.'/setup')->assertRedirect('/login');
    $this->actingAs($other)->get('/app/locations/'.$a->public_id.'/setup')->assertForbidden();
    $this->put('/app/locations/'.$a->public_id.'/setup/basics', setupBasics())->assertForbidden();
    $staff = User::factory()->create();
    OrganizationMembership::create(['organization_id' => $a->organization_id, 'user_id' => $staff->id, 'role' => 'staff']);
    $this->actingAs($staff)->get('/app/locations/'.$a->public_id.'/setup')->assertForbidden();
    $this->actingAs($owner)->get('/app/locations/'.$a->public_id.'/setup')->assertOk();
});

it('saves location details without exposing private setup data publicly', function () {
    [$owner, $location] = tenantFixture('setup-profile');
    $this->actingAs($owner)->from('/app/locations/'.$location->public_id.'/setup')->put('/app/locations/'.$location->public_id.'/setup/basics', setupBasics())->assertRedirect()->assertSessionHasNoErrors();
    $location->refresh();
    expect($location->name)->toBe('Centru')->and($location->restaurant->name)->toBe('Bistro Nou')->and($location->setup_revision)->toBe(1)->and($location->setup_profile['basics']['city'])->toBe('Râmnicu Vâlcea');
    $this->getJson('/api/v1/public/locations/'.$location->public_id.'/menu')->assertOk()->assertJsonMissingPath('location.setup_profile');
    expect($location->toArray())->not->toHaveKey('setup_profile');
});

it('rejects stale writes and leaves the successful changes intact', function () {
    [$owner, $location] = tenantFixture('setup-revision');
    $this->actingAs($owner)->put('/app/locations/'.$location->public_id.'/setup/basics', setupBasics())->assertSessionHasNoErrors();
    $this->put('/app/locations/'.$location->public_id.'/setup/basics', [...setupBasics(), 'name' => 'Stale'])->assertSessionHasErrors('revision');
    expect($location->fresh()->name)->toBe('Centru');
});

it('computes seating from tables and rejects duplicates or hidden fields', function () {
    [$owner, $location] = tenantFixture('setup-tables');
    $url = '/app/locations/'.$location->public_id.'/setup/seating';
    $data = ['revision' => 0, 'mode' => 'tables', 'capacity' => 0, 'tables' => [['code' => 'T1', 'area' => 'Terasă', 'seats' => 4], ['code' => 'T2', 'area' => 'Sală', 'seats' => 6]]];
    $this->actingAs($owner)->put($url, $data)->assertSessionHasNoErrors();
    expect($location->fresh()->setup_profile['seating']['capacity'])->toBe(10);
    $data['revision'] = 1;
    $data['tables'][1]['code'] = 't1';
    $this->put($url, $data)->assertSessionHasErrors('tables.1.code');
    $data['tables'][1]['code'] = 'T2';
    $data['tables'][1]['organization_id'] = 99;
    $this->put($url, $data)->assertSessionHasErrors('tables.1');
});

it('saves reservation preferences only within seating capacity and keeps payments unavailable', function () {
    [$owner, $location] = tenantFixture('setup-reservation');
    $this->actingAs($owner)->put('/app/locations/'.$location->public_id.'/setup/seating', ['revision' => 0, 'mode' => 'capacity', 'capacity' => 10, 'tables' => []])->assertSessionHasNoErrors();
    $data = ['revision' => 1, 'planned' => true, 'confirmation' => 'manual', 'duration_minutes' => 90, 'interval_minutes' => 30, 'minimum_notice_minutes' => 60, 'advance_days' => 30, 'min_party_size' => 1, 'max_party_size' => 12, 'cancellation_hours' => 24];
    $url = '/app/locations/'.$location->public_id.'/setup/reservations';
    $this->put($url, $data)->assertSessionHasErrors('max_party_size');
    $this->put($url, [...$data, 'max_party_size' => 8])->assertSessionHasNoErrors();
    $this->put('/app/locations/'.$location->public_id.'/setup/seating', ['revision' => 2, 'mode' => 'capacity', 'capacity' => 4, 'tables' => []])->assertSessionHasErrors('capacity');
    $this->get('/app/locations/'.$location->public_id.'/setup/payments')->assertInertia(fn (Assert $page) => $page->component('restaurant/Setup')->where('step', 'payments'));
    $this->put('/app/locations/'.$location->public_id.'/setup/payments', ['revision' => 2, 'provider' => 'stripe'])->assertSessionHasErrors();
});

it('validates coordinates and does not accept unsupported timezones or unsafe URLs', function () {
    [$owner, $location] = tenantFixture('setup-validation');
    $this->actingAs($owner)->put('/app/locations/'.$location->public_id.'/setup/basics', [...setupBasics(), 'latitude' => 200, 'timezone' => 'Invalid/Zone', 'website' => 'javascript:alert(1)'])->assertSessionHasErrors(['latitude', 'timezone', 'website']);
});

it('keeps catering optional and independently configurable for each restaurant location', function () {
    [$owner, $location] = tenantFixture('setup-catering');
    [, $other] = tenantFixture('setup-catering-other');
    $data = ['revision' => 0, 'pickup_enabled' => true, 'delivery_enabled' => false, 'dine_in_planned' => false, 'scheduled_orders_planned' => false];
    $url = '/app/locations/'.$location->public_id.'/setup/services';
    $this->actingAs($owner)->put($url, $data)->assertSessionHasNoErrors();
    expect($location->fresh()->setup_profile['services']['catering_planned'])->toBeFalse();
    $this->put($url, [...$data, 'revision' => 1, 'catering_planned' => true])->assertSessionHasNoErrors();
    expect($location->fresh()->setup_profile['services']['catering_planned'])->toBeTrue()->and($other->fresh()->setup_profile)->toBeNull();
    $this->put($url, [...$data, 'revision' => 2, 'catering_planned' => false])->assertSessionHasNoErrors();
    expect($location->fresh()->setup_profile['services']['catering_planned'])->toBeFalse();
});

it('gates publication on actual menu hours contact and enabled delivery zones', function () {
    [$owner, $location] = tenantFixture('setup-launch');
    $location->update(['accepting_orders' => false]);
    $url = '/app/locations/'.$location->public_id.'/setup/publish';
    $this->actingAs($owner)->put($url, ['revision' => 0, 'accepting_orders' => true])->assertSessionHasErrors('accepting_orders');
    $this->put('/app/locations/'.$location->public_id.'/setup/basics', setupBasics())->assertSessionHasNoErrors();
    $this->put($url, ['revision' => 1, 'accepting_orders' => true])->assertSessionHasNoErrors();
    expect($location->fresh()->accepting_orders)->toBeTrue();
    $this->put('/app/locations/'.$location->public_id.'/setup/services', ['revision' => 2, 'pickup_enabled' => true, 'delivery_enabled' => true, 'dine_in_planned' => true, 'scheduled_orders_planned' => false])->assertSessionHasNoErrors();
    $this->put($url, ['revision' => 3, 'accepting_orders' => true])->assertSessionHasErrors('accepting_orders');
    $this->put($url, ['revision' => 3, 'accepting_orders' => false])->assertSessionHasNoErrors();
    expect($location->fresh()->accepting_orders)->toBeFalse();
});
