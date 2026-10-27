<?php

use App\Domain\Dining\OrderingWindow;
use App\Domain\Dining\TableSessions;
use App\Domain\Tenancy\StaffPermissions;
use App\Models\DiningTable;
use Illuminate\Support\Facades\Crypt;
use Inertia\Testing\AssertableInertia as Assert;

it('atomically opens and closes one room or all active tables without rotating permanent QR credentials', function () {
    [$owner, $location] = seatedVenue('bulk-tables');
    $tables = DiningTable::where('location_id', $location->id)->orderBy('id')->get();
    $tables[0]->update(['area' => 'Sala principală']);
    $tables[1]->update(['area' => 'Terasă']);
    $hashes = $tables->pluck('qr_token_hash', 'id')->all();
    $url = '/app/locations/'.$location->public_id.'/tables/bulk';
    $this->actingAs($owner)->post($url, ['scope' => 'all', 'operation' => 'open'])->assertSessionHasNoErrors();
    $this->post($url, ['scope' => 'area', 'area' => 'Sala principală', 'operation' => 'close'])->assertSessionHasNoErrors();
    expect($tables[0]->fresh()->ordering_paused)->toBeTrue()->and($tables[1]->fresh()->ordering_paused)->toBeFalse();
    $this->post($url, ['scope' => 'all', 'operation' => 'close'])->assertSessionHasNoErrors();
    expect($tables[1]->fresh()->ordering_paused)->toBeTrue();
    $this->post($url, ['scope' => 'all', 'operation' => 'open'])->assertSessionHasNoErrors();
    expect(DiningTable::where('location_id', $location->id)->pluck('qr_token_hash', 'id')->all())->toBe($hashes);
    $this->postJson($url, ['scope' => 'area', 'operation' => 'close'])->assertUnprocessable();
    $this->postJson($url, ['scope' => 'area', 'area' => 'Does not exist', 'operation' => 'close'])->assertUnprocessable();
});

it('blocks read-only and foreign table changes and never enables a disabled global service', function () {
    [$owner, $location] = seatedVenue('bulk-access');
    [, $foreign] = seatedVenue('bulk-other');
    $staff = featureStaff($location, [...array_fill_keys(StaffPermissions::FEATURES, 'none'), 'tables' => 'view']);
    $table = DiningTable::where('location_id', $location->id)->firstOrFail();
    $this->actingAs($staff)->get('/app/locations/'.$location->public_id.'/tables')->assertOk();
    $this->postJson('/app/locations/'.$location->public_id.'/tables/bulk', ['scope' => 'all', 'operation' => 'open'])->assertForbidden();
    $this->postJson('/app/tables/'.$table->public_id.'/close')->assertForbidden();
    $this->postJson('/app/locations/'.$foreign->public_id.'/tables/bulk', ['scope' => 'all', 'operation' => 'close'])->assertForbidden();
    $location->fulfillment()->update(['dine_in_enabled' => false]);
    $this->actingAs($owner)->postJson('/app/locations/'.$location->public_id.'/tables/bulk', ['scope' => 'all', 'operation' => 'open'])->assertUnprocessable();
    expect($location->fulfillment->fresh()->dine_in_enabled)->toBeFalse();
});

it('reports the same closing window to staff and QR guests while preserving manual activation', function () {
    [$owner, $location] = seatedVenue('table-hours');
    $location->hours()->update(['opens_at' => '10:00', 'closes_at' => '23:00']);
    $location->fulfillment()->update(['dine_in_cutoff_minutes' => 30]);
    $table = DiningTable::where('location_id', $location->id)->firstOrFail();
    $this->travelTo(now('Europe/Bucharest')->setTime(1, 34));
    app(TableSessions::class)->open($table, $owner);
    expect($table->fresh()->ordering_paused)->toBeFalse();
    $this->actingAs($owner)->get('/app/locations/'.$location->public_id.'/tables')->assertInertia(fn (Assert $page) => $page->where('orderingWindow.reason', 'outside_hours')->where('orderingWindow.is_open', false)->where('orderingWindow.opens_at', '10:00'));
    $this->get('/table/'.$table->public_id)->assertOk()->assertInertia(fn (Assert $page) => $page->component('storefront/TableUnavailable')->where('reason', 'outside_hours'));
    $this->postJson('/api/v1/public/tables/'.$table->public_id.'/connect', ['qr_token' => Crypt::decryptString($table->qr_token_encrypted)])->assertUnprocessable();
    $this->travelTo(now('Europe/Bucharest')->setTime(22, 29));
    expect(app(OrderingWindow::class)->status($location->fresh())['is_open'])->toBeTrue();
    $this->travelTo(now('Europe/Bucharest')->setTime(22, 30));
    $this->get('/table/'.$table->public_id)->assertInertia(fn (Assert $page) => $page->where('reason', 'cutoff')->where('orderingWindow.cutoff_at', '22:30'));
});
