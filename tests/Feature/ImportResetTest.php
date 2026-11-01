<?php

use App\Domain\Imports\ImportPhotos;
use App\Domain\Imports\ImportRestaurantSettings;
use App\Domain\Imports\ProcessImport;
use App\Domain\Imports\PublishImport;
use App\Domain\Imports\ResetImports;
use App\Jobs\FetchGloriaFoodMenu;
use App\Models\ImportBatch;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\User;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpKernel\Exception\HttpException;

function resetImportFixture($owner, $location): ImportBatch
{
    $source = json_decode(file_get_contents(base_path('tests/Fixtures/gloriafood-menu.json')), true);
    $source['currency'] = $location->fresh()->currency;
    $batch = ImportBatch::create(['organization_id' => $location->organization_id, 'location_id' => $location->id, 'user_id' => $owner->id, 'settings' => ['size_prices' => 'base_plus_size', 'nutrition_basis' => 'per_100g', 'tax_basis_points' => 1100, 'nutrition_map' => ['9' => 'energy_kcal', '10' => 'fat_g', '11' => 'carbohydrate_g', '12' => 'protein_g', '13' => 'sugars_g', '14' => 'salt_g']]]);
    app(ProcessImport::class)->execute($batch, $source);

    return $batch->fresh();
}

function resetPublish($actor, $batch, array $resolutions = [])
{
    return app(PublishImport::class)->execute($actor, $batch, $resolutions, true, app(PublishImport::class)->reviewHash($batch));
}

function resetSettingsFixture($owner, $location, array $fields): ImportBatch
{
    $batch = resetImportFixture($owner, $location);
    $canonical = app(ProcessImport::class)->canonical($batch);
    $photo = app(ImportPhotos::class)->stage($batch, 'logo', UploadedFile::fake()->image('logo.png', 40, 40)->get(), 'https://example.test/logo.png');
    $values = ['name' => 'Imported name', 'phone' => '+40700000000', 'address' => ['address' => 'Imported address', 'city' => 'Test city', 'country' => 'RO', 'postal_code' => '123456', 'latitude' => 45.0, 'longitude' => 24.0],
        'hours' => ['timezone' => 'Europe/Bucharest', 'hours' => [['weekday' => 1, 'opens_at' => '10:00', 'closes_at' => '20:00']]],
        'zones' => [['source_id' => '100', 'name' => 'Imported zone', 'postal_prefix' => '123', 'fee' => 100, 'minimum_order' => 2000, 'geometry' => null, 'priority' => 1, 'is_active' => true]],
        'logo' => app(ImportPhotos::class)->publicPath($batch, 'logo', $photo)];
    $canonical['public_menu'] = ['logo' => $photo, 'settings_candidates' => array_map(fn ($value) => ['available' => true, 'error' => null, 'value' => $value], $values)];
    DB::table('import_snapshots')->where('batch_id', $batch->id)->update(['canonical' => Crypt::encryptString(json_encode($canonical))]);
    app(PublishImport::class)->execute($owner, $batch, [], true, app(PublishImport::class)->reviewHash($batch), $fields);

    return $batch;
}

it('optionally restores imported restaurant settings and retains source evidence', function (bool $restore) {
    Storage::fake('local');
    Storage::fake('public');
    [$owner, $location] = tenantFixture('reset-settings');
    $location->zones()->create(['name' => 'Existing zone', 'postal_prefix' => '24', 'fee' => 0, 'minimum_order' => 0, 'priority' => 1]);
    $location->restaurant()->update(['logo_path' => 'branding/local-logo.png']);
    Storage::disk('public')->put('branding/local-logo.png', 'local-logo');
    $settings = app(ImportRestaurantSettings::class);
    $before = $settings->state($location);
    $batch = resetSettingsFixture($owner, $location, ['name', 'phone', 'address', 'hours', 'zones', 'logo']);
    $after = $settings->state($location);
    $reset = app(ResetImports::class);
    $preview = $reset->preview($owner, $location);
    expect($preview['settings_fields'])->toHaveCount(6)->and($preview['preserved_settings'])->toBe([]);
    $reset->execute($owner, $location, $preview['review_hash'], $restore);
    expect($settings->state($location))->toBe($restore ? $before : $after);
    Storage::disk('local')->assertExists(app(ProcessImport::class)->canonical($batch)['public_menu']['logo']['path']);
    Storage::disk('public')->assertExists('branding/local-logo.png');
    if ($restore) {
        Storage::disk('public')->assertMissing($after['logo']['value']);
    } else {
        Storage::disk('public')->assertExists($after['logo']['value']);
    }
})->with([true, false]);

it('preserves settings edited after import while restoring other eligible fields', function () {
    Storage::fake('local');
    Storage::fake('public');
    [$owner, $location] = tenantFixture('reset-edited-settings');
    $name = $location->restaurant->name;
    resetSettingsFixture($owner, $location, ['name', 'phone']);
    $location->restaurant()->update(['contact_phone' => '+40799999999']);
    $reset = app(ResetImports::class);
    $preview = $reset->preview($owner, $location);
    expect($preview['settings_fields'])->toBe(['name'])->and($preview['preserved_settings'])->toBe(['phone']);
    $reset->execute($owner, $location, $preview['review_hash'], true);
    expect($location->restaurant()->first()->name)->toBe($name)->and($location->restaurant()->first()->contact_phone)->toBe('+40799999999');
});

it('removes imported menu graphs while keeping history orders local products and a clean reimport', function () {
    Storage::fake('public');
    [$owner, $location, $original] = tenantFixture('reset-all');
    $baseline = $location->menus()->first();
    $order = placeFixture($location, $original)['order'];
    $orderBefore = DB::table('orders')->where('id', $order->id)->first();
    $linesBefore = DB::table('order_items')->where('order_id', $order->id)->get()->all();
    $batch = resetImportFixture($owner, $location);
    $menu = resetPublish($owner, $batch);
    $imported = $menu->categories()->first()->items()->first();
    $path = 'menu/'.$location->organization_id.'/imports/'.$batch->public_id.'/fixture.webp';
    Storage::disk('public')->put($path, 'fixture-photo');
    $imported->update(['description' => 'Edited imported product', 'image_path' => $path]);
    $local = MenuItem::create(['organization_id' => $location->organization_id, 'category_id' => $imported->category_id, 'name' => 'Local recipe', 'price' => 5500, 'tax_basis_points' => 1100]);
    $inherited = $imported->category->modifierGroups->pluck('id')->all();
    $sourceBefore = DB::table('import_snapshots')->where('batch_id', $batch->id)->first();
    $reportBefore = DB::table('import_reports')->where('batch_id', $batch->id)->value('report');
    $reset = app(ResetImports::class);
    $preview = $reset->preview($owner, $location);
    expect($preview['products'])->toBe(4)->and($preview['local_products'])->toBe(1);
    $reset->execute($owner, $location, $preview['review_hash'], false);
    expect($batch->fresh()->status)->toBe('reset')->and($batch->fresh()->reset_id)->not->toBeNull()->and($location->menus()->first()->id)->toBe($baseline->id);
    expect(MenuItem::withTrashed()->find($imported->id))->toBeNull()->and($local->fresh()->category->menu_id)->toBe($baseline->id)->and($local->fresh()->modifierGroups->pluck('id')->intersect($inherited)->count())->toBe(count($inherited));
    expect(DB::table('orders')->where('id', $order->id)->first())->toEqual($orderBefore)->and(DB::table('order_items')->where('order_id', $order->id)->get()->all())->toEqual($linesBefore);
    expect(DB::table('import_snapshots')->where('batch_id', $batch->id)->first())->toEqual($sourceBefore)->and(DB::table('import_reports')->where('batch_id', $batch->id)->value('report'))->toBe($reportBefore)->and(DB::table('import_entity_mappings')->where('batch_id', $batch->id)->count())->toBeGreaterThan(0);
    Storage::disk('public')->assertMissing($path);
    $new = resetImportFixture($owner, $location);
    expect(app(PublishImport::class)->previous($new))->toBeNull()->and(app(PublishImport::class)->conflicts($new))->toBe([]);
    $newMenu = resetPublish($owner, $new);
    expect(MenuItem::whereHas('category', fn ($q) => $q->where('menu_id', $newMenu->id))->count())->toBe(4)->and(DB::table('import_resets')->count())->toBe(1);
    $this->actingAs($owner)->getJson('/app/locations/'.$location->public_id.'/migration-center/'.$batch->public_id)->assertOk()->assertJsonPath('status', 'reset')->assertJsonPath('report.products', 4)->assertJsonPath('reset.created_at', fn ($value) => str_ends_with($value, '+00:00'))->assertJsonStructure(['reset' => ['public_id', 'created_at']]);
});

it('cleans all imported versions and preserves current manual categories and shared photo references', function () {
    Storage::fake('public');
    [$owner, $location] = tenantFixture('reset-versions');
    $one = resetImportFixture($owner, $location);
    $first = resetPublish($owner, $one);
    $two = resetImportFixture($owner, $location);
    $second = resetPublish($owner, $two);
    $category = MenuCategory::create(['organization_id' => $location->organization_id, 'menu_id' => $second->id, 'name' => 'Manual category']);
    $path = 'menu/'.$location->organization_id.'/imports/'.$one->public_id.'/shared.webp';
    Storage::disk('public')->put($path, 'shared');
    $second->categories->first()->items->first()->update(['image_path' => $path]);
    $local = MenuItem::create(['organization_id' => $location->organization_id, 'category_id' => $category->id, 'name' => 'Local photo', 'image_path' => $path, 'price' => 1200]);
    $reset = app(ResetImports::class);
    $plan = $reset->preview($owner, $location);
    expect($plan['products'])->toBe(8);
    $reset->execute($owner, $location, $plan['review_hash'], false);
    expect(MenuItem::withTrashed()->where('organization_id', $location->organization_id)->whereNotNull('source_key')->count())->toBe(0)->and($local->fresh()->category->name)->toBe('Manual category')->and($first->fresh()->is_active)->toBeFalse()->and($second->fresh()->is_active)->toBeFalse();
    Storage::disk('public')->assertExists($path);
});

it('invalidates stale reset confirmations and blocks menus shared with another location', function () {
    [$owner, $location] = tenantFixture('reset-concurrency');
    $batch = resetImportFixture($owner, $location);
    $menu = resetPublish($owner, $batch);
    $reset = app(ResetImports::class);
    $preview = $reset->preview($owner, $location);
    $menu->categories->first()->items->first()->increment('price');
    expect(fn () => $reset->execute($owner, $location, $preview['review_hash'], false))->toThrow(HttpException::class);
    $sibling = $location->replicate(['public_id', 'slug']);
    $sibling->name = 'Shared location';
    $sibling->save();
    $sibling->menus()->attach($menu->id, ['organization_id' => $location->organization_id]);
    $preview = $reset->preview($owner, $location);
    expect($preview['shared_locations'])->toBe(['Shared location'])->and(fn () => $reset->execute($owner, $location, $preview['review_hash'], false))->toThrow(HttpException::class)->and($batch->fresh()->status)->toBe('published');
});

it('requires tenant management and explicit confirmation for reset endpoints', function () {
    [$owner, $location] = tenantFixture('reset-auth');
    [$foreign] = tenantFixture('reset-foreign');
    $batch = resetImportFixture($owner, $location);
    resetPublish($owner, $batch);
    $url = '/app/locations/'.$location->public_id.'/migration-center';
    $this->actingAs($foreign)->getJson($url.'/reset-preview')->assertForbidden();
    $this->postJson($url.'/reset', ['confirmation' => 'RESET', 'review_hash' => str_repeat('a', 64), 'restore_settings' => false])->assertForbidden();
    $this->actingAs(User::factory()->create(['is_platform_admin' => true]))->getJson($url.'/reset-preview')->assertForbidden();
    $this->actingAs($owner)->postJson($url.'/reset', ['confirmation' => 'DELETE'])->assertUnprocessable();
    $preview = $this->getJson($url.'/reset-preview')->assertOk()->assertHeader('Cache-Control', 'no-store, private')->json();
    $this->postJson($url.'/reset', ['confirmation' => 'RESET', 'review_hash' => $preview['review_hash'], 'restore_settings' => false])->assertOk();
    $this->postJson($url.'/reset', ['confirmation' => 'RESET', 'review_hash' => $preview['review_hash'], 'restore_settings' => false])->assertConflict();
    expect(DB::table('import_resets')->count())->toBe(1);
});

it('cancels queued imports without allowing old workers to overwrite reset history', function () {
    [$owner, $location] = tenantFixture('reset-queued');
    $batch = ImportBatch::create(['organization_id' => $location->organization_id, 'location_id' => $location->id, 'user_id' => $owner->id, 'settings' => [], 'credential' => 'private-key']);
    $reset = app(ResetImports::class);
    $reset->execute($owner, $location, $reset->preview($owner, $location)['review_hash'], false);
    Http::preventStrayRequests();
    (new FetchGloriaFoodMenu($batch->id))->handle();
    (new FetchGloriaFoodMenu($batch->id))->failed(new RuntimeException('late worker'));
    app(ProcessImport::class)->execute($batch, []);
    expect($batch->fresh()->status)->toBe('reset')->and($batch->fresh()->credential)->toBeNull()->and(DB::table('import_snapshots')->where('batch_id', $batch->id)->count())->toBe(0);
    Http::assertNothingSent();
});

it('keeps reset terminal when an already running fetch subsequently fails', function () {
    [$owner, $location] = tenantFixture('reset-running');
    $batch = ImportBatch::create(['organization_id' => $location->organization_id, 'location_id' => $location->id, 'user_id' => $owner->id, 'settings' => [], 'credential' => 'private-key']);
    Http::fake(function () use ($owner, $location) {
        $reset = app(ResetImports::class);
        $reset->execute($owner, $location, $reset->preview($owner, $location)['review_hash'], false);
        throw new RuntimeException('late provider failure');
    });
    (new FetchGloriaFoodMenu($batch->id))->handle();
    expect($batch->fresh()->status)->toBe('reset')->and($batch->fresh()->credential)->toBeNull()->and(DB::table('import_issues')->where('batch_id', $batch->id)->count())->toBe(0);
});

it('rolls back the entire reset if a database operation fails', function () {
    [$owner, $location] = tenantFixture('reset-atomic');
    $batch = resetImportFixture($owner, $location);
    resetPublish($owner, $batch);
    $reset = app(ResetImports::class);
    $preview = $reset->preview($owner, $location);
    $connection = DB::connection();
    $original = $connection->getEventDispatcher();
    $dispatcher = clone $original;
    $dispatcher->listen(QueryExecuted::class, function (QueryExecuted $event) {
        if (preg_match('/^delete from ["`]?menu_items["`]? /i', $event->sql)) {
            throw new RuntimeException('reset failure fixture');
        }
    });
    $connection->setEventDispatcher($dispatcher);
    try {
        expect(fn () => $reset->execute($owner, $location, $preview['review_hash'], false))->toThrow(RuntimeException::class, 'reset failure fixture');
    } finally {
        $connection->setEventDispatcher($original);
    }
    expect($batch->fresh()->status)->toBe('published')->and(DB::table('import_resets')->count())->toBe(0)->and(MenuItem::where('organization_id', $location->organization_id)->whereNotNull('source_key')->count())->toBe(4);
});
