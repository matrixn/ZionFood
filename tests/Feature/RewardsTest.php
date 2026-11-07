<?php

use App\Domain\Orders\Actions\PlaceOrderAction;
use App\Domain\Orders\Actions\TransitionOrderStatus;
use App\Domain\Orders\OrderStatus;
use App\Models\LocationStaff;
use App\Models\OrganizationMembership;
use App\Models\Promotion;
use App\Models\StorefrontDomain;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

function rewardsCampaign($item, array $overrides = []): array
{
    return [...[
        'name' => 'Coffee Club', 'mode' => 'customer_qr', 'active' => true,
        'buy_products' => [$item->public_id], 'buy_quantity' => 3,
        'reward_product' => $item->public_id, 'reward_quantity' => 2,
        'starts_on' => null, 'ends_on' => null,
    ], ...$overrides];
}

it('credits a staff-confirmed receipt once and redeems an earned reward even after pausing', function () {
    [$owner,$location,$item] = tenantFixture('rewards-counter');
    $staffUrl = '/app/locations/'.$location->public_id.'/rewards';
    $wallet = '/rewards/'.$location->public_id;
    $this->actingAs($owner)->get($staffUrl)->assertOk();
    $product = Str::lower($item->public_id);
    $id = $this->postJson($staffUrl.'/campaigns', rewardsCampaign($item, ['buy_products' => [$product], 'reward_product' => $product]))
        ->assertOk()->json('campaigns.0.public_id');
    $token = $this->postJson($wallet.'/create', [])->assertCreated()->json('token');
    $code = $this->postJson($wallet.'/code', ['token' => $token])->assertOk()->json('code');
    $purchase = ['code' => $code, 'campaign' => $id, 'quantity' => 4, 'receipt' => '11-07-42'];
    $this->postJson($staffUrl.'/counter/inspect', ['code' => $code])->assertOk()->assertJsonPath('campaigns.0.progress', 0);
    $this->postJson($staffUrl.'/counter/confirm', $purchase)->assertOk();
    $this->postJson($staffUrl.'/counter/confirm', $purchase)->assertUnprocessable()->assertJsonValidationErrors('code');
    $this->postJson($wallet.'/view', ['token' => $token])->assertOk()->assertJsonPath('campaigns.0.available', 1)->assertJsonPath('campaigns.0.progress', 1);
    $fresh = $this->postJson($wallet.'/code', ['token' => $token])->assertOk()->json('code');
    $this->postJson($staffUrl.'/counter/confirm', [...$purchase, 'code' => $fresh])->assertUnprocessable()->assertJsonValidationErrors('receipt');
    $this->postJson($staffUrl.'/campaigns/'.$id, rewardsCampaign($item, ['active' => false, 'buy_quantity' => 1]))
        ->assertOk()->assertJsonPath('campaigns.0.current', false)->assertJsonPath('campaigns.0.buy_quantity', 3);
    $this->postJson($staffUrl.'/counter/redeem', ['code' => $fresh, 'campaign' => $id])->assertOk();
    $this->postJson($staffUrl.'/counter/redeem', ['code' => $fresh, 'campaign' => $id])->assertUnprocessable();
    $this->postJson($wallet.'/view', ['token' => $token])->assertOk()->assertJsonPath('campaigns.0.available', 0)->assertJsonPath('campaigns.0.redeemed', 1);
    $this->assertDatabaseHas('reward_events', ['kind' => 'redeem', 'quantity' => 2, 'actor_id' => $owner->id]);
    $this->assertDatabaseMissing('reward_cards', ['token_hash' => $token]);
    $this->assertDatabaseMissing('reward_codes', ['code_hash' => $fresh]);
});

it('waits for staff confirmation of a venue scan and prevents capture by a second card', function () {
    [$owner,$location,$item] = tenantFixture('rewards-venue');
    $staffUrl = '/app/locations/'.$location->public_id.'/rewards';
    $wallet = '/rewards/'.$location->public_id;
    $id = $this->actingAs($owner)->postJson($staffUrl.'/campaigns', rewardsCampaign($item, ['mode' => 'venue_qr']))->assertOk()->json('campaigns.0.public_id');
    $token = $this->postJson($wallet.'/create', [])->assertCreated()->json('token');
    $other = $this->postJson($wallet.'/create', [])->assertCreated()->json('token');
    $code = $this->postJson($staffUrl.'/counter/venue', ['campaign' => $id])->assertOk()->json('code');
    $this->postJson($wallet.'/join', ['token' => $token, 'code' => $code])->assertOk()->assertJsonPath('campaigns.0.available', 0);
    $this->postJson($wallet.'/join', ['token' => $other, 'code' => $code])->assertUnprocessable();
    $pending = $this->postJson($staffUrl.'/counter/pending')->assertOk()->json('pending.0.id');
    $this->postJson($staffUrl.'/counter/confirm', ['pending' => $pending, 'campaign' => $id, 'quantity' => 3, 'receipt' => 'venue-1'])->assertOk();
    $this->postJson($wallet.'/view', ['token' => $token])->assertOk()->assertJsonPath('campaigns.0.available', 1);
    $this->postJson($wallet.'/view', ['token' => $other])->assertOk()->assertJsonPath('campaigns.0.available', 0);
    $expired = $this->postJson($staffUrl.'/counter/venue', ['campaign' => $id])->assertOk()->json('code');
    $this->travel(6)->minutes();
    $this->postJson($wallet.'/join', ['token' => $other, 'code' => $expired])->assertUnprocessable();
});

it('counts server-priced paid units for every eligible online campaign only on completed orders', function () {
    $this->travelTo(now()->setTime(10, 0));
    [$owner,$location,$item] = tenantFixture('rewards-online');
    $staffUrl = '/app/locations/'.$location->public_id.'/rewards';
    $wallet = '/rewards/'.$location->public_id;
    $this->actingAs($owner)->postJson($staffUrl.'/campaigns', rewardsCampaign($item, ['mode' => 'online', 'buy_quantity' => 2]))->assertOk();
    $this->postJson($staffUrl.'/campaigns', rewardsCampaign($item, ['mode' => 'online', 'buy_quantity' => 3, 'name' => 'Second']))->assertOk();
    $this->postJson($staffUrl.'/campaigns', rewardsCampaign($item, ['name' => 'At the counter']))->assertOk();
    $token = $this->postJson($wallet.'/create', [])->assertCreated()->json('token');
    $promotion = Promotion::create([
        'organization_id' => $location->organization_id, 'restaurant_id' => $location->restaurant_id, 'location_id' => $location->id,
        'name' => 'Pizza deal', 'kind' => 'buy_x_get_y', 'value' => 0, 'buy_quantity' => 1, 'reward_quantity' => 1,
        'fulfillments' => ['pickup'], 'product_ids' => [$item->public_id], 'active' => true, 'minimum_order' => 0,
    ]);
    $input = [...checkoutData($item), 'reward_card_token' => $token, 'items' => [['product_id' => $item->public_id, 'quantity' => 4, 'price' => 1]]];
    $checkout = app(PlaceOrderAction::class);
    $key = (string) Str::uuid();
    $order = $checkout->execute($location, $input, $key)['order'];
    expect($order->total)->toBe(7000)->and($order->promotion_snapshot['free_units'])->toBe([$item->public_id => 2]);
    expect($checkout->execute($location, $input, $key)['replayed'])->toBeTrue();
    $transition = app(TransitionOrderStatus::class);
    $cancelled = $checkout->execute($location, $input, (string) Str::uuid())['order'];
    $transition->execute($owner, $cancelled, OrderStatus::Cancelled, $cancelled->version);
    $this->assertDatabaseCount('reward_events', 0);
    foreach ([OrderStatus::Accepted, OrderStatus::Preparing, OrderStatus::Ready, OrderStatus::Completed] as $status) {
        $order = $transition->execute($owner, $order, $status, $order->version);
    }
    $this->assertDatabaseCount('reward_events', 2);
    expect(DB::table('reward_events')->pluck('quantity')->map(fn ($quantity) => (int) $quantity)->all())->toBe([2, 2]);
    $this->postJson($wallet.'/view', ['token' => $token])->assertOk()
        ->assertJsonPath('campaigns.0.progress', 0)->assertJsonPath('campaigns.1.progress', 2)->assertJsonPath('campaigns.2.available', 1);
    expect($promotion->fresh()->used_count)->toEqual(2);
});

it('enforces tenant, custom storefront, order permissions and location-local campaign dates', function () {
    $this->travelTo(now()->setDate(2026, 11, 7)->setTime(22, 30));
    [$owner,$location,$item] = tenantFixture('rewards-boundary');
    [$foreign,$other,$foreignItem] = tenantFixture('rewards-other');
    $location->update(['timezone' => 'Europe/Bucharest']);
    $staffUrl = '/app/locations/'.$location->public_id.'/rewards';
    $wallet = '/rewards/'.$location->public_id;
    $this->actingAs($foreign)->get($staffUrl)->assertForbidden();
    $this->actingAs($owner)->postJson($staffUrl.'/campaigns', rewardsCampaign($foreignItem))->assertUnprocessable();
    $this->postJson($staffUrl.'/campaigns', rewardsCampaign($item, ['starts_on' => '2026-11-09', 'ends_on' => '2026-11-08']))->assertUnprocessable();
    $this->postJson($staffUrl.'/campaigns', rewardsCampaign($item, ['starts_on' => '2026-11-08', 'ends_on' => '2026-11-08']))
        ->assertOk()->assertJsonPath('campaigns.0.current', true);
    $staff = User::factory()->create();
    $membership = OrganizationMembership::create(['organization_id' => $location->organization_id, 'user_id' => $staff->id, 'role' => 'staff', 'permissions' => ['orders' => 'view']]);
    LocationStaff::create(['organization_id' => $location->organization_id, 'location_id' => $location->id, 'user_id' => $staff->id]);
    $this->actingAs($staff)->get($staffUrl)->assertForbidden();
    $membership->update(['permissions' => ['orders' => 'manage']]);
    $this->get($staffUrl)->assertOk();
    $this->postJson($staffUrl.'/campaigns', rewardsCampaign($item))->assertForbidden();
    $token = $this->postJson($wallet.'/create', [])->assertCreated()->json('token');
    $this->postJson('/rewards/'.$other->public_id.'/view', ['token' => $token])->assertUnprocessable();
    StorefrontDomain::create(['location_id' => $location->id, 'hostname' => 'rewards.example.test', 'verification_status' => 'verified']);
    $this->get('http://rewards.example.test'.$wallet)->assertOk()->assertHeader('X-Robots-Tag', 'noindex, nofollow');
    $this->get('http://rewards.example.test/rewards/'.$other->public_id)->assertNotFound();
    $this->travel(24)->hours();
    $this->postJson($wallet.'/create', [])->assertNotFound();
});
