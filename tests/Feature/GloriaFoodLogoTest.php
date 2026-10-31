<?php

use App\Domain\Imports\GloriaFoodPublicClient;
use App\Domain\Imports\ImportRestaurantSettings;
use App\Domain\Imports\ProcessImport;
use App\Domain\Imports\PublishImport;
use App\Domain\Integrations\PublicDnsResolver;
use App\Jobs\DownloadGloriaFoodPhotos;
use App\Models\ImportBatch;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

function logoSource(): array
{
    return ['id' => 900, 'restaurant_id' => 901, 'currency' => 'RON', 'categories' => [['id' => 1, 'name' => 'Mains', 'items' => [['id' => 10, 'name' => 'Dish', 'price' => 20]]]]];
}

function logoPublic(): array
{
    return ['id' => 901, 'uid' => 'ca5549f4-4c22-4fd1-9c51-3bd227b20297', 'name' => 'Logo restaurant', 'menu' => logoSource(), 'pictures' => ['restaurant_logo' => ['thumbnail_type' => 'restaurant_logo', 'entity_id' => 901, 'filename' => '11111111-1111-4111-8111-111111111111.png', 'density' => 2]]];
}

function logoBatch($u, $l, ?array $public = null, ?string $image = null): ImportBatch
{
    Http::fake([GloriaFoodPublicClient::API => Http::response(['restaurant' => $public ?? logoPublic()], 200, ['Content-Type' => 'application/json']), 'www.fbgcdn.com/*' => Http::response($image ?? UploadedFile::fake()->image('logo.png', 180, 60)->get(), 200, ['Content-Type' => 'image/png'])]);
    $batch = ImportBatch::create(['organization_id' => $l->organization_id, 'location_id' => $l->id, 'user_id' => $u->id, 'settings' => ['public_menu_url' => 'https://www.foodbooking.com/ordering/restaurant/menu?restaurant_uid=ca5549f4-4c22-4fd1-9c51-3bd227b20297', 'size_prices' => 'base_plus_size', 'nutrition_basis' => 'per_100g', 'tax_basis_points' => 0]]);
    app(ProcessImport::class)->execute($batch, logoSource());

    return $batch->fresh();
}

function publishLogo($u, $b, array $fields = ['logo']): void
{
    app(PublishImport::class)->execute($u, $b, [], true, app(PublishImport::class)->reviewHash($b), $fields);
}

beforeEach(function () {
    Storage::fake('local');
    Storage::fake('public');
    $this->mock(PublicDnsResolver::class)->shouldReceive('addresses')->andReturn(['1.1.1.1']);
});

it('stages a logo privately and leaves the existing logo untouched when not selected', function () {
    [$u, $l] = tenantFixture();
    $l->restaurant()->update(['logo_path' => 'branding/old.png']);
    Storage::disk('public')->put('branding/old.png', UploadedFile::fake()->image('old.png')->get());
    $b = logoBatch($u, $l);
    $menu = app(ProcessImport::class)->canonical($b);
    $candidate = $menu['public_menu']['settings_candidates']['logo'];
    expect($candidate['available'])->toBeTrue()->and($menu['public_menu']['downloaded'])->toBe(0);
    Storage::disk('local')->assertExists($menu['public_menu']['logo']['path']);
    expect(Storage::disk('public')->allFiles())->toBe(['branding/old.png']);
    $preview = collect(app(ImportRestaurantSettings::class)->preview($b, $menu))->firstWhere('key', 'logo');
    expect($preview['current'])->toBe('branding/old.png')->and($preview['proposed_image_url'])->toEndWith('/'.$b->public_id.'/logo');
    $url = '/app/locations/'.$l->public_id.'/migration-center/'.$b->public_id.'/logo';
    $response = $this->actingAs($u)->get($url)->assertOk()->assertHeader('Content-Type', 'image/webp')->assertHeader('Cache-Control', 'no-store, private')->assertHeader('X-Content-Type-Options', 'nosniff');
    $size = getimagesizefromstring($response->getContent());
    expect([$size[0], $size[1]])->toBe([180, 60]);
    [$other] = tenantFixture('foreign-logo-preview');
    $this->actingAs($other)->get($url)->assertForbidden();
    publishLogo($u, $b, []);
    expect($l->restaurant()->first()->logo_path)->toBe('branding/old.png');
    expect(Storage::disk('public')->allFiles())->toBe(['branding/old.png']);
});

it('applies only the approved staged logo across shared locations and restores the previous logo on rollback', function () {
    [$u, $l] = tenantFixture();
    $old = 'branding/old.png';
    $l->restaurant()->update(['logo_path' => $old]);
    Storage::disk('public')->put($old, 'previous logo bytes');
    $sibling = $l->replicate(['public_id', 'slug']);
    $sibling->name = 'Second';
    $sibling->save();
    $b = logoBatch($u, $l);
    $this->actingAs($u)->postJson('/app/locations/'.$l->public_id.'/migration-center/'.$b->public_id.'/publish', ['acknowledged' => true, 'resolutions' => [], 'review_hash' => app(PublishImport::class)->reviewHash($b), 'settings_fields' => ['logo'], 'logo_path' => 'https://untrusted.invalid/logo.svg'])->assertOk();
    $path = $l->restaurant()->first()->logo_path;
    expect($path)->toStartWith('branding/'.$l->organization_id.'/')->and($sibling->restaurant()->first()->logo_path)->toBe($path);
    Storage::disk('public')->assertExists($path);
    Storage::disk('public')->assertExists($old);
    $report = json_decode(DB::table('import_reports')->where('batch_id', $b->id)->value('report'), true);
    expect($report['settings_changes']['logo'])->toBe(['before' => $old, 'after' => $path]);
    app(PublishImport::class)->revert($u, $b);
    expect($l->restaurant()->first()->logo_path)->toBe($old);
    Storage::disk('public')->assertExists($old);
});

it('does not use arbitrary filenames, theme images or another entity as a restaurant logo', function ($kind) {
    [$u, $l] = tenantFixture();
    $public = logoPublic();
    match ($kind) {
        'filename' => $public['pictures']['restaurant_logo']['filename'] = '../../private.png',
        'theme' => $public['pictures']['restaurant_logo']['theme_set_id'] = 990,
        'entity' => $public['pictures']['restaurant_logo']['entity_id'] = 999,
        'type' => $public['pictures']['restaurant_logo']['thumbnail_type'] = 'desktop_widget',
        'absent' => $public['pictures'] = [],
    };
    $b = logoBatch($u, $l, $public);
    expect(app(ProcessImport::class)->canonical($b)['public_menu']['settings_candidates']['logo']['available'])->toBeFalse();
    Http::assertSentCount(1);
    expect(fn () => publishLogo($u, $b))->toThrow(ValidationException::class);
    publishLogo($u, $b, []);
})->with(['filename', 'theme', 'entity', 'type', 'absent']);

it('keeps a failed logo download optional and does not count it as a product photo', function () {
    [$u, $l] = tenantFixture();
    $b = logoBatch($u, $l, image: '<html>not an image</html>');
    $meta = app(ProcessImport::class)->canonical($b)['public_menu'];
    expect($b->status)->toBe('ready')->and($meta['settings_candidates']['logo']['error'])->toBe('logo_download')->and($meta['failed'])->toBe(0);
    $this->actingAs($u)->get('/app/locations/'.$l->public_id.'/migration-center/'.$b->public_id.'/logo')->assertNotFound();
    publishLogo($u, $b, []);
});

it('queues logo staging without reindexing numeric product IDs and reuses verified evidence on revalidation', function () {
    [$u, $l] = tenantFixture();
    Queue::fake();
    $public = logoPublic();
    $public['pictures']['menu_item-10'] = ['thumbnail_type' => 'menu_item', 'entity_id' => 10, 'filename' => '22222222-2222-4222-8222-222222222222.jpg'];
    $b = logoBatch($u, $l, $public);
    expect($b->status)->toBe('enriching');
    Queue::assertPushed(DownloadGloriaFoodPhotos::class);
    (new DownloadGloriaFoodPhotos($b->id))->handle();
    $menu = app(ProcessImport::class)->canonical($b);
    expect($menu['categories'][0]['items'][0])->toHaveKey('photo')->and($menu['public_menu']['settings_candidates']['logo']['available'])->toBeTrue();
    expect($menu['public_menu']['downloaded'])->toBe(1)->and($menu['public_menu']['pending'])->toBe(0);
    $count = count(Http::recorded());
    app(ProcessImport::class)->execute($b->fresh(), logoSource());
    Http::assertSentCount($count);
    expect(app(ProcessImport::class)->canonical($b)['public_menu']['logo'])->toBe($menu['public_menu']['logo']);
});

it('rejects stale logo approval and protects later logo edits from rollback', function () {
    [$u, $l] = tenantFixture();
    $b = logoBatch($u, $l);
    $hash = app(PublishImport::class)->reviewHash($b);
    $l->restaurant()->update(['logo_path' => 'branding/new-local.png']);
    try {
        app(PublishImport::class)->execute($u, $b, [], true, $hash, ['logo']);
        $this->fail('Expected stale approval');
    } catch (HttpException $e) {
        expect($e->getStatusCode())->toBe(409);
    }
    publishLogo($u, $b);
    $l->restaurant()->update(['logo_path' => 'branding/later.png']);
    try {
        app(PublishImport::class)->revert($u, $b);
        $this->fail('Expected local edit protection');
    } catch (HttpException $e) {
        expect($e->getStatusCode())->toBe(409);
    }
    expect($l->restaurant()->first()->logo_path)->toBe('branding/later.png');
});

it('removes copied logo files if menu publication rolls back', function () {
    [$u, $l] = tenantFixture();
    $b = logoBatch($u, $l);
    $connection = DB::connection();
    $dispatcher = $connection->getEventDispatcher();
    $isolated = clone $dispatcher;
    $connection->setEventDispatcher($isolated);
    $path = app(ProcessImport::class)->canonical($b)['public_menu']['settings_candidates']['logo']['value'];
    $isolated->listen(QueryExecuted::class, function (QueryExecuted $query) use ($path) {
        if (str_starts_with(strtolower(ltrim($query->sql)), 'insert') && str_contains($query->sql, 'import_publications')) {
            // Fail after the real publication insert, with no driver-specific DDL or implicit commit.
            Storage::disk('public')->assertExists($path);
            throw new RuntimeException('Forced publication failure');
        }
    });
    try {
        expect(fn () => publishLogo($u, $b))->toThrow(RuntimeException::class, 'Forced publication failure');
    } finally {
        $connection->setEventDispatcher($dispatcher);
    }
    expect(DB::table('import_publications')->where('batch_id', $b->id)->exists())->toBeFalse();
    expect($b->fresh()->status)->toBe('ready')->and($l->restaurant()->first()->logo_path)->toBeNull();
    expect(Storage::disk('public')->allFiles())->toBe([]);
});

it('retains a committed logo when an after-commit callback fails', function () {
    [$u, $l] = tenantFixture();
    $b = logoBatch($u, $l);
    Cache::shouldReceive('forget')->andThrow(new RuntimeException('cache unavailable'));
    expect(fn () => publishLogo($u, $b))->toThrow(RuntimeException::class);
    expect($b->fresh()->status)->toBe('published');
    Storage::disk('public')->assertExists($l->restaurant()->first()->logo_path);
});

it('rejects tampered staged logo bytes before applying changes', function () {
    [$u, $l] = tenantFixture();
    $b = logoBatch($u, $l);
    $photo = app(ProcessImport::class)->canonical($b)['public_menu']['logo'];
    Storage::disk('local')->put($photo['path'], 'tampered');
    expect(fn () => publishLogo($u, $b))->toThrow(RuntimeException::class, 'public_photo_checksum');
    expect($l->restaurant()->first()->logo_path)->toBeNull()->and($b->fresh()->status)->toBe('ready');
});

it('retains the choices of older staged imports that have no logo candidate', function () {
    [$u, $l] = tenantFixture();
    $b = logoBatch($u, $l);
    $menu = app(ProcessImport::class)->canonical($b);
    unset($menu['public_menu']['settings_candidates']['logo'], $menu['public_menu']['logo']);
    DB::table('import_snapshots')->where('batch_id', $b->id)->update(['canonical' => Crypt::encryptString(json_encode($menu))]);
    expect(app(ImportRestaurantSettings::class)->preview($b, $menu))->toHaveCount(5);
    publishLogo($u, $b, []);
});

it('preserves transparency and proportions when re-encoding a logo', function () {
    [$u, $l] = tenantFixture();
    $image = imagecreatetruecolor(120, 40);
    imagealphablending($image, false);
    imagesavealpha($image, true);
    imagefill($image, 0, 0, imagecolorallocatealpha($image, 0, 0, 0, 127));
    imagefilledrectangle($image, 40, 10, 80, 30, imagecolorallocatealpha($image, 200, 0, 0, 0));
    ob_start();
    imagepng($image);
    $bytes = ob_get_clean();
    imagedestroy($image);
    $b = logoBatch($u, $l, image: $bytes);
    $photo = app(ProcessImport::class)->canonical($b)['public_menu']['logo'];
    $result = imagecreatefromstring(Storage::disk('local')->get($photo['path']));
    expect(imagesx($result))->toBe(120)->and(imagesy($result))->toBe(40)->and(imagecolorsforindex($result, imagecolorat($result, 0, 0))['alpha'])->toBe(127);
    imagedestroy($result);
});

it('keeps the original logo when a queued logo download is stopped', function () {
    [$u, $l] = tenantFixture();
    $l->restaurant()->update(['logo_path' => 'branding/old.png']);
    Queue::fake();
    $b = logoBatch($u, $l);
    (new DownloadGloriaFoodPhotos($b->id))->failed(new RuntimeException('worker stopped'));
    expect($b->fresh()->status)->toBe('ready')->and(app(ProcessImport::class)->canonical($b)['public_menu']['settings_candidates']['logo']['error'])->toBe('logo_download');
    publishLogo($u, $b, []);
    expect($l->restaurant()->first()->logo_path)->toBe('branding/old.png');
});

it('accepts an explicit unthemed logo record without treating other pictures as logos', function () {
    [$u, $l] = tenantFixture();
    $public = logoPublic();
    $public['pictures'] = ['logo' => [...$public['pictures']['restaurant_logo'], 'thumbnail_type' => 'logo', 'entity_id' => 0]];
    $b = logoBatch($u, $l, $public);
    expect(app(ProcessImport::class)->canonical($b)['public_menu']['settings_candidates']['logo']['available'])->toBeTrue();
});
