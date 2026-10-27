<?php

use App\Domain\Analytics\RestaurantOverview;
use App\Models\LocationStaff;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrganizationMembership;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

function overviewOrder($location, $item, string $status, int $quantity, int $value, string $created): Order
{
    $order = Order::create(['organization_id' => $location->organization_id, 'location_id' => $location->id, 'tracking_token_hash' => hash('sha256', uniqid()), 'tracking_token_encrypted' => encrypt('fixture-only-token'), 'fulfillment' => 'pickup', 'customer_name' => 'Private guest', 'customer_phone' => '0712345678', 'currency' => 'RON', 'subtotal' => $value, 'delivery_fee' => 0, 'total' => $value, 'status' => $status, 'created_at' => $created]);
    OrderItem::create(['order_id' => $order->id, 'product_public_id' => $item->public_id, 'name' => $item->name, 'quantity' => $quantity, 'unit_price' => (int) ($value / $quantity), 'tax_basis_points' => 0, 'total' => $value]);

    return $order;
}
it('aggregates actual orders and product snapshots without cancelled value or foreign tenant data', function () {
    $this->travelTo(now()->setDate(2026, 10, 27)->setTime(12, 0));
    [$owner,$location,$item] = tenantFixture('overview');
    [$foreign,$elsewhere,$other] = tenantFixture('overview-other');
    overviewOrder($location, $item, 'completed', 2, 7000, '2026-10-27 08:00:00');
    overviewOrder($location, $item, 'cancelled', 3, 9999, '2026-10-27 09:00:00');
    overviewOrder($elsewhere, $other, 'completed', 100, 999999, '2026-10-27 08:00:00');
    $data = app(RestaurantOverview::class)->build($owner, $location, 7);
    expect($data['orders']['count'])->toBe(2)->and($data['orders']['completed'])->toBe(1)->and($data['orders']['value'])->toBe(7000)->and($data['orders']['average'])->toBe(7000)->and($data['orders']['top_products'][0]->quantity)->toEqual(2)->and($data['menu']['recipes'])->toBe(1)->and(count($data['orders']['series']))->toBe(7);
    expect(json_encode($data))->not->toContain('Private guest')->not->toContain('0712345678');
    $this->actingAs($owner)->get('/app/locations/'.$location->public_id.'?days=7')->assertOk();
    $this->get('/app/locations/'.$location->public_id.'?days=9999')->assertUnprocessable();
    $this->actingAs($foreign)->get('/app/locations/'.$location->public_id)->assertForbidden();
});
it('keeps hidden feature statistics out of staff dashboard payloads', function () {
    [$owner,$location,$item] = tenantFixture('overview-staff');
    $staff = User::factory()->create();
    OrganizationMembership::create(['organization_id' => $location->organization_id, 'user_id' => $staff->id, 'role' => 'staff', 'permissions' => ['menu' => 'view', 'orders' => 'none', 'tables' => 'none', 'reservations' => 'none', 'kitchen' => 'none']]);
    LocationStaff::create(['organization_id' => $location->organization_id, 'location_id' => $location->id, 'user_id' => $staff->id]);
    $data = app(RestaurantOverview::class)->build($staff, $location, 30);
    expect($data['orders'])->toBeNull()->and($data['tables'])->toBeNull()->and($data['reservations'])->toBeNull()->and($data['menu']['recipes'])->toBe(1);
});
it('groups orders using local day boundaries across daylight saving changes', function () {
    $this->travelTo(now()->setDate(2026, 10, 27)->setTime(12, 0));
    [$owner,$location,$item] = tenantFixture('overview-dst');
    $location->update(['timezone' => 'Europe/Bucharest']);
    overviewOrder($location, $item, 'completed', 1, 1000, '2026-10-24 21:30:00'); // 00:30 local, before the clock change.
    overviewOrder($location, $item, 'completed', 1, 2000, '2026-10-25 22:30:00'); // 00:30 local, after the clock change.
    $series = collect(app(RestaurantOverview::class)->build($owner, $location, 7)['orders']['series'])->keyBy('day');
    expect((int) $series['2026-10-25']->orders)->toBe(1)->and((int) $series['2026-10-26']->orders)->toBe(1);
});
it('counts approximate public visits without cookies or identifiable database records and deduplicates refreshes', function () {
    Cache::flush();
    [$owner,$location] = tenantFixture('overview-visits');
    $url = '/api/v1/public/locations/'.$location->public_id.'/visit';
    $this->withHeaders(['User-Agent' => 'Test-browser'])->postJson($url)->assertNoContent();
    $this->postJson($url)->assertNoContent();
    expect((int) DB::table('menu_visit_stats')->sum('visits'))->toBe(1);
    $this->travel(31)->minutes();
    $this->postJson($url)->assertNoContent();
    expect((int) DB::table('menu_visit_stats')->sum('visits'))->toBe(2);
    $this->withHeaders(['User-Agent' => 'WhatsApp preview bot'])->postJson($url)->assertNoContent();
    expect((int) DB::table('menu_visit_stats')->sum('visits'))->toBe(2);
    $columns = DB::getSchemaBuilder()->getColumnListing('menu_visit_stats');
    expect($columns)->not->toContain('ip','user_agent','visitor_id');
    $this->postJson('/api/v1/public/locations/unknown/visit')->assertNotFound();
});
