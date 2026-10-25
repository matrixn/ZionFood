<?php

use App\Domain\Integrations\PublicDnsResolver;
use App\Domain\Integrations\PublicWebhookDestination;
use App\Domain\Integrations\QueueWebhooks;
use App\Domain\Integrations\UnsafeWebhookDestination;
use App\Domain\Menus\Actions\UpdateMenuItem;
use App\Events\OrderChanged;
use App\Jobs\DeliverOutbox;
use App\Jobs\DeliverWebhook;
use App\Models\Menu;
use App\Models\MenuCategory;
use App\Models\ModifierGroup;
use App\Models\OutboxMessage;
use App\Models\RestaurantLocation;
use App\Models\WebhookDelivery;
use App\Models\WebhookEndpoint;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;

beforeEach(function () {
    Queue::fake();
    Http::preventStrayRequests();
    $this->travelTo(now()->setTime(12, 0));
    $this->mock(PublicDnsResolver::class)->shouldReceive('addresses')->andReturn(['1.1.1.1']);
});
function endpointFixture($location, array $extra = []): WebhookEndpoint
{
    return WebhookEndpoint::create(['organization_id' => $location->organization_id, 'location_id' => $location->id, 'name' => 'Test receiver', 'url' => 'https://hooks.example.com/orders', 'secret_encrypted' => Crypt::encryptString(str_repeat('s', 48)), 'enabled' => true, 'events' => ['order.created', 'order.updated', 'item.updated', 'menu.updated'], ...$extra]);
}
function deliveryFixture($location, $item): WebhookDelivery
{
    placeFixture($location, $item);
    $message = OutboxMessage::latest('id')->firstOrFail();
    app(QueueWebhooks::class)->execute($message);

    return WebhookDelivery::where('outbox_message_id', $message->id)->firstOrFail();
}
it('configures scoped webhooks without exposing existing signing secrets', function () {
    [$owner,$location] = tenantFixture('first');
    [, $foreign] = tenantFixture('second');
    $data = ['name' => 'Receiver', 'url' => 'https://hooks.example.com/receive', 'events' => ['order.created'], 'enabled' => true, 'secret' => str_repeat('s', 48)];
    $this->actingAs($owner)->postJson('/api/v1/locations/'.$location->public_id.'/webhooks', $data)->assertCreated()->assertJsonMissingPath('secret_encrypted')->assertJsonMissingPath('secret');
    $endpoint = WebhookEndpoint::firstOrFail();
    expect($endpoint->secret_encrypted)->not->toBe($data['secret']);
    expect(Crypt::decryptString($endpoint->secret_encrypted))->toBe($data['secret']);
    $this->getJson('/api/v1/locations/'.$location->public_id.'/webhooks')->assertOk()->assertJsonMissingPath('endpoints.0.secret_encrypted');
    $this->putJson('/api/v1/locations/'.$foreign->public_id.'/webhooks/'.$endpoint->id, $data)->assertForbidden();
    $this->putJson('/api/v1/locations/'.$location->public_id.'/webhooks/'.$endpoint->id, [...$data, 'secret' => ''])->assertOk();
    expect(Crypt::decryptString($endpoint->fresh()->secret_encrypted))->toBe($data['secret']);
    $token = $owner->createToken('Write menu', ['menu:write'])->plainTextToken;
    $this->app['auth']->forgetGuards();
    $this->withToken($token)->getJson('/api/v1/locations/'.$location->public_id.'/webhooks')->assertForbidden();
});
it('rejects unsafe URL syntax before any outbound request', function (string $url) {
    expect(fn () => app(PublicWebhookDestination::class)->resolve($url))->toThrow(UnsafeWebhookDestination::class);
    Http::assertNothingSent();
})->with(['http://example.com/hooks', 'https://localhost/hooks', 'https://127.0.0.1/hooks', 'https://[::1]/hooks', 'https://example.com:8443/hooks', 'https://user:password@example.com/hooks', 'https://example.com/hooks?secret=value', 'https://example.com/hooks#secret', 'https://service.internal/hooks']);
it('rejects private special and mixed DNS answers', function (array $addresses) {
    $this->mock(PublicDnsResolver::class)->shouldReceive('addresses')->andReturn($addresses);
    expect(fn () => app(PublicWebhookDestination::class)->resolve('https://hooks.example.com/events'))->toThrow(UnsafeWebhookDestination::class);
    Http::assertNothingSent();
})->with([[['127.0.0.1']], [['10.0.0.1']], [['169.254.169.254']], [['100.100.100.200']], [['192.168.1.1']], [['198.18.0.1']], [['::ffff:127.0.0.1']], [['fe80::1']], [['fc00::1']], [['2001:db8::1']], [['1.1.1.1', '127.0.0.1']], [[]]]);
it('fans out only to enabled matching endpoints in the same organization and location', function () {
    [, $a,$item] = tenantFixture('first');
    [, $b] = tenantFixture('second');
    $wanted = endpointFixture($a);
    endpointFixture($a, ['enabled' => false]);
    endpointFixture($a, ['events' => ['menu.updated']]);
    endpointFixture($b);
    placeFixture($a, $item);
    $message = OutboxMessage::firstOrFail();
    app(QueueWebhooks::class)->execute($message);
    app(QueueWebhooks::class)->execute($message);
    expect(WebhookDelivery::count())->toBe(1);
    expect(WebhookDelivery::first()->endpoint_id)->toBe($wanted->id);
});
it('signs exact request bytes pins checked DNS and skips duplicate delivered jobs', function () {
    [, $location,$item] = tenantFixture();
    endpointFixture($location);
    $delivery = deliveryFixture($location, $item);
    $options = [];
    Http::fake(function ($request, $requestOptions) use (&$options) {
        $options = $requestOptions;

        return Http::response('', 204);
    });
    $job = new DeliverWebhook($delivery->id);
    $job->handle(app(PublicWebhookDestination::class));
    $job->handle(app(PublicWebhookDestination::class));
    Http::assertSentCount(1);
    Http::assertSent(function ($request) {
        $timestamp = $request->header('X-ZionFood-Timestamp')[0];

        return $request->header('X-ZionFood-Signature')[0] === 'sha256='.hash_hmac('sha256', $timestamp.'.'.$request->body(), str_repeat('s', 48)) && ! str_contains($request->body(), 'customer_phone');
    });
    expect($options['allow_redirects'])->toBeFalse();
    expect($options['proxy'])->toBe('');
    expect($options['curl'][CURLOPT_RESOLVE])->toBe(['hooks.example.com:443:1.1.1.1']);
    expect($delivery->fresh()->status)->toBe('delivered');
    expect($delivery->fresh()->attempts)->toBe(1);
});
it('revalidates DNS after configuration to prevent rebinding', function () {
    [, $location,$item] = tenantFixture();
    endpointFixture($location);
    $delivery = deliveryFixture($location, $item);
    $this->mock(PublicDnsResolver::class)->shouldReceive('addresses')->andReturn(['127.0.0.1']);
    Http::fake();
    (new DeliverWebhook($delivery->id))->handle(app(PublicWebhookDestination::class));
    Http::assertNothingSent();
    expect($delivery->fresh()->status)->toBe('blocked');
});
it('persists bounded retries and refuses redirects', function () {
    [, $location,$item] = tenantFixture();
    endpointFixture($location);
    $delivery = deliveryFixture($location, $item);
    Http::fakeSequence()->push('', 500)->push('', 204)->push('', 302, ['Location' => 'http://127.0.0.1/internal']);
    (new DeliverWebhook($delivery->id))->handle(app(PublicWebhookDestination::class));
    expect($delivery->fresh()->status)->toBe('retrying');
    (new DeliverWebhook($delivery->id))->handle(app(PublicWebhookDestination::class));
    Http::assertSentCount(1);
    $this->travel(16)->seconds();
    (new DeliverWebhook($delivery->id))->handle(app(PublicWebhookDestination::class));
    expect($delivery->fresh()->status)->toBe('delivered');
    $another = deliveryFixture($location, $item);
    (new DeliverWebhook($another->id))->handle(app(PublicWebhookDestination::class));
    expect($another->fresh()->status)->toBe('failed');
    expect($another->fresh()->http_status)->toBe(302);
});
it('reconciles stale leases and stops after six failed attempts', function () {
    [, $location,$item] = tenantFixture();
    endpointFixture($location);
    $delivery = deliveryFixture($location, $item);
    Http::fake(['*' => Http::response('', 503)]);
    $delivery->update(['status' => 'delivering', 'locked_until' => now()->addMinute(), 'claim_token' => (string) Str::uuid()]);
    (new DeliverWebhook($delivery->id))->handle(app(PublicWebhookDestination::class));
    Http::assertNothingSent();
    $this->travel(61)->seconds();
    for ($i = 0; $i < 6; $i++) {
        (new DeliverWebhook($delivery->id))->handle(app(PublicWebhookDestination::class));
        $this->travel(2)->hours();
    }
    expect($delivery->fresh()->status)->toBe('failed');
    expect($delivery->fresh()->attempts)->toBe(6);
    Http::assertSentCount(6);
});
it('preserves webhook fanout despite a failed kitchen broadcast', function () {
    [, $location,$item] = tenantFixture();
    endpointFixture($location);
    placeFixture($location, $item);
    $message = OutboxMessage::firstOrFail();
    Event::listen(OrderChanged::class, fn () => throw new RuntimeException('Broadcast unavailable'));
    expect(fn () => (new DeliverOutbox($message->id))->handle())->toThrow(RuntimeException::class);
    expect(WebhookDelivery::count())->toBe(1);
    expect($message->fresh()->delivered_at)->toBeNull();
});
it('cancels disabled endpoints and permits authorized retry without changing the event id', function () {
    [$owner,$location,$item] = tenantFixture();
    $endpoint = endpointFixture($location);
    $delivery = deliveryFixture($location, $item);
    $id = $delivery->event_id;
    $delivery->update(['status' => 'failed', 'attempts' => 6]);
    $this->actingAs($owner)->postJson('/api/v1/locations/'.$location->public_id.'/webhook-deliveries/'.$delivery->id.'/retry')->assertNoContent();
    expect($delivery->fresh()->event_id)->toBe($id);
    expect($delivery->fresh()->attempts)->toBe(0);
    $endpoint->update(['enabled' => false]);
    Http::fake();
    (new DeliverWebhook($delivery->id))->handle(app(PublicWebhookDestination::class));
    Http::assertNothingSent();
    expect($delivery->fresh()->status)->toBe('cancelled');
});
it('keeps API field names stable in Romanian and records menu changes atomically', function () {
    [$owner,$location,$item] = tenantFixture();
    $this->actingAs($owner)->postJson('/app/locations/'.$location->public_id.'/modifiers', ['name' => 'Sosuri', 'min_choices' => 0, 'max_choices' => 1, 'options' => [['name' => 'Sos', 'price' => 200]]])->assertRedirect();
    $group = ModifierGroup::where('organization_id', $location->organization_id)->firstOrFail();
    $item->modifierGroups()->attach($group->id, ['organization_id' => $location->organization_id]);
    $this->getJson('/api/v1/public/locations/'.$location->public_id.'/menu')->assertJsonPath('menus.0.categories.0.items.0.modifier_groups.0.options.0.name', 'Sos');
    $this->get('/app/locations/'.$location->public_id.'/menu')->assertOk();
    expect(OutboxMessage::where('type', 'menu.updated')->count())->toBe(1);
    OutboxMessage::creating(fn () => throw new RuntimeException('Outbox insert failed'));
    expect(fn () => app(UpdateMenuItem::class)->execute($owner, $location->organization_id, ['category_id' => $item->category_id, 'name' => 'Changed', 'price' => 9000], $item))->toThrow(RuntimeException::class);
    expect($item->fresh()->name)->toBe('Pizza');
    expect($item->fresh()->price)->toBe(3500);
});
it('notifies both affected locations when moving a product to another assigned menu', function () {
    [$owner,$a,$item] = tenantFixture();
    $b = RestaurantLocation::factory()->create(['organization_id' => $a->organization_id, 'restaurant_id' => $a->restaurant_id]);
    $menu = Menu::factory()->create(['organization_id' => $a->organization_id, 'restaurant_id' => $a->restaurant_id]);
    $b->menus()->attach($menu->id, ['organization_id' => $a->organization_id]);
    $category = MenuCategory::factory()->create(['organization_id' => $a->organization_id, 'menu_id' => $menu->id]);
    app(UpdateMenuItem::class)->execute($owner, $a->organization_id, ['category_id' => $category->id, 'name' => 'Moved', 'price' => 3500], $item);
    expect(OutboxMessage::where('type', 'item.updated')->pluck('location_id')->sort()->values()->all())->toBe(collect([$a->id, $b->id])->sort()->values()->all());
});
it('retains scoped delivery history after an endpoint is removed', function () {
    [$owner,$location,$item] = tenantFixture();
    $endpoint = endpointFixture($location);
    $delivery = deliveryFixture($location, $item);
    $this->actingAs($owner)->deleteJson('/api/v1/locations/'.$location->public_id.'/webhooks/'.$endpoint->id)->assertNoContent();
    $this->getJson('/api/v1/locations/'.$location->public_id.'/webhooks')->assertOk()->assertJsonCount(0, 'endpoints')->assertJsonPath('deliveries.data.0.id', $delivery->id)->assertJsonPath('deliveries.data.0.endpoint.name', 'Test receiver')->assertJsonMissingPath('deliveries.data.0.endpoint.secret_encrypted');
});
it('reconciles more than one chunk without duplicating jobs or selecting future attempts', function () {
    [, $location] = tenantFixture();
    $endpoint = endpointFixture($location);
    foreach (range(1, 108) as $number) {
        $message = OutboxMessage::create(['event_id' => (string) Str::ulid(), 'organization_id' => $location->organization_id, 'location_id' => $location->id, 'type' => 'menu.updated', 'payload' => ['location_id' => $location->public_id]]);
        WebhookDelivery::create(['endpoint_id' => $endpoint->id, 'event_id' => $message->event_id, 'outbox_message_id' => $message->id, 'payload' => ['id' => $message->event_id, 'type' => 'menu.updated', 'data' => $message->payload], 'status' => $number >= 107 ? 'delivering' : 'pending', 'next_attempt_at' => $number === 106 ? now()->addHour() : now(), 'locked_until' => $number === 107 ? now()->addMinute() : ($number === 108 ? now()->subMinute() : null)]);
    }
    Queue::fake();
    $this->artisan('zionfood:webhooks')->assertExitCode(0);
    Queue::assertPushed(DeliverWebhook::class, 106);
    expect(Queue::pushed(DeliverWebhook::class)->map(fn ($job) => $job->deliveryId)->unique()->count())->toBe(106);
});
