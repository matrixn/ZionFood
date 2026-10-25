<?php

use App\Domain\Orders\Actions\PlaceOrderAction;
use App\Models\LocationStaff;
use App\Models\MenuItem;
use App\Models\OrganizationMembership;
use App\Models\RestaurantLocation;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

beforeEach(function () {
    Queue::fake();
    $this->travelTo(now()->setTime(12, 0));
});

it('enforces database tenant consistency even outside controllers', function () {
    [, $a] = tenantFixture('first');
    [$user, $b] = tenantFixture('second');
    expect(fn () => LocationStaff::create(['organization_id' => $a->organization_id, 'location_id' => $b->id, 'user_id' => $user->id]))->toThrow(QueryException::class);
});
it('keeps platform privileges separate from tenant membership', function () {
    [, $location] = tenantFixture();
    $admin = User::factory()->create(['is_platform_admin' => true]);
    $this->actingAs($admin)->get('/admin')->assertOk();
    $this->getJson('/api/v1/locations/'.$location->public_id.'/orders')->assertForbidden();
});
it('issues organization scoped tokens and revokes them', function () {
    [$owner, $a] = tenantFixture('first');
    [, $b] = tenantFixture('second');
    OrganizationMembership::create(['user_id' => $owner->id, 'organization_id' => $b->organization_id, 'role' => 'owner']);
    $response = $this->actingAs($owner)->postJson('/api/v1/locations/'.$a->public_id.'/integration-tokens', ['name' => 'WordPress', 'abilities' => ['menu:read']])->assertCreated();
    $token = $response->json('token');
    $id = $owner->tokens()->first()->id;
    $this->app['auth']->forgetGuards();
    $this->withToken($token)->getJson('/api/v1/locations/'.$b->public_id.'/menu')->assertForbidden();
    $this->app['auth']->forgetGuards();
    $this->withToken($token)->getJson('/api/v1/locations/'.$a->public_id.'/menu')->assertOk();
    $this->app['auth']->forgetGuards();
    $this->withToken($token)->postJson('/api/v1/locations/'.$a->public_id.'/integration-tokens', ['name' => 'Escalation', 'abilities' => ['orders:manage']])->assertForbidden();
    $this->actingAs($owner)->withHeader('Authorization', '')->deleteJson('/api/v1/locations/'.$a->public_id.'/integration-tokens/'.$id)->assertNoContent();
    expect($owner->tokens()->count())->toBe(0);
});
it('authorizes private channels by both organization and staff location', function () {
    [$owner, $location] = tenantFixture();
    $foreign = User::factory()->create();
    config(['broadcasting.default' => 'reverb', 'broadcasting.connections.reverb.key' => 'test-key', 'broadcasting.connections.reverb.secret' => 'test-secret', 'broadcasting.connections.reverb.app_id' => 'testing']);
    require base_path('routes/channels.php');
    $body = ['channel_name' => "private-organization.{$location->organization_id}.location.{$location->id}.orders", 'socket_id' => '123.456'];
    $this->actingAs($foreign)->postJson('/broadcasting/auth', $body)->assertForbidden();
    $this->actingAs($owner)->postJson('/broadcasting/auth', $body)->assertOk();
});
it('rejects unsafe uploads and preserves explicit empty variant arrays with an image', function () {
    Storage::fake('public');
    [$owner, , $item] = tenantFixture();
    $item->variants()->create(['name' => 'Large', 'price' => 5000]);
    $this->actingAs($owner)->postJson('/api/v1/items/'.$item->public_id, ['_method' => 'PUT', 'payload' => json_encode(['category_id' => $item->category_id, 'name' => 'Pizza', 'price' => 3500, 'is_available' => true]), 'image' => UploadedFile::fake()->createWithContent('unsafe.svg', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>')])->assertUnprocessable();
    $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/h9sAAAAASUVORK5CYII=');
    $this->post('/api/v1/items/'.$item->public_id, ['_method' => 'PUT', 'payload' => json_encode(['category_id' => $item->category_id, 'name' => 'Pizza', 'price' => 3500, 'is_available' => true, 'variants' => [], 'modifier_group_ids' => []]), 'image' => UploadedFile::fake()->createWithContent('photo.png', $png)], ['Accept' => 'application/json'])->assertOk();
    expect($item->variants()->count())->toBe(0);
    Storage::disk('public')->assertExists($item->fresh()->image_path);
});
it('keeps history immutable through the model', function () {
    [, $location, $item] = tenantFixture();
    $order = placeFixture($location, $item)['order'];
    expect(fn () => $order->history()->first()->update(['to_status' => 'completed']))->toThrow(LogicException::class);
});
it('treats changed client prices as the same idempotent checkout', function () {
    [, $location, $item] = tenantFixture();
    $key = (string) Str::uuid();
    $data = checkoutData($item);
    $data['items'][0]['price'] = 1;
    $action = app(PlaceOrderAction::class);
    $first = $action->execute($location, $data, $key);
    $data['items'][0]['price'] = 99999;
    $second = $action->execute($location, $data, $key);
    expect($second['order']->id)->toBe($first['order']->id);
    expect($second['replayed'])->toBeTrue();
});
it('persists closed days and validates invalid opening intervals', function () {
    [$owner, $location] = tenantFixture();
    $data = ['name' => 'Main', 'address' => 'Street', 'accepting_orders' => true, 'pickup_enabled' => true, 'delivery_enabled' => false, 'hours' => [], 'zones' => []];
    $this->actingAs($owner)->put('/app/locations/'.$location->public_id.'/settings', $data)->assertSessionHasNoErrors();
    expect($location->hours()->count())->toBe(0);
    $data['hours'] = [['weekday' => 1, 'opens_at' => '22:00', 'closes_at' => '10:00']];
    $this->putJson('/app/locations/'.$location->public_id.'/settings', $data)->assertUnprocessable();
});
it('supports factories with consistent tenant ancestry', function () {
    $item = MenuItem::factory()->create();
    expect($item->organization_id)->toBe($item->category->menu->organization_id);
    $location = RestaurantLocation::factory()->create();
    expect($location->organization_id)->toBe($location->restaurant->organization_id);
});
