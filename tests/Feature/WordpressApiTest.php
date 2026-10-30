<?php

use App\Domain\Integrations\Actions\ManageWordpressConnection;
use App\Domain\Integrations\CommunicationJournal;
use App\Domain\Integrations\PublicDnsResolver;
use App\Domain\Integrations\QueueWebhooks;
use App\Domain\Integrations\WordpressAccess;
use App\Domain\Orders\Actions\PlaceOrderAction;
use App\Domain\Orders\Actions\TransitionOrderStatus;
use App\Domain\Orders\ManagePromotion;
use App\Domain\Orders\OrderStatus;
use App\Domain\Orders\ReleaseScheduledOrders;
use App\Domain\Orders\ResolveFulfillmentTime;
use App\Domain\Reservations\CreateReservation;
use App\Domain\Restaurants\LocationPreferences;
use App\Domain\Tenancy\StaffPermissions;
use App\Jobs\DeliverWebhook;
use App\Models\CommunicationLog;
use App\Models\LocationStaff;
use App\Models\Order;
use App\Models\OrganizationMembership;
use App\Models\OutboxMessage;
use App\Models\Restaurant;
use App\Models\User;
use App\Models\WebhookDelivery;
use App\Models\WebhookEndpoint;
use App\Models\WordpressConnection;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpKernel\Exception\HttpException;

beforeEach(function () {
    Queue::fake();
    Notification::fake();
    Http::preventStrayRequests();
    $this->travelTo(CarbonImmutable::parse('2026-11-02T10:00:00Z'));
});

function wpConnection($owner, $l, array $abilities = []): array
{
    return app(ManageWordpressConnection::class)->create($owner, $l, ['name' => 'Restaurant WP', 'site_url' => 'https://restaurant.example', 'location_ids' => [$l->id], 'abilities' => $abilities ?: WordpressAccess::ABILITIES, 'expires_in_days' => 90]);
}

function wpUrl($l, string $path = 'menu'): string
{
    return '/api/v1/wordpress/locations/'.$l->public_id.'/'.$path;
}
function promoInput(array $extra = []): array
{
    return ['name' => 'Discount', 'code' => 'WELCOME', 'kind' => 'percent', 'value' => 1000, 'minimum_order' => 0, 'max_discount' => null, 'fulfillments' => ['pickup', 'delivery', 'dine_in'], 'product_ids' => null, 'buy_quantity' => 1, 'reward_quantity' => 1, 'starts_at' => null, 'ends_at' => null, 'usage_limit' => null, 'active' => true, ...$extra];
}
function scheduling($l, array $extra = []): void
{
    $l->update(['preferences' => ['scheduled_orders_enabled' => true, 'reservation_preorders_enabled' => true, ...$extra]]);
}

it('issues restaurant scoped secrets once and protects browser key lifecycle from bearer tokens', function () {
    [$owner,$l] = tenantFixture();
    $payload = ['name' => 'WordPress', 'site_url' => 'https://restaurant.example', 'location_ids' => [$l->id], 'abilities' => ['storefront:read'], 'expires_in_days' => 30];
    $base = '/app/locations/'.$l->public_id.'/wordpress-connections';
    $issued = $this->actingAs($owner)->postJson($base, $payload)->assertCreated()->json();
    expect($issued['token'])->toContain('|')->and(strlen($issued['webhook_secret']))->toBe(64);
    $this->getJson($base)->assertOk()->assertJsonMissingPath('connections.0.webhook_secret')->assertJsonMissingPath('connections.0.token');
    expect(DB::table('wordpress_connections')->value('webhook_secret'))->not->toBe($issued['webhook_secret']);
    $this->app['auth']->forgetGuards();
    $this->withToken($issued['token'])->postJson($base, $payload)->assertUnauthorized();
});

it('rejects WordPress calls authenticated only by a browser session or a generic token', function () {
    [$owner,$l] = tenantFixture();
    $this->actingAs($owner)->getJson(wpUrl($l))->assertUnauthorized();
    $generic = $owner->createToken('Generic', ['*'])->plainTextToken;
    $this->app['auth']->forgetGuards();
    $this->withToken($generic)->getJson(wpUrl($l))->assertUnauthorized();
});

it('restricts scopes across restaurants in the same organization and legacy API routes', function () {
    [$owner,$l,$item] = tenantFixture();
    $otherRestaurant = Restaurant::create(['organization_id' => $l->organization_id, 'name' => 'Other', 'slug' => 'other-restaurant']);
    $foreign = $l->replicate();
    $foreign->public_id = (string) Str::ulid();
    $foreign->restaurant_id = $otherRestaurant->id;
    $foreign->save();
    $issued = wpConnection($owner, $l, ['storefront:read', 'orders:read']);
    $this->withToken($issued['token'])->getJson(wpUrl($l))->assertOk()->assertHeader('X-ZionFood-Request-ID');
    $this->getJson(wpUrl($foreign))->assertNotFound();
    $this->getJson('/api/v1/locations/'.$foreign->public_id.'/orders')->assertForbidden();
    $this->getJson(wpUrl($l, 'orders'))->assertOk();
    $this->postJson(wpUrl($l, 'checkout'), checkoutData($item), ['Idempotency-Key' => (string) Str::uuid()])->assertForbidden();
    expect(CommunicationLog::where('http_status', 403)->exists())->toBeTrue();
});

it('rejects foreign allowed locations when creating a key', function () {
    [$owner,$l] = tenantFixture('one');
    [, $foreign] = tenantFixture('two');
    $this->actingAs($owner)->postJson('/app/locations/'.$l->public_id.'/wordpress-connections', ['name' => 'WP', 'site_url' => 'https://example.org', 'location_ids' => [$foreign->id], 'abilities' => ['storefront:read'], 'expires_in_days' => 90])->assertUnprocessable();
});

it('rotates with a bounded grace period and revokes both keys and callbacks', function () {
    [$owner,$l] = tenantFixture();
    $issued = wpConnection($owner, $l);
    $c = WordpressConnection::firstOrFail();
    $token = PersonalAccessToken::findToken($issued['token']);
    $rotated = app(ManageWordpressConnection::class)->rotate($owner, $c);
    expect($token->fresh()->expires_at->equalTo(now()->addMinutes(15)))->toBeTrue();
    $this->withToken($issued['token'])->getJson(wpUrl($l))->assertOk();
    $this->travel(16)->minutes();
    $this->app['auth']->forgetGuards();
    $this->withToken($issued['token'])->getJson(wpUrl($l))->assertUnauthorized();
    $this->app['auth']->forgetGuards();
    $this->withToken($rotated['token'])->getJson(wpUrl($l))->assertOk();
    app(ManageWordpressConnection::class)->revoke($owner, $c);
    $this->app['auth']->forgetGuards();
    $this->withToken($rotated['token'])->getJson(wpUrl($l))->assertUnauthorized();
});

it('immediately denies a key when its issuer loses restaurant membership', function () {
    [$owner,$l] = tenantFixture();
    $issued = wpConnection($owner, $l);
    OrganizationMembership::where('organization_id', $l->organization_id)->where('user_id', $owner->id)->delete();
    $this->withToken($issued['token'])->getJson(wpUrl($l))->assertForbidden();
});

it('replays checkout without duplicating orders or promotion redemption and encrypts response receipts', function () {
    [$owner,$l,$item] = tenantFixture();
    $issued = wpConnection($owner, $l);
    $promo = app(ManagePromotion::class)->execute($owner, $l, promoInput());
    $data = [...checkoutData($item), 'promotion_code' => 'welcome', 'total' => 1];
    $key = (string) Str::uuid();
    $first = $this->withToken($issued['token'])->postJson(wpUrl($l, 'checkout'), $data, ['Idempotency-Key' => $key])->assertCreated()->assertJsonPath('total', 6300)->json();
    $this->postJson(wpUrl($l, 'checkout'), $data, ['Idempotency-Key' => $key])->assertOk()->assertHeader('Idempotency-Replayed', 'true')->assertJsonPath('order_id', $first['order_id']);
    expect(Order::count())->toBe(1)->and($promo->fresh()->used_count)->toBe(1);
    expect(DB::table('wordpress_requests')->value('response'))->not->toContain($first['tracking_token']);
    $this->postJson(wpUrl($l, 'checkout'), [...$data, 'notes' => 'changed'], ['Idempotency-Key' => $key])->assertConflict();
    $this->getJson(wpUrl($l, 'orders/'.$first['order_id']))->assertOk()->assertJsonPath('data.total', 6300);
    $this->postJson(wpUrl($l, 'orders/'.$first['order_id'].'/track'), ['tracking_token' => $first['tracking_token']])->assertOk()->assertJsonMissingPath('customer_phone');
    expect(json_encode(CommunicationLog::all()))->not->toContain($issued['token'])->not->toContain($data['customer_phone'])->not->toContain($first['tracking_token']);
});

it('namespaces idempotency by connection and blocks reuse after receipt retention', function () {
    [$owner,$l,$item] = tenantFixture();
    $a = wpConnection($owner, $l);
    $b = wpConnection($owner, $l);
    $key = (string) Str::uuid();
    $this->withToken($a['token'])->postJson(wpUrl($l, 'checkout'), checkoutData($item), ['Idempotency-Key' => $key])->assertCreated();
    $this->app['auth']->forgetGuards();
    $this->withToken($b['token'])->postJson(wpUrl($l, 'checkout'), checkoutData($item), ['Idempotency-Key' => $key])->assertCreated();
    DB::table('wordpress_requests')->update(['expires_at' => now()->subMinute()]);
    $this->postJson(wpUrl($l, 'checkout'), checkoutData($item), ['Idempotency-Key' => $key])->assertConflict();
    expect(Order::count())->toBe(2);
});

it('does not retain failed mutations and allows correction with the same key', function () {
    [$owner,$l,$item] = tenantFixture();
    $issued = wpConnection($owner, $l);
    $key = (string) Str::uuid();
    $this->withToken($issued['token'])->postJson(wpUrl($l, 'checkout'), [], ['Idempotency-Key' => $key])->assertUnprocessable();
    expect(DB::table('wordpress_requests')->count())->toBe(0);
    $this->postJson(wpUrl($l, 'checkout'), checkoutData($item), ['Idempotency-Key' => $key])->assertCreated();
});

it('requires HTTPS outside local test runtimes', function () {
    [$owner,$l] = tenantFixture();
    $issued = wpConnection($owner, $l);
    $this->app->instance('env', 'production');
    $this->withToken($issued['token'])->getJson(wpUrl($l))->assertStatus(400);
});

it('keeps tenant logs hidden until enabled and isolates them from other restaurants', function () {
    [$owner,$l] = tenantFixture('one');
    [, $foreign] = tenantFixture('two');
    foreach ([$l, $foreign] as $loc) {
        app(CommunicationJournal::class)->write(['request_id' => (string) Str::uuid(), 'organization_id' => $loc->organization_id, 'restaurant_id' => $loc->restaurant_id, 'location_id' => $loc->id, 'direction' => 'inbound', 'operation' => 'bootstrap', 'method' => 'GET', 'http_status' => 200, 'outcome' => 'success']);
    }
    $base = '/app/locations/'.$l->public_id.'/communication-logs/data';
    $this->actingAs($owner)->getJson($base)->assertForbidden();
    $l->restaurant->update(['communication_logs_enabled' => true]);
    $this->getJson($base.'?restaurant='.$foreign->restaurant->public_id)->assertOk()->assertJsonCount(1, 'logs.data')->assertJsonPath('logs.data.0.restaurant_id', $l->restaurant_id);
    $this->getJson('/admin/communication-logs/data')->assertForbidden();
    $owner->forceFill(['is_platform_admin' => true])->save();
    $this->getJson('/admin/communication-logs/data?restaurant='.$foreign->restaurant->public_id.'&direction=inbound&status=200')->assertOk()->assertJsonCount(1, 'logs.data')->assertJsonPath('logs.data.0.restaurant_id', $foreign->restaurant_id);
});

it('preserves commerce preferences while saving display settings', function () {
    [$owner,$l] = tenantFixture();
    scheduling($l);
    app(LocationPreferences::class)->save($owner, $l, [...LocationPreferences::DEFAULTS, 'revision' => 0, 'communication_logs_enabled' => true]);
    expect($l->fresh()->commerce_settings['scheduled_orders_enabled'])->toBeTrue()->and($l->restaurant->fresh()->communication_logs_enabled)->toBeTrue();
});

it('creates future orders while closed now and releases them into the kitchen only when due', function () {
    [$owner,$l,$item] = tenantFixture();
    scheduling($l);
    $l->hours()->update(['opens_at' => '13:00', 'closes_at' => '18:00']);
    $data = [...checkoutData($item), 'scheduled_for' => '2026-11-03T14:00:00+02:00'];
    $order = app(PlaceOrderAction::class)->execute($l, $data, (string) Str::uuid())['order'];
    expect($order->scheduled_for->format('H:i'))->toBe('12:00');
    $this->actingAs($owner)->getJson('/api/v1/locations/'.$l->public_id.'/kitchen-orders')->assertOk()->assertJsonCount(0, 'data');
    app(TransitionOrderStatus::class)->execute($owner, $order, OrderStatus::Accepted, 1);
    expect(fn () => app(TransitionOrderStatus::class)->execute($owner, $order->fresh(), OrderStatus::Preparing, 2))->toThrow(ValidationException::class);
    $this->travelTo($order->preparation_at);
    expect(app(ReleaseScheduledOrders::class)->execute())->toBe(1)->and(app(ReleaseScheduledOrders::class)->execute())->toBe(0);
    $this->getJson('/api/v1/locations/'.$l->public_id.'/kitchen-orders')->assertOk()->assertJsonCount(1, 'data');
    expect($order->fresh()->version)->toBe(3);
});

it('enforces scheduling opt in notice horizon service hours and capacity', function () {
    [, $l,$item] = tenantFixture();
    $data = [...checkoutData($item), 'scheduled_for' => '2026-11-03T14:00:00+02:00'];
    expect(fn () => app(PlaceOrderAction::class)->execute($l, $data, (string) Str::uuid()))->toThrow(ValidationException::class);
    scheduling($l, ['scheduled_orders_per_slot' => 1]);
    foreach (['2026-11-02T12:15:00+02:00', '2026-12-01T14:00:00+02:00', '2026-11-03T14:07:00+02:00'] as $time) {
        expect(fn () => app(PlaceOrderAction::class)->execute($l, [...$data, 'scheduled_for' => $time], (string) Str::uuid()))->toThrow(ValidationException::class);
    }
    app(PlaceOrderAction::class)->execute($l, $data, (string) Str::uuid());
    expect(fn () => app(PlaceOrderAction::class)->execute($l, $data, (string) Str::uuid()))->toThrow(ValidationException::class);
    $slots = app(ResolveFulfillmentTime::class)->slots($l, '2026-11-03', 'pickup');
    expect($slots)->not->toContain($data['scheduled_for']);
    $l->update(['accepting_orders' => false]);
    expect(app(ResolveFulfillmentTime::class)->slots($l, '2026-11-03', 'pickup'))->toBe([]);
});

it('requires a confirmed matching reservation proof and permits only one active preorder', function () {
    [$owner,$l,$item] = tenantFixture();
    scheduling($l);
    $l->update(['setup_profile' => ['seating' => ['mode' => 'capacity', 'capacity' => 20], 'reservations' => ['planned' => true, 'confirmation' => 'automatic', 'duration_minutes' => 90, 'interval_minutes' => 30, 'minimum_notice_minutes' => 60, 'advance_days' => 30, 'min_party_size' => 1, 'max_party_size' => 10, 'cancellation_hours' => 0]]]);
    $l->fulfillment()->update(['reservations_enabled' => true, 'dine_in_enabled' => true]);
    $b = app(CreateReservation::class)->execute($l, ['guest_name' => 'Guest', 'guest_phone' => '0712345678', 'party_size' => 2, 'starts_at' => '2026-11-03T14:00'], (string) Str::uuid());
    $data = [...checkoutData($item), 'fulfillment' => 'dine_in', 'reservation_id' => $b['reservation']->public_id, 'reservation_token' => $b['token']];
    expect(fn () => app(PlaceOrderAction::class)->execute($l, [...$data, 'reservation_token' => str_repeat('x', 64)], (string) Str::uuid()))->toThrow(ValidationException::class);
    app(ManagePromotion::class)->execute($owner, $l, promoInput(['code' => null, 'value' => 5000, 'starts_at' => '2026-11-03T00:00:00Z', 'ends_at' => '2026-11-04T00:00:00Z']));
    $issued = wpConnection($owner, $l);
    $this->withToken($issued['token'])->postJson(wpUrl($l, 'quote'), $data)->assertOk()->assertJsonPath('total', 3500);
    $o = app(PlaceOrderAction::class)->execute($l, $data, (string) Str::uuid())['order'];
    expect($o->total)->toBe(3500);
    expect($o->reservation_id)->toBe($b['reservation']->id)->and($o->dining_session_id)->toBeNull();
    expect(fn () => app(PlaceOrderAction::class)->execute($l, $data, (string) Str::uuid()))->toThrow(ValidationException::class);
});

it('calculates each promotion type using server prices', function (array $changes, string $fulfillment, int $expected) {
    [$owner,$l,$item] = tenantFixture();
    app(ManagePromotion::class)->execute($owner, $l, promoInput($changes));
    $input = [...checkoutData($item), 'fulfillment' => $fulfillment, 'promotion_code' => 'WELCOME', 'delivery_address' => 'Street', 'postal_code' => '010000'];
    if ($fulfillment === 'delivery') {
        $l->fulfillment()->update(['delivery_enabled' => true]);
    }
    if ($fulfillment === 'delivery') {
        $l->zones()->create(['organization_id' => $l->organization_id, 'name' => 'City', 'postal_prefix' => '01', 'fee' => 1000, 'minimum_order' => 0, 'is_active' => true]);
    }
    if ($fulfillment === 'delivery') {
        $input['delivery_zone_id'] = $l->zones()->first()->id;
    }
    $o = app(PlaceOrderAction::class)->execute($l, $input, (string) Str::uuid())['order'];
    expect($o->total)->toBe($expected)->and($o->promotion_snapshot['name'])->toBe('Discount');
})->with([
    'percent' => [['kind' => 'percent', 'value' => 1000], 'pickup', 6300],
    'fixed' => [['kind' => 'fixed', 'value' => 1500], 'pickup', 5500],
    'free delivery' => [['kind' => 'free_delivery', 'value' => 0], 'delivery', 7000],
    'buy one get one' => [['kind' => 'buy_x_get_y', 'value' => 0], 'pickup', 3500],
    'capped percent' => [['kind' => 'percent', 'value' => 5000, 'max_discount' => 1000], 'pickup', 6000],
]);

it('selects the best automatic promotion and preserves order snapshots after editing', function () {
    [$owner,$l,$item] = tenantFixture();
    app(ManagePromotion::class)->execute($owner, $l, promoInput(['code' => null, 'value' => 1000]));
    $p = app(ManagePromotion::class)->execute($owner, $l, promoInput(['name' => 'Best', 'code' => null, 'kind' => 'fixed', 'value' => 2000]));
    $o = app(PlaceOrderAction::class)->execute($l, checkoutData($item), (string) Str::uuid())['order'];
    $p->update(['active' => false, 'name' => 'Changed', 'value' => 1]);
    expect($o->fresh()->total)->toBe(5000)->and($o->fresh()->promotion_snapshot['name'])->toBe('Best');
});

it('rejects exhausted or invalid coupons and does not consume a use for quotes', function () {
    [$owner,$l,$item] = tenantFixture();
    $issued = wpConnection($owner, $l);
    $p = app(ManagePromotion::class)->execute($owner, $l, promoInput(['usage_limit' => 1]));
    $input = [...checkoutData($item), 'promotion_code' => 'WELCOME'];
    $this->withToken($issued['token'])->postJson(wpUrl($l, 'quote'), $input)->assertOk()->assertJsonPath('total', 6300);
    expect($p->fresh()->used_count)->toBe(0);
    app(PlaceOrderAction::class)->execute($l, $input, (string) Str::uuid());
    expect(fn () => app(PlaceOrderAction::class)->execute($l, $input, (string) Str::uuid()))->toThrow(ValidationException::class);
    expect(Order::count())->toBe(1);
});

it('provides only permitted analytics and event types', function () {
    [$owner,$l,$item] = tenantFixture();
    placeFixture($l, $item);
    $issued = wpConnection($owner, $l, ['analytics:read', 'events:read', 'menu:read']);
    $this->withToken($issued['token'])->getJson(wpUrl($l, 'analytics?days=7'))->assertOk()->assertJsonPath('orders', null)->assertJsonPath('tables', null);
    $this->getJson(wpUrl($l, 'events'))->assertOk()->assertJsonCount(0, 'events');
});

it('signs callbacks journals each transport attempt and stops delivery after revocation', function () {
    [$owner,$l,$item] = tenantFixture();
    $issued = wpConnection($owner, $l);
    $this->mock(PublicDnsResolver::class)->shouldReceive('addresses')->andReturn(['1.1.1.1']);
    $headers = ['Idempotency-Key' => (string) Str::uuid()];
    $this->withToken($issued['token'])->putJson(wpUrl($l, 'callback'), ['url' => 'https://hooks.example.org/callback', 'enabled' => true, 'events' => ['order.created']], $headers)->assertOk()->assertJsonMissingPath('secret_encrypted');
    placeFixture($l, $item);
    $m = OutboxMessage::latest('id')->first();
    app(QueueWebhooks::class)->execute($m);
    $d = WebhookDelivery::firstOrFail();
    Http::fake(['hooks.example.org/*' => Http::response('', 200)]);
    app()->call([new DeliverWebhook($d->id), 'handle']);
    expect($d->fresh()->status)->toBe('delivered')->and(CommunicationLog::where('direction', 'outbound')->where('outcome', 'delivered')->count())->toBe(1);
    Http::assertSent(fn ($r) => $r->hasHeader('X-ZionFood-Signature') && $r->hasHeader('X-ZionFood-Request-ID'));
    app(ManageWordpressConnection::class)->revoke($owner, WordpressConnection::firstOrFail());
    placeFixture($l, $item);
    app(QueueWebhooks::class)->execute(OutboxMessage::latest('id')->first());
    expect(WebhookDelivery::count())->toBe(1)->and(WebhookEndpoint::first()->enabled)->toBeFalse();
});

it('journals unauthenticated attempts without attributing a guessed token to a restaurant', function () {
    $this->withToken('999999|invalid')->getJson('/api/v1/wordpress/bootstrap')->assertUnauthorized()->assertHeader('X-ZionFood-Request-ID');
    expect(CommunicationLog::count())->toBe(1)->and(CommunicationLog::first()->restaurant_id)->toBeNull();
});

it('denies a manager minting forbidden scopes or using scopes removed after issuance', function () {
    [$owner,$l] = tenantFixture();
    $manager = User::factory()->create();
    $membership = OrganizationMembership::create(['organization_id' => $l->organization_id, 'user_id' => $manager->id, 'role' => 'manager', 'permissions' => array_fill_keys(StaffPermissions::FEATURES, 'manage')]);
    $issued = wpConnection($manager, $l, ['orders:read']);
    $membership->update(['permissions' => array_fill_keys(StaffPermissions::FEATURES, 'none')]);
    expect(fn () => wpConnection($manager, $l, ['orders:manage']))->toThrow(AuthorizationException::class);
    $this->withToken($issued['token'])->getJson(wpUrl($l, 'orders'))->assertForbidden();
    expect(fn () => app(ManageWordpressConnection::class)->revoke($manager, WordpressConnection::create(['organization_id' => $l->organization_id, 'restaurant_id' => $l->restaurant_id, 'user_id' => $owner->id, 'name' => 'Owner', 'site_url' => 'https://example.org', 'location_ids' => [$l->id], 'abilities' => ['storefront:read'], 'webhook_secret' => Str::random(64)])))->toThrow(HttpException::class);
});

it('does not shorten an already earlier token expiry when rotating', function () {
    [$owner,$l] = tenantFixture();
    $issued = wpConnection($owner, $l);
    $token = PersonalAccessToken::findToken($issued['token']);
    $expiry = now()->addMinutes(2);
    $token->update(['expires_at' => $expiry]);
    app(ManageWordpressConnection::class)->rotate($owner, WordpressConnection::first());
    expect($token->fresh()->expires_at->equalTo($expiry))->toBeTrue();
});

it('creates categories and products through shared domain rules and protects shared menus', function () {
    [$owner,$l] = tenantFixture();
    $issued = wpConnection($owner, $l, ['menu:read', 'menu:write']);
    $category = $this->withToken($issued['token'])->postJson(wpUrl($l, 'categories'), ['name' => 'New category', 'menu_id' => $l->menus()->first()->id], ['Idempotency-Key' => (string) Str::uuid()])->assertOk()->json();
    $item = $this->postJson(wpUrl($l, 'items'), ['category_id' => $category['id'], 'name' => 'API product', 'price' => 1200, 'is_available' => true], ['Idempotency-Key' => (string) Str::uuid()])->assertCreated()->json();
    $this->putJson(wpUrl($l, 'items/'.$item['public_id']), ['category_id' => $category['id'], 'name' => 'Edited', 'price' => 1500, 'is_available' => true], ['Idempotency-Key' => (string) Str::uuid()])->assertOk();
    $this->deleteJson(wpUrl($l, 'items/'.$item['public_id']), [], ['Idempotency-Key' => (string) Str::uuid()])->assertNoContent();
    $other = $l->replicate();
    $other->public_id = (string) Str::ulid();
    $other->save();
    $other->menus()->attach($l->menus()->first()->id, ['organization_id' => $l->organization_id]);
    $this->postJson(wpUrl($l, 'categories'), ['name' => 'Forbidden shared write', 'menu_id' => $l->menus()->first()->id], ['Idempotency-Key' => (string) Str::uuid()])->assertForbidden();
});

it('never prices a coupon from another location and validates duplicate codes as form errors', function () {
    [$owner,$l,$item] = tenantFixture();
    $other = $l->replicate();
    $other->public_id = (string) Str::ulid();
    $other->save();
    $p = app(ManagePromotion::class)->execute($owner, $other, promoInput());
    expect(fn () => app(PlaceOrderAction::class)->execute($l, [...checkoutData($item), 'promotion_code' => 'WELCOME'], (string) Str::uuid()))->toThrow(ValidationException::class);
    expect(fn () => app(ManagePromotion::class)->execute($owner, $l, promoInput()))->toThrow(ValidationException::class);
    expect($p->fresh()->used_count)->toBe(0);
});

it('returns distinct offset slots across the autumn daylight saving transition', function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-24T08:00:00Z'));
    [, $l] = tenantFixture();
    scheduling($l);
    $slots = app(ResolveFulfillmentTime::class)->slots($l, '2026-10-25', 'pickup');
    expect($slots)->toContain('2026-10-25T03:00:00+03:00', '2026-10-25T03:00:00+02:00')->and(count($slots))->toBe(count(array_unique($slots)));
});

it('journals failed webhook attempts and retries with the same event id', function () {
    [$owner,$l,$item] = tenantFixture();
    $issued = wpConnection($owner, $l);
    $this->mock(PublicDnsResolver::class)->shouldReceive('addresses')->andReturn(['1.1.1.1']);
    $this->withToken($issued['token'])->putJson(wpUrl($l, 'callback'), ['url' => 'https://hooks.example.org/callback', 'enabled' => true, 'events' => ['order.created']], ['Idempotency-Key' => (string) Str::uuid()])->assertOk();
    placeFixture($l, $item);
    app(QueueWebhooks::class)->execute(OutboxMessage::latest('id')->first());
    $d = WebhookDelivery::firstOrFail();
    Http::fake(['hooks.example.org/*' => Http::sequence()->push('', 429)->push('', 200)]);
    app()->call([new DeliverWebhook($d->id), 'handle']);
    expect($d->fresh()->status)->toBe('retrying');
    $this->travel(16)->seconds();
    app()->call([new DeliverWebhook($d->id), 'handle']);
    expect($d->fresh()->status)->toBe('delivered')->and(CommunicationLog::where('direction', 'outbound')->count())->toBe(2)->and(CommunicationLog::where('event_id', $d->event_id)->where('error_code', 'http_429')->count())->toBe(1);
});

it('normalizes promotion date offsets to UTC before persistence and eligibility checks', function () {
    [$owner, $l, $item] = tenantFixture();
    $p = app(ManagePromotion::class)->execute($owner, $l, promoInput(['starts_at' => '2026-11-02T11:00:00+02:00', 'ends_at' => '2026-11-02T13:00:00+02:00']));
    expect($p->fresh()->starts_at->format('H:i'))->toBe('09:00');
    $o = app(PlaceOrderAction::class)->execute($l, [...checkoutData($item), 'promotion_code' => 'WELCOME'], (string) Str::uuid())['order'];
    expect($o->total)->toBe(6300);
});

it('stops dedicated credentials on legacy routes after the issuer becomes staff', function () {
    [, $l] = tenantFixture();
    $manager = User::factory()->create();
    $m = OrganizationMembership::create(['organization_id' => $l->organization_id, 'user_id' => $manager->id, 'role' => 'manager']);
    $issued = wpConnection($manager, $l, ['orders:read']);
    LocationStaff::create(['organization_id' => $l->organization_id, 'user_id' => $manager->id, 'location_id' => $l->id]);
    $m->update(['role' => 'staff']);
    $this->withToken($issued['token'])->getJson('/api/v1/locations/'.$l->public_id.'/orders')->assertForbidden();
    $this->getJson(wpUrl($l, 'orders'))->assertForbidden();
});

it('issues non-expiring keys while retaining rotation grace and revocation', function () {
    [$owner, $l] = tenantFixture();
    $this->actingAs($owner)->postJson('/app/locations/'.$l->public_id.'/wordpress-connections', ['name' => 'Permanent WordPress', 'site_url' => 'https://restaurant.example', 'location_ids' => [$l->id], 'abilities' => ['storefront:read'], 'expires_in_days' => 0])->assertCreated()->assertJsonPath('expires_at', null);
    $connection = WordpressConnection::firstOrFail();
    $old = PersonalAccessToken::where('wordpress_connection_id', $connection->id)->firstOrFail();
    expect($old->expires_at)->toBeNull()->and((int) $connection->expires_in_days)->toBe(0);
    // Reissue through the lifecycle action to obtain a new plaintext bearer for the actual authentication checks.
    $issued = app(ManageWordpressConnection::class)->rotate($owner, $connection);
    expect($issued['expires_at'])->toBeNull()->and($old->fresh()->expires_at->equalTo(now()->addMinutes(15)))->toBeTrue();
    $this->travel(10)->years();
    $this->app['auth']->forgetGuards();
    $this->withToken($issued['token'])->getJson('/api/v1/wordpress/bootstrap')->assertOk();
    $rotated = app(ManageWordpressConnection::class)->rotate($owner, $connection);
    expect($rotated['expires_at'])->toBeNull();
    $this->travel(16)->minutes();
    $this->app['auth']->forgetGuards();
    $this->withToken($issued['token'])->getJson('/api/v1/wordpress/bootstrap')->assertUnauthorized();
    $this->app['auth']->forgetGuards();
    $this->withToken($rotated['token'])->getJson('/api/v1/wordpress/bootstrap')->assertOk();
    app(ManageWordpressConnection::class)->revoke($owner, $connection);
    $this->app['auth']->forgetGuards();
    $this->getJson('/api/v1/wordpress/bootstrap')->assertUnauthorized();
});
