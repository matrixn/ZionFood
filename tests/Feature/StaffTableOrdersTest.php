<?php

use App\Domain\Dining\StaffTableOrders;
use App\Domain\Dining\TableSessions;
use App\Domain\Orders\Actions\PlaceOrderAction;
use App\Domain\Orders\Actions\TransitionOrderStatus;
use App\Domain\Orders\OrderStatus;
use App\Domain\Restaurants\SyncDiningTables;
use App\Models\DiningTable;
use App\Models\LocationStaff;
use App\Models\OrganizationMembership;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;

function staffTableFixture(string $slug = 'staff-tables'): array
{
    [$owner,$location,$item] = tenantFixture($slug);
    $location->fulfillment()->update(['dine_in_enabled' => true]);
    app(SyncDiningTables::class)->execute($location, ['mode' => 'tables', 'tables' => [['code' => 'T1', 'area' => 'Salon', 'seats' => 4]]]);
    $table = DiningTable::where('location_id', $location->id)->first();
    $session = app(TableSessions::class)->open($table, $owner);
    $order = app(PlaceOrderAction::class)->execute($location, [...checkoutData($item), 'fulfillment' => 'dine_in', 'table_session_token' => Crypt::decryptString($session->token_encrypted)], (string) Str::uuid())['order'];

    return [$owner, $location, $table, $order];
}

beforeEach(fn () => $this->travelTo(now('UTC')->setTime(9, 0)));

it('totals the location day excluding cancelled and rejected and keeps older active table orders', function () {
    [$owner,$location,$table,$order] = staffTableFixture();
    $order->update(['created_at' => now()->subDays(2)]);
    foreach (['completed', 'ready', 'cancelled', 'rejected'] as $status) {
        $copy = $order->replicate(['public_id', 'tracking_token_hash']);
        $copy->tracking_token_hash = hash('sha256', Str::random(64));
        $copy->status = $status;
        $copy->created_at = now();
        $copy->save();
    }
    $summary = app(StaffTableOrders::class)->summaries($owner, $location)[$table->id];
    expect($summary)->toBe(['active_count' => 2, 'daily_total' => 14000]);
    app(TableSessions::class)->close($owner, $table);
    $this->actingAs($owner)->getJson('/app/tables/'.$table->public_id.'/orders')->assertOk()->assertJsonCount(3, 'orders')->assertJsonPath('can_serve', true)->assertJsonMissingPath('orders.0.customer_phone');
});

it('uses local midnight and handles a DST day without assuming a 24 hour day', function () {
    [$owner,$location,$table,$order] = staffTableFixture();
    $location->update(['timezone' => 'Europe/Bucharest']);
    $this->travelTo(CarbonImmutable::parse('2026-10-25T12:00:00Z'));
    $order->update(['created_at' => '2026-10-24 21:00:00']); // Midnight in Bucharest, before the fall-back.
    expect(app(StaffTableOrders::class)->summaries($owner, $location)[$table->id]['daily_total'])->toBe(7000);
    $order->update(['created_at' => '2026-10-25 21:59:59']);
    expect(app(StaffTableOrders::class)->summaries($owner, $location)[$table->id]['daily_total'])->toBe(7000);
    $order->update(['created_at' => '2026-10-25 22:00:00']);
    expect(app(StaffTableOrders::class)->summaries($owner, $location)[$table->id]['daily_total'])->toBe(0);
});

it('only serves ready scoped orders with a current version and does not grant orders access through tables', function () {
    [$owner,$location,$table,$order] = staffTableFixture();
    [$foreign] = tenantFixture('foreign-table-staff');
    $base = '/app/tables/'.$table->public_id;
    $url = $base.'/orders/'.$order->public_id.'/served';
    $this->actingAs($foreign)->getJson($base.'/orders')->assertForbidden();
    $this->actingAs($owner)->postJson($url, ['version' => 1])->assertUnprocessable();
    foreach ([OrderStatus::Accepted, OrderStatus::Preparing, OrderStatus::Ready] as $next) {
        $order = app(TransitionOrderStatus::class)->execute($owner, $order, $next, $order->version);
    }
    $this->postJson($url, ['version' => 1])->assertConflict();
    $staff = User::factory()->create();
    $permissions = ['tables' => 'view', 'orders' => 'none', 'kitchen' => 'none', 'menu' => 'none', 'reservations' => 'none'];
    $membership = OrganizationMembership::create(['organization_id' => $location->organization_id, 'user_id' => $staff->id, 'role' => 'staff', 'permissions' => $permissions]);
    LocationStaff::create(['organization_id' => $location->organization_id, 'location_id' => $location->id, 'user_id' => $staff->id]);
    $this->actingAs($staff)->getJson($base.'/orders')->assertForbidden();
    expect(app(StaffTableOrders::class)->summaries($staff, $location))->toBe([]);
    $membership->update(['permissions' => [...$permissions, 'kitchen' => 'view']]);
    $this->getJson($base.'/orders')->assertOk()->assertJsonPath('can_serve', false);
    $this->postJson($url, ['version' => $order->version])->assertForbidden();
    $membership->update(['permissions' => [...$permissions, 'kitchen' => 'manage']]);
    $this->postJson($url, ['version' => $order->version])->assertNoContent();
    expect($order->fresh()->status)->toBe(OrderStatus::Completed);
    $this->postJson($url, ['version' => $order->version])->assertConflict();
});
