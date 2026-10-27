<?php

use App\Domain\Menus\Actions\UpdateMenuItem;
use App\Domain\Orders\Actions\PlaceOrderAction;
use App\Events\OrderChanged;
use App\Jobs\DeliverOutbox;
use App\Models\LocationStaff;
use App\Models\ModifierGroup;
use App\Models\Order;
use App\Models\OrganizationMembership;
use App\Models\OrganizationSubscription;
use App\Models\OutboxMessage;
use App\Models\User;
use App\Policies\MenuItemPolicy;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia;

beforeEach(function () {
    Queue::fake();
    $this->travelTo(now()->setTime(12, 0));
});

it('renders the landing page and authentication pages', function () {
    foreach (['/', '/login', '/register', '/forgot-password'] as $path) {
        $this->get($path)->assertOk();
    }
});
it('registers users and requires verified email for onboarding', function () {
    $this->post('/register', ['name' => 'Owner', 'email' => 'new@example.test', 'password' => 'a-long-test-password', 'password_confirmation' => 'a-long-test-password'])->assertRedirect('/app');
    $this->assertAuthenticated();
    $this->get('/onboarding')->assertRedirect('/email/verify');
    expect(User::where('email', 'new@example.test')->first()->is_platform_admin)->toBeFalse();
});
it('validates onboarding and creates the complete hierarchy', function () {
    $user = User::factory()->create();
    $this->actingAs($user)->post('/onboarding', ['organization_name' => 'ACME', 'restaurant_name' => 'ACME', 'slug' => 'app', 'location_name' => 'Main', 'address' => 'Street'])->assertSessionHasErrors('slug');
    [$owner, $location] = tenantFixture();
    expect($location->restaurant->organization->memberships()->first()->user_id)->toBe($owner->id);
    expect($location->menus()->count())->toBe(1);
    expect($location->hours()->count())->toBe(7);
});
it('isolates organization orders menus locations and platform admin', function () {
    [$a] = tenantFixture('first');
    [$b, $location, $item] = tenantFixture('second');
    $result = placeFixture($location, $item);
    $this->actingAs($a);
    foreach (["/api/v1/locations/{$location->public_id}/orders", "/api/v1/locations/{$location->public_id}/menu", "/app/locations/{$location->public_id}", '/admin'] as $path) {
        $this->getJson($path)->assertForbidden();
    }
    $this->patchJson('/api/v1/orders/'.$result['order']->public_id.'/status', ['status' => 'accepted', 'version' => 1])->assertForbidden();
});
it('restricts staff to assigned locations and prevents menu writes', function () {
    [, $location, $item] = tenantFixture();
    $staff = User::factory()->create();
    OrganizationMembership::create(['organization_id' => $location->organization_id, 'user_id' => $staff->id, 'role' => 'staff']);
    $this->actingAs($staff)->getJson("/api/v1/locations/{$location->public_id}/orders")->assertForbidden();
    LocationStaff::create(['organization_id' => $location->organization_id, 'user_id' => $staff->id, 'location_id' => $location->id]);
    $this->getJson("/api/v1/locations/{$location->public_id}/orders")->assertOk();
    $this->get("/app/locations/{$location->public_id}/menu")->assertOk()->assertInertia(fn (AssertableInertia $page) => $page->where('menuWritable', false));
    expect(app(MenuItemPolicy::class)->update($staff, $item))->toBeFalse();
});
it('lets owners update their menu while rejecting foreign categories', function () {
    [$owner, $location, $item] = tenantFixture('first');
    [, , $foreign] = tenantFixture('second');
    $this->actingAs($owner)->putJson('/api/v1/items/'.$item->public_id, ['category_id' => $item->category_id, 'name' => 'New pizza', 'price' => 4200, 'is_available' => true])->assertOk();
    expect($item->fresh()->price)->toBe(4200);
    $this->putJson('/api/v1/items/'.$item->public_id, ['category_id' => $foreign->category_id, 'name' => 'Bad', 'price' => 1, 'is_available' => true])->assertUnprocessable();
});
it('places public orders and ignores client supplied prices', function () {
    [, $location, $item] = tenantFixture();
    $data = checkoutData($item);
    $data['total'] = 1;
    $data['items'][0]['price'] = 1;
    $response = $this->postJson("/api/v1/public/locations/{$location->public_id}/checkout", $data, ['Idempotency-Key' => (string) Str::uuid()])->assertCreated();
    $order = Order::where('public_id', $response->json('order_id'))->firstOrFail();
    expect($order->total)->toBe(7000);
    expect($order->items()->first()->unit_price)->toBe(3500);
    $this->assertDatabaseCount('outbox_messages', 1);
    $this->assertDatabaseCount('order_status_history', 1);
});
it('replays checkout safely and rejects key reuse for changed requests', function () {
    [, $location, $item] = tenantFixture();
    $key = (string) Str::uuid();
    $data = checkoutData($item);
    $first = app(PlaceOrderAction::class)->execute($location, $data, $key);
    $second = app(PlaceOrderAction::class)->execute($location, $data, $key);
    expect($second['tracking_token'])->toBe($first['tracking_token']);
    expect($second['replayed'])->toBeTrue();
    $this->assertDatabaseCount('orders', 1);
    $data['customer_name'] = 'Changed';
    $this->postJson("/api/v1/public/locations/{$location->public_id}/checkout", $data, ['Idempotency-Key' => $key])->assertConflict();
});
it('rejects unavailable and foreign products', function () {
    [, $location, $item] = tenantFixture('first');
    [, , $foreign] = tenantFixture('second');
    $this->postJson("/api/v1/public/locations/{$location->public_id}/checkout", checkoutData($foreign), ['Idempotency-Key' => (string) Str::uuid()])->assertUnprocessable();
    $item->update(['is_available' => false]);
    expect(fn () => placeFixture($location, $item))->toThrow(ValidationException::class);
    $this->assertDatabaseCount('orders', 0);
});
it('rejects closed paused and expired restaurants', function (string $condition) {
    [, $location, $item] = tenantFixture();
    match ($condition) {
        'closed' => $location->hours()->delete(), 'paused' => $location->update(['accepting_orders' => false]), 'expired' => OrganizationSubscription::where('organization_id', $location->organization_id)->update(['trial_ends_at' => now()->subDay()])
    };
    expect(fn () => placeFixture($location, $item))->toThrow(ValidationException::class);
})->with(['closed', 'paused', 'expired']);
it('validates variants required modifier counts and foreign modifiers', function () {
    [, $location, $item] = tenantFixture();
    $variant = $item->variants()->create(['name' => 'Large', 'price' => 5000]);
    $group = ModifierGroup::create(['organization_id' => $location->organization_id, 'name' => 'Sauce', 'min_choices' => 1, 'max_choices' => 1]);
    $option = $group->options()->create(['name' => 'Spicy', 'price' => 300]);
    $item->modifierGroups()->attach($group->id, ['organization_id' => $location->organization_id]);
    expect(fn () => placeFixture($location, $item))->toThrow(ValidationException::class);
    $data = checkoutData($item);
    $data['items'][0]['variant_id'] = $variant->id;
    $data['items'][0]['modifier_ids'] = [$option->id, $option->id];
    expect(fn () => app(PlaceOrderAction::class)->execute($location, $data, (string) Str::uuid()))->toThrow(ValidationException::class);
    $data['items'][0]['modifier_ids'] = [$option->id, 99999];
    expect(fn () => app(PlaceOrderAction::class)->execute($location, $data, (string) Str::uuid()))->toThrow(ValidationException::class);
    $data['items'][0]['modifier_ids'] = [$option->id];
    expect(app(PlaceOrderAction::class)->execute($location, $data, (string) Str::uuid())['order']->total)->toBe(10600);
});
it('validates delivery zones fees and minimums', function () {
    [, $location, $item] = tenantFixture();
    $location->fulfillment()->update(['delivery_enabled' => true]);
    $zone = $location->zones()->create(['name' => 'Central', 'postal_prefix' => '01', 'fee' => 1000, 'minimum_order' => 5000]);
    $data = [...checkoutData($item), 'fulfillment' => 'delivery', 'delivery_address' => 'Test street', 'postal_code' => '010001', 'delivery_zone_id' => $zone->id];
    expect(app(PlaceOrderAction::class)->execute($location, $data, (string) Str::uuid())['order']->total)->toBe(8000);
    $data['postal_code'] = '990001';
    expect(fn () => app(PlaceOrderAction::class)->execute($location, $data, (string) Str::uuid()))->toThrow(ValidationException::class);
});
it('protects transitions and detects stale concurrent operators', function () {
    [$owner, $location, $item] = tenantFixture();
    $order = placeFixture($location, $item)['order'];
    $this->actingAs($owner)->patchJson("/api/v1/orders/{$order->public_id}/status", ['status' => 'ready', 'version' => 1])->assertUnprocessable();
    $this->patchJson("/api/v1/orders/{$order->public_id}/status", ['status' => 'accepted', 'version' => 1])->assertOk();
    $this->patchJson("/api/v1/orders/{$order->public_id}/status", ['status' => 'rejected', 'version' => 1, 'reason' => 'Too late'])->assertConflict();
    expect($order->fresh()->version)->toBe(2);
    expect($order->history()->count())->toBe(2);
});
it('keeps historical snapshots after product changes and deletion', function () {
    [$owner, $location, $item] = tenantFixture();
    $order = placeFixture($location, $item)['order'];
    app(UpdateMenuItem::class)->execute($owner, $location->organization_id, ['category_id' => $item->category_id, 'name' => 'Renamed', 'price' => 9000], $item);
    $item->delete();
    expect($order->items()->first()->name)->toBe('Pizza');
    expect($order->items()->first()->unit_price)->toBe(3500);
});
it('protects tracking tokens and omits private customer information', function () {
    [, $location, $item] = tenantFixture();
    $a = placeFixture($location, $item);
    $b = placeFixture($location, $item);
    $this->withToken($a['tracking_token'])->getJson('/api/v1/public/orders/'.$a['order']->public_id)->assertOk()->assertJsonMissingPath('customer_phone');
    $this->withToken($b['tracking_token'])->getJson('/api/v1/public/orders/'.$a['order']->public_id)->assertNotFound();
});
it('recovers authoritative active kitchen state', function () {
    [$owner, $location, $item] = tenantFixture();
    $order = placeFixture($location, $item)['order'];
    $this->actingAs($owner)->getJson("/api/v1/locations/{$location->public_id}/orders?active=1")->assertOk()->assertJsonPath('data.0.public_id', $order->public_id);
});
it('retries failed outbox deliveries and safely skips delivered messages', function () {
    [, $location, $item] = tenantFixture();
    placeFixture($location, $item);
    $message = OutboxMessage::firstOrFail();
    Event::listen(OrderChanged::class, fn () => throw new RuntimeException('Transport failure'));
    expect(fn () => (new DeliverOutbox($message->id))->handle())->toThrow(RuntimeException::class);
    expect($message->fresh()->attempts)->toBe(1);
    expect($message->fresh()->delivered_at)->toBeNull();
    Event::fake([OrderChanged::class]);
    (new DeliverOutbox($message->id))->handle();
    (new DeliverOutbox($message->id))->handle();
    Event::assertDispatchedTimes(OrderChanged::class, 1);
    expect($message->fresh()->attempts)->toBe(2);
    expect($message->fresh()->delivered_at)->not->toBeNull();
});
it('does not dispatch or retain an event from a rolled back checkout', function () {
    [, $location, $item] = tenantFixture();
    DB::beginTransaction();
    placeFixture($location, $item);
    Queue::assertNothingPushed();
    DB::rollBack();
    $this->assertDatabaseCount('orders', 0);
    $this->assertDatabaseCount('outbox_messages', 0);
});
it('rejects unknown hosts and central routes on storefront hosts', function () {
    [, $location] = tenantFixture();
    $this->get('http://attacker.test/login')->assertNotFound();
    $this->get('http://bistro.zionfood.ro/login')->assertNotFound();
    $this->get('http://bistro.zionfood.ro/')->assertOk()->assertInertia(fn ($page) => $page->component('storefront/Menu'));
});
it('requires token abilities without bypassing tenant authorization', function () {
    [$owner, $location] = tenantFixture('first');
    [, $foreign] = tenantFixture('second');
    $token = $owner->createToken('Read menu', ['menu:read'])->plainTextToken;
    $this->withToken($token)->getJson("/api/v1/locations/{$location->public_id}/menu")->assertOk();
    $this->withToken($token)->getJson("/api/v1/locations/{$location->public_id}/orders")->assertForbidden();
    $this->withToken($token)->getJson("/api/v1/locations/{$foreign->public_id}/menu")->assertForbidden();
});
