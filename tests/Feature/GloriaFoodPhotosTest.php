<?php

use App\Domain\Imports\GloriaFoodPublicClient;
use App\Domain\Imports\ProcessImport;
use App\Domain\Imports\PublishImport;
use App\Domain\Integrations\PublicDnsResolver;
use App\Jobs\DownloadGloriaFoodPhotos;
use App\Models\ImportBatch;
use App\Models\MenuItem;
use Illuminate\Database\QueryException;
use Illuminate\Http\Client\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

function publicPhotoUrl(): string
{
    return 'https://www.foodbooking.com/ordering/restaurant/menu?restaurant_uid=ca5549f4-4c22-4fd1-9c51-3bd227b20297';
}

function publicPhotoSource(): array
{
    return ['id' => 169612, 'restaurant_id' => 170737, 'currency' => 'RON', 'categories' => [['id' => 11, 'name' => 'Mains', 'items' => [['id' => 101, 'name' => 'Test pizza', 'price' => 20], ['id' => 102, 'name' => 'No photo dish', 'price' => 30]]]]];
}

function publicPhotoResponse(?array $source = null): array
{
    $source ??= publicPhotoSource();

    return ['restaurant' => ['id' => 170737, 'uid' => 'ca5549f4-4c22-4fd1-9c51-3bd227b20297', 'name' => 'Public restaurant', 'address' => 'Public address', 'menu' => $source, 'pictures' => ['menu_item-101' => ['thumbnail_type' => 'menu_item', 'entity_id' => 101, 'filename' => '11111111-1111-4111-8111-111111111111.jpg', 'density' => 3]]]];
}

function photoBatch($u, $l, ?array $source = null): ImportBatch
{
    $b = ImportBatch::create(['organization_id' => $l->organization_id, 'location_id' => $l->id, 'user_id' => $u->id, 'settings' => ['public_menu_url' => publicPhotoUrl(), 'size_prices' => 'base_plus_size', 'nutrition_basis' => 'per_100g', 'tax_basis_points' => 0]]);
    app(ProcessImport::class)->execute($b, $source ?? publicPhotoSource());

    return $b->fresh();
}

beforeEach(function () {
    Storage::fake('local');
    Storage::fake('public');
    $this->mock(PublicDnsResolver::class)->shouldReceive('addresses')->andReturn(['1.1.1.1']);
    $file = UploadedFile::fake()->image('photo.jpg', 30, 20);
    $this->photoBytes = $file->get();
});

it('keeps the original import flow without public requests or photo jobs when the URL is absent', function () {
    [$u, $l] = tenantFixture();
    Http::fake();
    Queue::fake();
    $b = ImportBatch::create(['organization_id' => $l->organization_id, 'location_id' => $l->id, 'user_id' => $u->id, 'settings' => ['size_prices' => 'base_plus_size', 'nutrition_basis' => 'per_100g', 'tax_basis_points' => 0]]);
    app(ProcessImport::class)->execute($b, publicPhotoSource());
    expect($b->fresh()->status)->toBe('ready')->and(app(ProcessImport::class)->canonical($b))->not->toHaveKey('public_menu');
    Http::assertNothingSent();
    Queue::assertNothingPushed();
});

it('stages exact ID photos privately, previews with tenant authorization and publishes only after approval', function () {
    [$u, $l, $old] = tenantFixture();
    Http::fake([GloriaFoodPublicClient::API => Http::response(publicPhotoResponse(), 200, ['Content-Type' => 'application/json']), 'www.fbgcdn.com/*' => Http::response($this->photoBytes, 200, ['Content-Type' => 'image/jpeg'])]);
    $b = photoBatch($u, $l);
    $menu = app(ProcessImport::class)->canonical($b);
    expect($menu['public_menu'])->toMatchArray(['matched' => 1, 'downloaded' => 1, 'missing' => 1, 'failed' => 0]);
    expect($l->menus()->first()->categories->first()->items->first()->id)->toBe($old->id);
    expect(Storage::disk('public')->allFiles())->toBe([]);
    $photo = $menu['categories'][0]['items'][0]['photo'];
    Storage::disk('local')->assertExists($photo['path']);
    Http::assertSent(fn (Request $r) => $r->url() === GloriaFoodPublicClient::CDN.'11111111-1111-4111-8111-111111111111_d3.jpg');
    $url = '/app/locations/'.$l->public_id.'/migration-center/'.$b->public_id.'/photos/101';
    $this->actingAs($u)->get($url)->assertOk()->assertHeader('Content-Type', 'image/webp')->assertHeader('X-Content-Type-Options', 'nosniff');
    [$other] = tenantFixture('other-photo-tenant');
    $this->actingAs($other)->get($url)->assertForbidden();
    $published = app(PublishImport::class)->execute($u, $b, [], true, app(PublishImport::class)->reviewHash($b));
    $item = MenuItem::whereHas('category', fn ($q) => $q->where('menu_id', $published->id))->where('source_key', '101')->firstOrFail();
    Storage::disk('public')->assertExists($item->image_path);
    expect($l->fresh()->address)->toBe('Test street');
    expect(getimagesizefromstring(Storage::disk('public')->get($item->image_path))['mime'])->toBe('image/webp');
});

it('retains all 386 source products and 24 categories when the public menu contains a subset', function () {
    [$u, $l] = tenantFixture();
    $source = publicPhotoSource();
    $source['categories'] = [];
    foreach (range(1, 24) as $c) {
        $source['categories'][] = ['id' => $c, 'name' => 'Category '.$c, 'items' => array_map(fn ($i) => ['id' => ($c - 1) * 16 + $i, 'name' => 'Dish '.(($c - 1) * 16 + $i), 'price' => 10], range(1, $c === 24 ? 18 : 16))];
    }
    $public = publicPhotoResponse();
    $public['restaurant']['menu']['categories'] = array_slice($source['categories'], 0, 7);
    $public['restaurant']['menu']['categories'][6]['items'] = array_slice($public['restaurant']['menu']['categories'][6]['items'], 0, 13);
    $public['restaurant']['pictures'] = [];
    Http::fake([GloriaFoodPublicClient::API => Http::response($public, 200, ['Content-Type' => 'application/json'])]);
    $b = photoBatch($u, $l, $source);
    $report = json_decode(DB::table('import_reports')->where('batch_id', $b->id)->value('report'), true);
    expect($report)->toMatchArray(['products' => 386, 'categories' => 24, 'blockers' => 0]);
    expect($report['public_menu']['missing'])->toBe(386);
    Http::assertSentCount(1);
});

it('blocks different source menus restaurants and currencies without requesting images', function ($field) {
    [$u, $l] = tenantFixture();
    $public = publicPhotoResponse();
    if ($field === 'restaurant') {
        $public['restaurant']['id'] = 1;
    } else {
        $public['restaurant']['menu'][$field] = $field === 'currency' ? 'EUR' : 1;
    }
    Http::fake([GloriaFoodPublicClient::API => Http::response($public, 200, ['Content-Type' => 'application/json'])]);
    $b = photoBatch($u, $l);
    expect(DB::table('import_issues')->where('batch_id', $b->id)->where('code', 'public_menu_mismatch')->where('severity', 'blocker')->exists())->toBeTrue();
    expect(fn () => app(PublishImport::class)->execute($u, $b, [], true, app(PublishImport::class)->reviewHash($b)))->toThrow(ValidationException::class);
    Http::assertSentCount(1);
})->with(['id', 'restaurant', 'currency']);

it('does not guess matches for changed names or duplicate public IDs', function ($duplicate) {
    [$u, $l] = tenantFixture();
    $public = publicPhotoResponse();
    if ($duplicate) {
        $public['restaurant']['menu']['categories'][0]['items'][] = $public['restaurant']['menu']['categories'][0]['items'][0];
    } else {
        $public['restaurant']['menu']['categories'][0]['items'][0]['name'] = 'A different dish';
    }
    Http::fake([GloriaFoodPublicClient::API => Http::response($public, 200, ['Content-Type' => 'application/json'])]);
    $b = photoBatch($u, $l);
    expect(app(ProcessImport::class)->canonical($b)['public_menu']['matched'])->toBe(0);
    Http::assertSentCount(1);
})->with([true, false]);

it('uses an exact photo entity ID for a Fetch Menu product omitted from the visible public list', function () {
    [$u, $l] = tenantFixture();
    $public = publicPhotoResponse();
    $public['restaurant']['menu']['categories'] = [];
    Http::fake([GloriaFoodPublicClient::API => Http::response($public, 200, ['Content-Type' => 'application/json']), 'www.fbgcdn.com/*' => Http::response($this->photoBytes, 200, ['Content-Type' => 'image/jpeg'])]);
    $b = photoBatch($u, $l);
    $menu = app(ProcessImport::class)->canonical($b);
    expect($menu['categories'][0]['items'])->toHaveCount(2);
    expect($menu['public_menu'])->toMatchArray(['matched' => 1, 'downloaded' => 1, 'missing' => 1]);
});

it('rejects non-raster oversized and redirected image responses without losing products', function ($kind) {
    [$u, $l] = tenantFixture();
    $body = $kind === 'oversized' ? str_repeat('x', 3145729) : '<svg onload="alert(1)"/>';
    Http::fake([GloriaFoodPublicClient::API => Http::response(publicPhotoResponse(), 200, ['Content-Type' => 'application/json']), 'www.fbgcdn.com/*' => Http::response($body, $kind === 'redirect' ? 302 : 200, ['Content-Type' => $kind === 'svg' ? 'image/svg+xml' : 'image/jpeg', 'Location' => 'http://127.0.0.1/'])]);
    $b = photoBatch($u, $l);
    $menu = app(ProcessImport::class)->canonical($b);
    expect($menu['public_menu'])->toMatchArray(['downloaded' => 0, 'failed' => 1]);
    expect($menu['categories'][0]['items'])->toHaveCount(2);
    expect(Storage::disk('local')->allFiles())->toBe([]);
})->with(['svg', 'oversized', 'redirect']);

it('rejects foreign or credential-bearing menu URLs before creating a batch', function ($url) {
    [$u, $l] = tenantFixture();
    Http::fake();
    $this->actingAs($u)->postJson('/app/locations/'.$l->public_id.'/migration-center', ['source' => 'api', 'key' => 'private-key', 'authorized' => true, 'size_prices' => 'base_plus_size', 'nutrition_basis' => 'per_100g', 'tax_basis_points' => 0, 'public_menu_url' => $url])->assertUnprocessable()->assertJsonValidationErrors('public_menu_url');
    expect(ImportBatch::count())->toBe(0);
    Http::assertNothingSent();
})->with(['https://127.0.0.1/ordering/?restaurant_uid=ca5549f4-4c22-4fd1-9c51-3bd227b20297', 'https://www.foodbooking.com.evil.example/ordering/?restaurant_uid=ca5549f4-4c22-4fd1-9c51-3bd227b20297', 'https://user:pass@www.foodbooking.com/ordering/?restaurant_uid=ca5549f4-4c22-4fd1-9c51-3bd227b20297', publicPhotoUrl().'&redirect=http://localhost/']);

it('blocks mixed private DNS answers before any public-menu request', function () {
    [$u, $l] = tenantFixture();
    $dns = Mockery::mock(PublicDnsResolver::class);
    $dns->shouldReceive('addresses')->andReturn(['1.1.1.1', '127.0.0.1']);
    app()->instance(PublicDnsResolver::class, $dns);
    Http::fake();
    $b = photoBatch($u, $l);
    expect(app(ProcessImport::class)->canonical($b)['public_menu']['status'])->toBe('unavailable');
    Http::assertNothingSent();
});

it('revalidates staged photos without external requests and expires their private files', function () {
    [$u, $l] = tenantFixture();
    Http::fake([GloriaFoodPublicClient::API => Http::response(publicPhotoResponse(), 200, ['Content-Type' => 'application/json']), 'www.fbgcdn.com/*' => Http::response($this->photoBytes, 200, ['Content-Type' => 'image/jpeg'])]);
    $b = photoBatch($u, $l);
    app(ProcessImport::class)->execute($b, publicPhotoSource());
    Http::assertSentCount(2);
    expect(app(ProcessImport::class)->canonical($b)['public_menu']['downloaded'])->toBe(1);
    $b->update(['created_at' => now()->subDays(40)]);
    $this->artisan('zionfood:imports-prune')->assertSuccessful();
    expect(Storage::disk('local')->allFiles())->toBe([]);
});

it('uses pinned TLS requests without redirects proxies or provider API credentials', function () {
    [$u, $l] = tenantFixture();
    Http::fake(function (Request $request, array $options) {
        expect($options['verify'])->toBeTrue()->and($options['allow_redirects'])->toBeFalse()->and($options['proxy'])->toBe('');
        expect($options['curl'][CURLOPT_RESOLVE][0])->toEndWith(':443:1.1.1.1');
        expect($request->hasHeader('Authorization'))->toBeFalse();

        return $request->url() === GloriaFoodPublicClient::API ? Http::response(publicPhotoResponse(), 200, ['Content-Type' => 'application/json']) : Http::response($this->photoBytes, 200, ['Content-Type' => 'image/jpeg']);
    });
    expect(app(ProcessImport::class)->canonical(photoBatch($u, $l))['public_menu']['downloaded'])->toBe(1);
});

it('refuses publication if a staged photo was changed after preview', function () {
    [$u, $l] = tenantFixture();
    $previous = $l->menus()->first()->id;
    Http::fake([GloriaFoodPublicClient::API => Http::response(publicPhotoResponse(), 200, ['Content-Type' => 'application/json']), 'www.fbgcdn.com/*' => Http::response($this->photoBytes, 200, ['Content-Type' => 'image/jpeg'])]);
    $b = photoBatch($u, $l);
    $photo = app(ProcessImport::class)->canonical($b)['categories'][0]['items'][0]['photo'];
    Storage::disk('local')->put($photo['path'], 'changed after review');
    expect(fn () => app(PublishImport::class)->execute($u, $b, [], true, app(PublishImport::class)->reviewHash($b)))->toThrow(RuntimeException::class);
    expect($l->menus()->first()->id)->toBe($previous)->and(Storage::disk('public')->allFiles())->toBe([]);
});

it('retains a locally uploaded photo when source fields are replaced on reimport', function () {
    [$u, $l] = tenantFixture();
    Http::fake([GloriaFoodPublicClient::API => Http::response(publicPhotoResponse(), 200, ['Content-Type' => 'application/json']), 'www.fbgcdn.com/*' => Http::response($this->photoBytes, 200, ['Content-Type' => 'image/jpeg'])]);
    $first = photoBatch($u, $l);
    $action = app(PublishImport::class);
    $menu = $action->execute($u, $first, [], true, $action->reviewHash($first));
    $menu->categories->first()->items->firstWhere('source_key', '101')->update(['image_path' => 'menu/owner-photo.webp']);
    $second = photoBatch($u, $l);
    $published = $action->execute($u, $second, ['101' => 'use_source'], true, $action->reviewHash($second));
    expect($published->categories->first()->items->firstWhere('source_key', '101')->image_path)->toBe('menu/owner-photo.webp');
});

it('does not delete a committed photo when cache invalidation fails after publication', function () {
    [$u, $l] = tenantFixture();
    Http::fake([GloriaFoodPublicClient::API => Http::response(publicPhotoResponse(), 200, ['Content-Type' => 'application/json']), 'www.fbgcdn.com/*' => Http::response($this->photoBytes, 200, ['Content-Type' => 'image/jpeg'])]);
    $b = photoBatch($u, $l);
    Cache::partialMock()->shouldReceive('forget')->andThrow(new RuntimeException('cache unavailable'));
    expect(fn () => app(PublishImport::class)->execute($u, $b, [], true, app(PublishImport::class)->reviewHash($b)))->toThrow(RuntimeException::class);
    expect($b->fresh()->status)->toBe('published');
    $photo = $l->menus()->first()->categories->first()->items->firstWhere('source_key', '101')->image_path;
    Storage::disk('public')->assertExists($photo);
});

it('removes newly copied public files if a database write rolls publication back', function () {
    [$u, $l] = tenantFixture();
    $previous = $l->menus()->first()->id;
    Http::fake([GloriaFoodPublicClient::API => Http::response(publicPhotoResponse(), 200, ['Content-Type' => 'application/json']), 'www.fbgcdn.com/*' => Http::response($this->photoBytes, 200, ['Content-Type' => 'image/jpeg'])]);
    $b = photoBatch($u, $l);
    $menu = app(ProcessImport::class)->canonical($b);
    $menu['categories'][0]['items'][0]['variants'] = [['name' => null, 'price' => 100, 'is_default' => false, 'nutrition' => null, 'groups' => []]];
    DB::table('import_snapshots')->where('batch_id', $b->id)->update(['canonical' => Crypt::encryptString(json_encode($menu))]);
    expect(fn () => app(PublishImport::class)->execute($u, $b, [], true, app(PublishImport::class)->reviewHash($b)))->toThrow(QueryException::class);
    expect($b->fresh()->status)->toBe('ready')->and($l->menus()->first()->id)->toBe($previous)->and(Storage::disk('public')->allFiles())->toBe([]);
});

it('reports photo traffic limits without dropping source products', function () {
    [$u, $l] = tenantFixture();
    $source = publicPhotoSource();
    $source['categories'][0]['items'] = array_map(fn ($i) => ['id' => $i, 'name' => 'Dish '.$i, 'price' => 10], range(1, 201));
    $public = publicPhotoResponse($source);
    $public['restaurant']['pictures'] = [];
    foreach (range(1, 201) as $i) {
        $public['restaurant']['pictures']['menu_item-'.$i] = ['thumbnail_type' => 'menu_item', 'entity_id' => $i, 'filename' => '11111111-1111-4111-8111-111111111111.jpg', 'density' => 1];
    }
    Http::fake([GloriaFoodPublicClient::API => Http::response($public, 200, ['Content-Type' => 'application/json']), 'www.fbgcdn.com/*' => Http::response($this->photoBytes, 200, ['Content-Type' => 'image/jpeg'])]);
    Queue::fake();
    $b = photoBatch($u, $l, $source);
    $menu = app(ProcessImport::class)->canonical($b);
    expect($b->status)->toBe('enriching');
    $menu['public_menu']['bytes'] = 134217728;
    DB::table('import_snapshots')->where('batch_id', $b->id)->update(['canonical' => Crypt::encryptString(json_encode($menu))]);
    (new DownloadGloriaFoodPhotos($b->id))->handle();
    $menu = app(ProcessImport::class)->canonical($b);
    expect($menu['categories'][0]['items'])->toHaveCount(201);
    expect($menu['public_menu'])->toMatchArray(['matched' => 201, 'downloaded' => 0, 'limited' => 201]);
    Http::assertSentCount(1);
});

it('continues queued photo chunks without an import key and safely completes failed or duplicate jobs', function () {
    [$u, $l] = tenantFixture();
    Http::fake([GloriaFoodPublicClient::API => Http::response(publicPhotoResponse(), 200, ['Content-Type' => 'application/json']), 'www.fbgcdn.com/*' => Http::response($this->photoBytes, 200, ['Content-Type' => 'image/jpeg'])]);
    Queue::fake();
    $b = photoBatch($u, $l);
    expect($b->status)->toBe('enriching')->and($b->credential)->toBeNull();
    Queue::assertPushed(DownloadGloriaFoodPhotos::class);
    (new DownloadGloriaFoodPhotos($b->id))->failed(new RuntimeException('worker stopped'));
    expect($b->fresh()->status)->toBe('ready')->and(app(ProcessImport::class)->canonical($b)['public_menu']['failed'])->toBe(1);
    (new DownloadGloriaFoodPhotos($b->id))->handle();
    Http::assertSentCount(1);
});

it('resumes after five photos and makes duplicate completed jobs harmless', function () {
    [$u, $l] = tenantFixture();
    $source = publicPhotoSource();
    $source['categories'][0]['items'] = array_map(fn ($i) => ['id' => $i, 'name' => 'Dish '.$i, 'price' => 10], range(1, 7));
    $public = publicPhotoResponse($source);
    $public['restaurant']['pictures'] = [];
    foreach (range(1, 7) as $i) {
        $public['restaurant']['pictures']['menu_item-'.$i] = ['thumbnail_type' => 'menu_item', 'entity_id' => $i, 'filename' => '11111111-1111-4111-8111-111111111111.jpg', 'density' => 1];
    }
    Http::fake([GloriaFoodPublicClient::API => Http::response($public, 200, ['Content-Type' => 'application/json']), 'www.fbgcdn.com/*' => Http::response($this->photoBytes, 200, ['Content-Type' => 'image/jpeg'])]);
    Queue::fake();
    $b = photoBatch($u, $l, $source);
    (new DownloadGloriaFoodPhotos($b->id))->handle();
    expect($b->fresh()->status)->toBe('enriching')->and(app(ProcessImport::class)->canonical($b)['public_menu'])->toMatchArray(['downloaded' => 5, 'pending' => 2]);
    (new DownloadGloriaFoodPhotos($b->id))->handle();
    expect($b->fresh()->status)->toBe('ready')->and(app(ProcessImport::class)->canonical($b)['public_menu'])->toMatchArray(['downloaded' => 7, 'pending' => 0]);
    (new DownloadGloriaFoodPhotos($b->id))->handle();
    Http::assertSentCount(8);
});
