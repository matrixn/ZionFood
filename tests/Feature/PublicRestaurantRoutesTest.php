<?php

use App\Domain\Dining\TableSessions;
use App\Models\DiningTable;
use App\Models\RestaurantLocation;
use App\Models\StorefrontDomain;
use Illuminate\Support\Facades\Crypt;
use Inertia\Testing\AssertableInertia as Assert;

it('uses the restaurant slug and redirects legacy menu links without breaking mapped host isolation', function () {
    [, $location] = tenantFixture('nice-menu');
    [, $foreign] = tenantFixture('other-menu');
    $this->get('/store/'.$location->public_id)->assertRedirect('/restaurant/nice-menu')->assertStatus(301);
    $this->get('/restaurant/nice-menu')->assertOk()->assertInertia(fn (Assert $page) => $page->component('storefront/Menu')->where('location.public_id', $location->public_id));
    $domain = StorefrontDomain::where('location_id', $location->id)->firstOrFail();
    $domain->update(['verification_status' => 'verified']);
    $this->get('http://'.$domain->hostname.'/restaurant/other-menu')->assertNotFound();
    $this->get('http://'.$domain->hostname.'/restaurant/nice-menu/'.$foreign->public_id)->assertNotFound();
    $this->get('http://'.$domain->hostname.'/restaurant/nice-menu')->assertOk();
});

it('offers an explicit branch choice and scopes every branch to the restaurant', function () {
    [, $location] = tenantFixture('many-places');
    [, $foreign] = tenantFixture('unrelated-place');
    $second = RestaurantLocation::create(['organization_id' => $location->organization_id, 'restaurant_id' => $location->restaurant_id, 'name' => 'Second branch', 'address' => 'Street 2', 'timezone' => 'Europe/Bucharest', 'currency' => 'RON']);
    $this->get('/restaurant/many-places')->assertInertia(fn (Assert $page) => $page->component('storefront/Locations')->has('locations', 2)->missing('locations.0.organization_id'));
    $this->get('/restaurant/many-places/'.$second->public_id)->assertInertia(fn (Assert $page) => $page->where('location.public_id', $second->public_id));
    $this->get('/restaurant/many-places/'.$foreign->public_id)->assertNotFound();
    $this->get('/store/'.$location->public_id)->assertRedirect('/restaurant/many-places/'.$location->public_id);
});

it('explains disabled or paused table menus and preserves QR rotation and reopen semantics', function () {
    [$owner, $location] = seatedVenue('table-explanation');
    $table = DiningTable::where('location_id', $location->id)->firstOrFail();
    $location->fulfillment()->update(['dine_in_enabled' => false]);
    $this->get('/table/'.$table->public_id)->assertOk()->assertInertia(fn (Assert $page) => $page->component('storefront/TableUnavailable')->where('reason', 'service_disabled'));
    $this->postJson('/api/v1/public/tables/'.$table->public_id.'/connect', ['qr_token' => Crypt::decryptString($table->qr_token_encrypted)])->assertUnprocessable();
    app(TableSessions::class)->close($owner, $table);
    app(TableSessions::class)->rotate($owner, $table);
    expect($table->fresh()->ordering_paused)->toBeTrue()->and($location->fulfillment->fresh()->dine_in_enabled)->toBeFalse();
    $location->fulfillment()->update(['dine_in_enabled' => true]);
    $this->get('/table/'.$table->public_id)->assertInertia(fn (Assert $page) => $page->where('reason', 'table_closed'));
    app(TableSessions::class)->open($table->fresh(), $owner);
    $this->get('/table/'.$table->public_id)->assertInertia(fn (Assert $page) => $page->component('storefront/Menu'));
    $this->get('/table/not-a-table')->assertNotFound();
});
