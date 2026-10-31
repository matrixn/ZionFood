<?php

use App\Domain\Imports\GloriaFoodPublicClient;
use App\Domain\Imports\ImportRestaurantSettings;
use App\Domain\Imports\NormalizePublicRestaurantSettings;
use App\Domain\Imports\ProcessImport;
use App\Domain\Imports\PublishImport;
use App\Domain\Integrations\PublicDnsResolver;
use App\Domain\Tenancy\StaffPermissions;
use App\Models\ImportBatch;
use App\Models\OrganizationMembership;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

function publicSettingsSource(): array
{
    return ['id' => 900, 'restaurant_id' => 901, 'currency' => 'RON', 'categories' => [['id' => 1, 'name' => 'Mains', 'items' => [['id' => 10, 'name' => 'Dish', 'price' => 20]]]]];
}

function publicSettingsData(): array
{
    return ['id' => 901, 'uid' => 'ca5549f4-4c22-4fd1-9c51-3bd227b20297', 'name' => 'Imported restaurant', 'phone' => '+40 757 188 188', 'address' => 'New street 43, New City', 'latitude' => '45.1', 'longitude' => '24.3',
        'restaurantAccount' => ['city' => 'New City', 'country_code' => 'RO', 'zipcode' => '240425', 'timezone' => 'Europe/Bucharest'],
        'opening_hours' => [['type' => 'opening', 'day_of_week' => 127, 'begin_minute' => 660, 'end_minute' => 1380]],
        'delivery_zones' => [['id' => 77, 'name' => 'Imported zone', 'active' => true, 'usage' => 'delivery', 'type' => 'polygon', 'delivery_fee_type' => 'fixed', 'delivery_fee' => '5.25', 'minimum_order' => '40.10', 'shape_json' => json_encode(['path' => [['lat' => 45, 'lng' => 24], ['lat' => 45, 'lng' => 25], ['lat' => 46, 'lng' => 25]]])]],
        'menu' => publicSettingsSource(), 'pictures' => []];
}

function settingsBatch($u, $l, ?array $public = null): ImportBatch
{
    Http::fake([GloriaFoodPublicClient::API => Http::response(['restaurant' => $public ?? publicSettingsData()], 200, ['Content-Type' => 'application/json'])]);
    $b = ImportBatch::create(['organization_id' => $l->organization_id, 'location_id' => $l->id, 'user_id' => $u->id, 'settings' => ['public_menu_url' => 'https://www.foodbooking.com/ordering/restaurant/menu?restaurant_uid=ca5549f4-4c22-4fd1-9c51-3bd227b20297', 'size_prices' => 'base_plus_size', 'nutrition_basis' => 'per_100g', 'tax_basis_points' => 0]]);
    app(ProcessImport::class)->execute($b, publicSettingsSource());

    return $b->fresh();
}

function publishSettings($u, $b, array $fields): void
{
    app(PublishImport::class)->execute($u, $b, [], true, app(PublishImport::class)->reviewHash($b), $fields);
}

beforeEach(function () {
    $this->mock(PublicDnsResolver::class)->shouldReceive('addresses')->andReturn(['1.1.1.1']);
});

it('previews all five settings without changing any existing data or re-fetching on selection', function () {
    [$u, $l] = tenantFixture();
    $action = app(ImportRestaurantSettings::class);
    $before = $action->state($l);
    $b = settingsBatch($u, $l);
    $preview = $action->preview($b, app(ProcessImport::class)->canonical($b));
    expect($action->state($l))->toBe($before);
    expect(array_column($preview, 'available'))->toBe([true, true, true, true, true]);
    expect($preview[4]['proposed'][0])->toMatchArray(['fee' => 525, 'minimum_order' => 4010]);
    expect($preview[4]['proposed'][0]['geometry']['coordinates'][0])->toBe([[24, 45], [25, 45], [25, 46], [24, 45]]);
    $url = '/app/locations/'.$l->public_id.'/migration-center/'.$b->public_id;
    $this->actingAs($u)->getJson($url)->assertOk()->assertJsonCount(5, 'settings_preview');
    publishSettings($u, $b, []);
    expect($action->state($l))->toBe($before);
    Http::assertSentCount(1);
});

it('applies only the explicitly selected setting', function ($field) {
    [$u, $l] = tenantFixture();
    $action = app(ImportRestaurantSettings::class);
    $before = $action->state($l);
    $b = settingsBatch($u, $l);
    publishSettings($u, $b, [$field]);
    $after = $action->state($l);
    expect($after[$field])->not->toBe($before[$field]);
    foreach (array_diff(NormalizePublicRestaurantSettings::FIELDS, [$field]) as $unchanged) {
        expect($after[$unchanged])->toBe($before[$unchanged]);
    }
    $snapshot = DB::table('import_publications')->where('batch_id', $b->id)->value('settings_snapshot');
    expect(json_decode(Crypt::decryptString($snapshot), true)['before'])->toHaveKey($field);
    $report = json_decode(DB::table('import_reports')->where('batch_id', $b->id)->value('report'), true);
    expect(array_keys($report['settings_changes']))->toBe([$field]);
})->with(NormalizePublicRestaurantSettings::FIELDS);

it('appends zones without deleting or modifying existing zones and avoids reimport duplicates', function () {
    [$u, $l] = tenantFixture();
    $old = $l->zones()->create(['name' => 'Existing zone', 'postal_prefix' => '24', 'fee' => 100, 'minimum_order' => 0, 'priority' => 1]);
    $b = settingsBatch($u, $l);
    publishSettings($u, $b, ['zones']);
    expect($l->zones()->count())->toBe(2)->and($old->fresh()->fee)->toBe(100);
    $imported = $l->zones()->where('id', '!=', $old->id)->first();
    $imported->update(['fee' => 999, 'name' => 'Locally reviewed zone']);
    $second = settingsBatch($u, $l);
    $preview = app(ImportRestaurantSettings::class)->preview($second, app(ProcessImport::class)->canonical($second));
    expect($preview[4]['added_zones'])->toBe(0);
    publishSettings($u, $second, ['zones']);
    expect($l->zones()->count())->toBe(2)->and($imported->fresh()->fee)->toBe(999)->and($imported->fresh()->name)->toBe('Locally reviewed zone');
});

it('matches an identical existing zone without a mapping and coalesces equivalent source zones', function () {
    [$u, $l] = tenantFixture();
    $source = publicSettingsData();
    $candidate = app(NormalizePublicRestaurantSettings::class)->execute($source)['zones']['value'][0];
    $old = $l->zones()->create([...array_diff_key($candidate, ['source_id' => true]), 'priority' => 0]);
    $source['delivery_zones'][] = [...$source['delivery_zones'][0], 'id' => 88];
    $b = settingsBatch($u, $l, $source);
    publishSettings($u, $b, ['zones']);
    expect($l->zones()->count())->toBe(1)->and($l->zones()->first()->id)->toBe($old->id);
    expect(DB::table('import_entity_mappings')->where('batch_id', $b->id)->where('entity_type', 'delivery_zone')->pluck('local_id')->all())->toBe([$old->id, $old->id]);
});

it('rejects a stale preview when restaurant settings change before approval', function ($field) {
    [$u, $l] = tenantFixture();
    $b = settingsBatch($u, $l);
    $hash = app(PublishImport::class)->reviewHash($b);
    match ($field) {
        'name' => $l->restaurant()->update(['name' => 'Changed elsewhere']),
        'phone' => $l->restaurant()->update(['contact_phone' => '0711111111']),
        'address' => $l->update(['address' => 'Changed elsewhere']),
        'hours' => $l->hours()->update(['opens_at' => '09:00']),
        'zones' => $l->zones()->create(['name' => 'Changed elsewhere', 'postal_prefix' => '24', 'fee' => 100, 'minimum_order' => 0]),
    };
    try {
        app(PublishImport::class)->execute($u, $b, [], true, $hash, [$field]);
        $this->fail('Expected stale preview');
    } catch (HttpException $e) {
        expect($e->getStatusCode())->toBe(409);
    }
    expect($b->fresh()->status)->toBe('ready');
})->with(NormalizePublicRestaurantSettings::FIELDS);

it('rolls selected settings back without deleting zones that predated the import', function () {
    [$u, $l] = tenantFixture();
    $old = $l->zones()->create(['name' => 'Existing', 'postal_prefix' => '24', 'fee' => 100, 'minimum_order' => 0]);
    $before = app(ImportRestaurantSettings::class)->state($l);
    $b = settingsBatch($u, $l);
    publishSettings($u, $b, NormalizePublicRestaurantSettings::FIELDS);
    app(PublishImport::class)->revert($u, $b);
    expect(app(ImportRestaurantSettings::class)->state($l))->toBe($before)->and($old->fresh())->not->toBeNull();
});

it('refuses rollback after a local edit to an imported setting', function () {
    [$u, $l] = tenantFixture();
    $b = settingsBatch($u, $l);
    publishSettings($u, $b, ['phone']);
    $l->restaurant()->update(['contact_phone' => '0722222222']);
    try {
        app(PublishImport::class)->revert($u, $b);
        $this->fail('Expected local edit protection');
    } catch (HttpException $e) {
        expect($e->getStatusCode())->toBe(409);
    }
    expect($b->fresh()->status)->toBe('published')->and($l->restaurant()->first()->contact_phone)->toBe('0722222222');
});

it('leaves unsupported source settings unavailable without blocking an unselected menu import', function ($kind) {
    [$u, $l] = tenantFixture();
    $public = publicSettingsData();
    if ($kind === 'hours') {
        $public['opening_hours'][] = ['type' => 'opening', 'day_of_week' => 1, 'begin_minute' => 300, 'end_minute' => 400];
    } else {
        $public['delivery_zones'][0]['type'] = 'circle';
    }
    $b = settingsBatch($u, $l, $public);
    expect(app(ProcessImport::class)->canonical($b)['public_menu']['settings_candidates'][$kind]['available'])->toBeFalse();
    expect(fn () => publishSettings($u, $b, [$kind]))->toThrow(ValidationException::class);
    publishSettings($u, $b, []);
    expect($b->fresh()->status)->toBe('published');
})->with(['hours', 'zones']);

it('requires order management access to change hours or delivery zones', function ($field) {
    [$owner, $l] = tenantFixture();
    $manager = User::factory()->create();
    OrganizationMembership::create(['organization_id' => $l->organization_id, 'user_id' => $manager->id, 'role' => 'manager', 'permissions' => [...StaffPermissions::defaults('manager'), 'orders' => 'view']]);
    $b = settingsBatch($owner, $l);
    expect(fn () => publishSettings($manager, $b, [$field]))->toThrow(AuthorizationException::class);
    expect($b->fresh()->status)->toBe('ready');
})->with(['hours', 'zones']);

it('keeps shared name changes and wizard fields consistent across the restaurant locations', function () {
    [$u, $l] = tenantFixture();
    $second = $l->replicate(['public_id', 'slug']);
    $second->name = 'Second location';
    $second->save();
    $b = settingsBatch($u, $l);
    $preview = app(ImportRestaurantSettings::class)->preview($b, app(ProcessImport::class)->canonical($b));
    expect($preview[0]['affected_locations'])->toHaveCount(2);
    publishSettings($u, $b, ['name']);
    expect($second->fresh()->setup_profile['basics']['restaurant_name'])->toBe('Imported restaurant');
    expect($second->fresh()->address)->toBe('Test street');
    app(PublishImport::class)->revert($u, $b);
    expect($second->fresh()->setup_profile['basics']['restaurant_name'] ?? null)->toBeNull();
});

it('rejects unknown or duplicate selections at the HTTP boundary', function ($fields) {
    [$u, $l] = tenantFixture();
    $b = settingsBatch($u, $l);
    $before = app(ImportRestaurantSettings::class)->state($l);
    $this->actingAs($u)->postJson('/app/locations/'.$l->public_id.'/migration-center/'.$b->public_id.'/publish', ['acknowledged' => true, 'resolutions' => [], 'review_hash' => app(PublishImport::class)->reviewHash($b), 'settings_fields' => $fields])->assertUnprocessable();
    expect(app(ImportRestaurantSettings::class)->state($l))->toBe($before);
})->with([[['currency']], [['name', 'name']]]);

it('uses only staged values rather than client supplied field values at approval', function () {
    [$u, $l] = tenantFixture();
    $b = settingsBatch($u, $l);
    $this->actingAs($u)->postJson('/app/locations/'.$l->public_id.'/migration-center/'.$b->public_id.'/publish', ['acknowledged' => true, 'resolutions' => [], 'review_hash' => app(PublishImport::class)->reviewHash($b), 'settings_fields' => ['phone'], 'phone' => 'untrusted-client-value'])->assertOk();
    expect($l->restaurant()->first()->contact_phone)->toBe(publicSettingsData()['phone']);
});

it('preserves unselected changes made after publication when rolling selected fields back', function () {
    [$u, $l] = tenantFixture();
    $b = settingsBatch($u, $l);
    publishSettings($u, $b, ['phone']);
    $l->update(['address' => 'Later address edit']);
    app(PublishImport::class)->revert($u, $b);
    expect($l->fresh()->address)->toBe('Later address edit');
});

it('refuses a settings rollback receipt from another location even for an authorized target owner', function () {
    [$u, $l] = tenantFixture();
    $b = settingsBatch($u, $l);
    publishSettings($u, $b, ['phone']);
    $snapshot = DB::table('import_publications')->where('batch_id', $b->id)->value('settings_snapshot');
    [$other, $target] = tenantFixture('other-receipt-target');
    $before = app(ImportRestaurantSettings::class)->state($target);
    try {
        app(ImportRestaurantSettings::class)->revert($other, $target, $snapshot);
        $this->fail('Expected scoped rollback receipt');
    } catch (HttpException $e) {
        expect($e->getStatusCode())->toBe(409);
    }
    expect(app(ImportRestaurantSettings::class)->state($target))->toBe($before);
});

it('retains an imported zone identity through the regular settings editor before reimport', function () {
    [$u, $l] = tenantFixture();
    $b = settingsBatch($u, $l);
    publishSettings($u, $b, ['zones']);
    $zone = $l->zones()->firstOrFail();
    $data = ['name' => $l->name, 'address' => $l->address, 'accepting_orders' => true, 'pickup_enabled' => (bool) $l->fulfillment->pickup_enabled, 'delivery_enabled' => (bool) $l->fulfillment->delivery_enabled,
        'hours' => $l->hours->map(fn ($h) => ['weekday' => $h->weekday, 'opens_at' => substr($h->opens_at, 0, 5), 'closes_at' => substr($h->closes_at, 0, 5)])->all(),
        'zones' => [['id' => $zone->id, 'name' => 'Locally edited through Settings', 'postal_prefix' => '', 'fee' => 777, 'minimum_order' => 0, 'geometry' => $zone->geometry, 'priority' => 0]]];
    $this->actingAs($u)->putJson('/app/locations/'.$l->public_id.'/settings', $data)->assertRedirect()->assertSessionHasNoErrors();
    expect($l->zones()->first()->id)->toBe($zone->id);
    $second = settingsBatch($u, $l);
    publishSettings($u, $second, ['zones']);
    expect($l->zones()->count())->toBe(1)->and($zone->fresh()->fee)->toBe(777)->and($zone->fresh()->name)->toBe('Locally edited through Settings');
});

it('rejects zone identities from another location in the settings editor', function () {
    [$u, $l] = tenantFixture();
    [, $other] = tenantFixture('foreign-zone-location');
    $foreign = $other->zones()->create(['name' => 'Foreign', 'postal_prefix' => '12', 'fee' => 100, 'minimum_order' => 0]);
    $data = ['name' => $l->name, 'address' => $l->address, 'accepting_orders' => true, 'pickup_enabled' => true, 'delivery_enabled' => false, 'hours' => [], 'zones' => [['id' => $foreign->id, 'name' => 'Tampered', 'postal_prefix' => '24', 'fee' => 200, 'minimum_order' => 0]]];
    $this->actingAs($u)->putJson('/app/locations/'.$l->public_id.'/settings', $data)->assertUnprocessable()->assertJsonValidationErrors('zones');
    expect($foreign->fresh()->name)->toBe('Foreign')->and($l->zones()->count())->toBe(0);
});

it('does not silently discard unsupported polygon holes or shape extensions', function () {
    $source = publicSettingsData();
    $shape = json_decode($source['delivery_zones'][0]['shape_json'], true);
    $shape['holes'] = [$shape['path']];
    $source['delivery_zones'][0]['shape_json'] = json_encode($shape);
    $fields = app(NormalizePublicRestaurantSettings::class)->execute($source);
    expect($fields['zones']['available'])->toBeFalse()->and($fields['name']['available'])->toBeTrue();
});
