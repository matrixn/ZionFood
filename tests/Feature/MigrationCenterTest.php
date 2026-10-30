<?php

use App\Domain\Imports\GloriaFoodAdapter;
use App\Domain\Imports\ImportVerification;
use App\Domain\Imports\ProcessImport;
use App\Domain\Imports\PublishImport;
use App\Domain\Menus\Actions\UpdateMenuItem;
use App\Domain\Menus\EffectiveModifiers;
use App\Domain\Menus\PublicMenuTree;
use App\Domain\Orders\Actions\PlaceOrderAction;
use App\Jobs\FetchGloriaFoodMenu;
use App\Models\ImportBatch;
use App\Models\Menu;
use App\Models\MenuItem;
use App\Models\ModifierGroup;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

function migrationSource(): array
{
    return json_decode(file_get_contents(base_path('tests/Fixtures/gloriafood-menu.json')), true);
}
function migrationBatch($user, $location, ?array $source = null): ImportBatch
{
    $batch = ImportBatch::create(['organization_id' => $location->organization_id, 'location_id' => $location->id, 'user_id' => $user->id, 'settings' => ['size_prices' => 'base_plus_size', 'nutrition_basis' => 'per_100g', 'tax_basis_points' => 1100, 'nutrition_map' => ['9' => 'energy_kcal', '10' => 'fat_g', '11' => 'carbohydrate_g', '12' => 'protein_g', '13' => 'sugars_g', '14' => 'salt_g']]]);
    $source ??= migrationSource();
    $source['currency'] = $location->fresh()->currency;
    app(ProcessImport::class)->execute($batch, $source);

    return $batch->fresh();
}
function publishBatch($u, $b, array $resolutions = [])
{
    return app(PublishImport::class)->execute($u, $b, $resolutions, true, app(PublishImport::class)->reviewHash($b));
}

it('still rejects malformed nonempty provider restaurant IDs', function ($value) {
    $source = migrationSource();
    $source['restaurant_id'] = $value;
    expect(fn () => app(GloriaFoodAdapter::class)->identity($source))->toThrow(ValidationException::class);
})->with([0, -1, 'not-an-id', [[]]]);

it('requires a source menu ID even when the provider restaurant ID is absent', function () {
    expect(fn () => app(GloriaFoodAdapter::class)->identity([]))->toThrow(ValidationException::class);
});

it('accepts fetched menus without a restaurant ID while preserving the original payload and credential cleanup', function ($value) {
    [$u, $l, $old] = tenantFixture();
    $settings = migrationBatch($u, $l)->settings;
    $source = migrationSource();
    $source['currency'] = $l->fresh()->currency;
    unset($source['restaurant_id']);
    if ($value !== 'missing') {
        $source['restaurant_id'] = $value;
    }
    Http::fake(['pos.globalfoodsoft.com/*' => Http::response($source)]);
    $batch = ImportBatch::create(['organization_id' => $l->organization_id, 'location_id' => $l->id, 'user_id' => $u->id, 'settings' => $settings, 'credential' => 'private-fetch-key']);
    (new FetchGloriaFoodMenu($batch->id))->handle();
    expect($batch->fresh()->status)->toBe('ready')->and($batch->fresh()->credential)->toBeNull();
    $canonical = app(ProcessImport::class)->canonical($batch);
    expect($canonical['source_restaurant'])->toBe('menu:'.$source['id'])->and($canonical['reported_restaurant_id'])->toBeNull();
    $raw = json_decode(Crypt::decryptString(DB::table('import_snapshots')->where('batch_id', $batch->id)->value('source')), true);
    expect($raw)->toBe($source)->and($l->menus()->first()->categories->first()->items->first()->id)->toBe($old->id);
})->with(['missing', null, '']);

it('preserves source lineage, local edit decisions and nutrition mapping when the provider adds or removes its restaurant ID', function () {
    [$u, $l] = tenantFixture();
    $source = migrationSource();
    unset($source['restaurant_id']);
    $first = migrationBatch($u, $l, $source);
    $firstMenu = publishBatch($u, $first);
    $firstMenu->categories->first()->items->first()->update(['description' => 'Local reviewed description']);
    $knownSource = migrationSource();
    $knownSource['currency'] = $l->fresh()->currency;
    $settings = $first->settings;
    unset($settings['nutrition_map']);
    $second = ImportBatch::create(['organization_id' => $l->organization_id, 'location_id' => $l->id, 'user_id' => $u->id, 'settings' => $settings]);
    app(ProcessImport::class)->execute($second, $knownSource);
    $second->refresh();
    expect($second->source_restaurant)->toBe($first->source_restaurant)->and($second->settings['nutrition_map'])->toBe($first->settings['nutrition_map']);
    expect(app(PublishImport::class)->conflicts($second))->toContain('58424');
    $secondMenu = publishBatch($u, $second, ['58424' => 'keep_local']);
    expect(MenuItem::whereHas('category', fn ($q) => $q->where('menu_id', $secondMenu->id))->where('source_key', '58424')->first()->description)->toBe('Local reviewed description');
    expect($l->menus()->count())->toBe(1)->and(MenuItem::whereHas('category', fn ($q) => $q->where('menu_id', $secondMenu->id))->count())->toBe(4);
    $third = migrationBatch($u, $l, $source);
    $canonical = app(ProcessImport::class)->canonical($third);
    expect($canonical['source_restaurant'])->toBe($first->source_restaurant)->and($canonical['reported_restaurant_id'])->toBeNull()->and($canonical['resolved_restaurant_id'])->toBe((string) $knownSource['restaurant_id']);
});

it('reuses legacy numeric source identity only within the same organization and location', function () {
    [$u, $l] = tenantFixture();
    $first = migrationBatch($u, $l);
    publishBatch($u, $first);
    $report = json_decode(DB::table('import_reports')->where('batch_id', $first->id)->value('report'), true);
    unset($report['reported_restaurant_id'], $report['resolved_restaurant_id']);
    DB::table('import_reports')->where('batch_id', $first->id)->update(['report' => json_encode($report)]);
    $source = migrationSource();
    unset($source['restaurant_id']);
    $same = migrationBatch($u, $l, $source);
    expect($same->source_restaurant)->toBe($first->source_restaurant);
    $secondLocation = $l->replicate(['public_id', 'slug']);
    $secondLocation->name = 'Second source location';
    $secondLocation->save();
    $separate = migrationBatch($u, $secondLocation, $source);
    expect($separate->source_restaurant)->toBe('menu:'.$source['id']);
    [$other, $otherLocation] = tenantFixture('other-source');
    expect(migrationBatch($other, $otherLocation, $source)->source_restaurant)->toBe('menu:'.$source['id']);
});

it('blocks ambiguous missing identities and refuses stale source aliases at publication', function () {
    [$u, $l] = tenantFixture();
    $missingSource = migrationSource();
    unset($missingSource['restaurant_id']);
    $staged = migrationBatch($u, $l, $missingSource);
    $known = migrationBatch($u, $l);
    $knownMenu = publishBatch($u, $known);
    try {
        publishBatch($u, $staged);
        $this->fail('Expected identity conflict');
    } catch (HttpException $e) {
        expect($e->getStatusCode())->toBe(409);
    }
    expect($l->menus()->first()->id)->toBe($knownMenu->id);
    $otherSource = migrationSource();
    $otherSource['restaurant_id'] = 999999;
    $other = migrationBatch($u, $l, $otherSource);
    publishBatch($u, $other);
    $ambiguous = migrationBatch($u, $l, $missingSource);
    expect(DB::table('import_issues')->where('batch_id', $ambiguous->id)->where('code', 'source_identity_conflict')->where('severity', 'blocker')->exists())->toBeTrue();
    expect(fn () => publishBatch($u, $ambiguous))->toThrow(ValidationException::class);
});
it('normalizes official category and size rules without mutating the active menu', function () {
    [$u,$l,$old] = tenantFixture();
    $b = migrationBatch($u, $l);
    $data = app(ProcessImport::class)->canonical($b);
    expect($l->menus()->first()->categories->first()->items->first()->id)->toBe($old->id);
    expect($data['categories'])->toHaveCount(2);
    expect(array_sum(array_map(fn ($c) => count($c['items']), $data['categories'])))->toBe(4);
    expect($data['categories'][0]['items'][0]['variants'][1]['price'])->toBe(900);
    expect(DB::table('import_issues')->where('batch_id', $b->id)->where('severity', 'blocker')->count())->toBe(0);
    expect(DB::table('import_snapshots')->value('source'))->not->toContain('Pizza Margherita');
});
it('publishes a separate location version and prices inherited size and quantity modifiers authoritatively', function () {
    [$u,$l,$old] = tenantFixture();
    $s = migrationSource();
    $s['categories'][0]['items'][0]['sizes'][0]['groups'][0]['allow_quantity'] = true;
    $b = migrationBatch($u, $l, $s);
    $menu = publishBatch($u, $b);
    $item = $menu->categories->first()->items->first();
    $v = $item->variants->first();
    $categoryOption = $item->category->modifierGroups->first()->options->first();
    $sizeOption = $v->modifierGroups->first()->options->first();
    $data = checkoutData($item);
    $data['items'][0] = ['product_id' => $item->public_id, 'variant_id' => $v->id, 'quantity' => 2, 'modifiers' => [['id' => $categoryOption->id, 'quantity' => 1], ['id' => $sizeOption->id, 'quantity' => 3]], 'price' => 1];
    $r = app(PlaceOrderAction::class)->execute($l, $data, (string) Str::uuid());
    expect($r['order']->subtotal)->toBe(1820);
    expect($r['order']->items->first()->modifiers->firstWhere('name', 'Corn')->quantity)->toBe(3);
    expect(MenuItem::find($old->id))->not->toBeNull();
    expect(DB::table('import_entity_mappings')->where('batch_id', $b->id)->where('entity_type', 'variant')->count())->toBe(4);
    $public = app(PublicMenuTree::class)->build($l);
    expect($public[0]['categories'][0]['items'][0]['variants'][0]['is_default'])->toBeTrue();
});
it('rejects modifiers from another size and quantities on a single-choice option', function () {
    [$u,$l] = tenantFixture();
    $b = migrationBatch($u, $l);
    $menu = publishBatch($u, $b);
    $item = $menu->categories->first()->items->first();
    $v = $item->variants->first();
    $foreign = $item->variants->last()->modifierGroups->first()->options->first();
    $crust = $item->category->modifierGroups->first()->options->first();
    $data = checkoutData($item);
    $data['items'][0]['variant_id'] = $v->id;
    $data['items'][0]['modifier_ids'] = [$crust->id, $foreign->id];
    expect(fn () => app(PlaceOrderAction::class)->execute($l, $data, (string) Str::uuid()))->toThrow(ValidationException::class);
    $data['items'][0]['modifier_ids'] = [];
    $data['items'][0]['modifiers'] = [['id' => $crust->id, 'quantity' => 2]];
    expect(fn () => app(PlaceOrderAction::class)->execute($l, $data, (string) Str::uuid()))->toThrow(ValidationException::class);
});
it('preserves legacy optional groups and enforces product fulfillment restrictions', function () {
    [$u,$l,$item] = tenantFixture();
    $g = ModifierGroup::create(['organization_id' => $l->organization_id, 'name' => 'Extras', 'min_choices' => 0, 'max_choices' => 1]);
    $g->options()->create(['name' => 'Cheese', 'price' => 100]);
    $item->modifierGroups()->attach($g->id, ['organization_id' => $l->organization_id]);
    expect(placeFixture($l, $item)['order']->subtotal)->toBe(7000);
    $item->update(['order_types' => ['delivery']]);
    expect(fn () => placeFixture($l, $item))->toThrow(ValidationException::class);
});
it('blocks currency and unknown food metadata instead of silently losing it', function () {
    [$u,$l] = tenantFixture();
    $s = migrationSource();
    $s['categories'][0]['items'][0]['tags'] = ['MAGIC'];
    $s['categories'][0]['items'][0]['extras']['menu_item_allergens_values'] = [['name' => 'Unknown allergen']];
    $b = migrationBatch($u, $l, $s);
    expect(DB::table('import_issues')->where('batch_id', $b->id)->where('severity', 'blocker')->count())->toBe(2);
    expect(fn () => publishBatch($u, $b))->toThrow(ValidationException::class);
    $foreign = migrationSource();
    $foreign['currency'] = 'USD';
    $b2 = ImportBatch::create(['organization_id' => $l->organization_id, 'location_id' => $l->id, 'user_id' => $u->id, 'settings' => $b->settings]);
    app(ProcessImport::class)->execute($b2, $foreign);
    expect(DB::table('import_issues')->where('batch_id', $b2->id)->where('code', 'currency')->exists())->toBeTrue();
});
it('maps nutrition and dietary data on products and sizes with explicit basis', function () {
    [$u,$l] = tenantFixture();
    $s = migrationSource();
    $s['categories'][0]['items'][0]['tags'] = ['VEGETARIAN'];
    $s['categories'][0]['items'][0]['extras']['menu_item_nutritional_values'] = [['name' => 'Protein', 'value' => 8.4, 'unit' => 'g']];
    $s['categories'][0]['items'][0]['sizes'][0]['extras']['menu_item_nutritional_values'] = [['name' => 'Calories', 'value' => 250, 'unit' => 'kcal']];
    $b = migrationBatch($u, $l, $s);
    $m = publishBatch($u, $b);
    $i = $m->categories->first()->items->first();
    expect($i->content['nutrition']['values']['protein_g'])->toBe(8.4);
    expect($i->variants->first()->nutrition['values']['energy_kcal'])->toBe(250);
    expect($i->content['labels'])->toBe(['vegetarian']);
});
it('detects local edits on reimport and retains chosen edits without duplicating live products', function () {
    [$u,$l] = tenantFixture();
    $b = migrationBatch($u, $l);
    $first = publishBatch($u, $b);
    $item = $first->categories->first()->items->first();
    $item->update(['name' => 'Local pizza', 'price' => 1234]);
    $b2 = migrationBatch($u, $l);
    expect(app(PublishImport::class)->conflicts($b2))->toContain('58424');
    expect(fn () => publishBatch($u, $b2))->toThrow(ValidationException::class);
    $second = publishBatch($u, $b2, ['58424' => 'keep_local']);
    expect(MenuItem::whereHas('category', fn ($q) => $q->where('menu_id', $second->id))->count())->toBe(4);
    $copy = MenuItem::where('source_key', '58424')->whereHas('category', fn ($q) => $q->where('menu_id', $second->id))->first();
    expect($copy->name)->toBe('Local pizza');
    expect($copy->price)->toBe(1234);
    expect($l->menus()->count())->toBe(1);
    expect(ModifierGroup::where('organization_id', $l->organization_id)->forMenus([$second->id])->count())->toBe(6);
});
it('rolls back only unchanged publication and retains immutable order history', function () {
    [$u,$l,$old] = tenantFixture();
    $original = $l->menus()->first()->id;
    $b = migrationBatch($u, $l);
    $m = publishBatch($u, $b);
    app(PublishImport::class)->revert($u, $b);
    expect($l->menus()->first()->id)->toBe($original);
    expect(Menu::find($m->id))->not->toBeNull();
    $b2 = migrationBatch($u, $l);
    $m2 = publishBatch($u, $b2);
    $m2->categories->first()->items->first()->update(['price' => 1111]);
    try {
        app(PublishImport::class)->revert($u, $b2);
        $this->fail('Expected conflict');
    } catch (HttpException $e) {
        expect($e->getStatusCode())->toBe(409);
    }
});
it('guards source publication against stale previews and tenant access', function () {
    [$u,$l] = tenantFixture();
    [$other,$foreign] = tenantFixture('other');
    $b = migrationBatch($u, $l);
    $url = '/app/locations/'.$l->public_id.'/migration-center';
    $this->actingAs($other)->getJson($url.'/'.$b->public_id)->assertForbidden();
    $this->actingAs($u)->getJson('/app/locations/'.$foreign->public_id.'/migration-center/'.$b->public_id)->assertForbidden();
    $hash = app(PublishImport::class)->reviewHash($b);
    $l->update(['currency' => 'EUR']);
    try {
        app(PublishImport::class)->execute($u, $b, [], true, $hash);
        $this->fail('Expected conflict');
    } catch (HttpException $e) {
        expect($e->getStatusCode())->toBe(409);
    }
});
it('fetches via fixed endpoint and erases encrypted credentials on success and failure', function () {
    [$u,$l] = tenantFixture();
    Queue::fake();
    $s = migrationSource();
    $s['currency'] = 'RON';
    Http::fake(['pos.globalfoodsoft.com/*' => Http::sequence()->push($s)->push('invalid', 503)]);
    $url = '/app/locations/'.$l->public_id.'/migration-center';
    $r = $this->actingAs($u)->postJson($url, ['source' => 'api', 'key' => 'private-example-key', 'authorized' => true, 'size_prices' => 'base_plus_size', 'nutrition_basis' => 'per_100g', 'tax_basis_points' => 1100])->assertCreated();
    $b = ImportBatch::where('public_id', $r->json('batch_id'))->first();
    expect(DB::table('import_batches')->where('id', $b->id)->value('credential'))->not->toContain('private-example-key');
    (new FetchGloriaFoodMenu($b->id))->handle();
    expect($b->fresh()->credential)->toBeNull();
    expect($b->fresh()->status)->toBe('ready');
    Http::assertSent(fn ($r) => $r->url() === 'https://pos.globalfoodsoft.com/pos/menu' && $r->hasHeader('Glf-Api-Version', '2') && $r->hasHeader('Authorization', 'private-example-key'));
    $detail = $this->getJson($url.'/'.$b->public_id)->assertOk();
    expect($detail->getContent())->not->toContain('private-example-key');
    $failed = ImportBatch::create(['organization_id' => $l->organization_id, 'location_id' => $l->id, 'user_id' => $u->id, 'settings' => $b->settings, 'credential' => 'private-failure-key']);
    (new FetchGloriaFoodMenu($failed->id))->handle();
    expect($failed->fresh()->credential)->toBeNull();
    expect($failed->fresh()->failure_code)->toBe('fetch_or_validation_failed');
});
it('reports bounded validation fields and rules without exposing source values or API keys', function () {
    [$u, $l] = tenantFixture();
    $source = migrationSource();
    $source['categories'] = ['private-source-value' => [...$source['categories'][0], 'name' => str_repeat('private-source-value', 20)]];
    Http::fake(['pos.globalfoodsoft.com/*' => Http::response($source)]);
    Log::spy();
    $batch = ImportBatch::create(['organization_id' => $l->organization_id, 'location_id' => $l->id, 'user_id' => $u->id, 'settings' => ['size_prices' => 'base_plus_size', 'nutrition_basis' => 'per_100g', 'tax_basis_points' => 0], 'credential' => 'private-fetch-key']);
    $menus = Menu::count();
    (new FetchGloriaFoodMenu($batch->id))->handle();
    expect($batch->fresh()->failure_code)->toBe('source_validation_failed')->and($batch->fresh()->credential)->toBeNull();
    $response = $this->actingAs($u)->getJson('/app/locations/'.$l->public_id.'/migration-center/'.$batch->public_id)->assertOk();
    $response->assertJsonPath('issues.0.path', 'categories.*.name')->assertJsonPath('issues.0.message', 'max:100');
    expect($response->getContent())->not->toContain('private-source-value')->not->toContain('private-fetch-key');
    expect(Menu::count())->toBe($menus);
    Log::shouldHaveReceived('warning')->once()->with('GloriaFood import validation failed', Mockery::on(fn ($context) => $context['stage'] === 'validation' && ! str_contains(json_encode($context), 'private-source-value') && ! str_contains(json_encode($context), 'private-fetch-key')));
});

it('accepts an exported file and rejects malformed data without live changes', function () {
    [$u,$l,$old] = tenantFixture();
    $url = '/app/locations/'.$l->public_id.'/migration-center';
    $s = migrationSource();
    $s['currency'] = 'RON';
    $r = $this->actingAs($u)->post($url, ['source' => 'file', 'file' => UploadedFile::fake()->createWithContent('menu.json', json_encode($s)), 'authorized' => true, 'size_prices' => 'base_plus_size', 'nutrition_basis' => 'per_100g', 'tax_basis_points' => 1100])->assertCreated();
    $this->getJson($url.'/'.$r->json('batch_id'))->assertJsonPath('status', 'ready');
    expect($l->menus()->first()->categories->first()->items->first()->id)->toBe($old->id);
});

it('requires mappings for unnamed official nutrient IDs and revalidates without another fetch', function () {
    [$u,$l] = tenantFixture();
    $b = ImportBatch::create(['organization_id' => $l->organization_id, 'location_id' => $l->id, 'user_id' => $u->id, 'settings' => ['size_prices' => 'base_plus_size', 'nutrition_basis' => 'per_serving', 'tax_basis_points' => 0]]);
    $s = migrationSource();
    $s['currency'] = 'RON';
    app(ProcessImport::class)->execute($b, $s);
    expect(DB::table('import_issues')->where('batch_id', $b->id)->where('code', 'nutrient_id')->count())->toBe(12);
    $url = '/app/locations/'.$l->public_id.'/migration-center/'.$b->public_id;
    $this->actingAs($u)->postJson($url.'/resolve', ['nutrition_map' => ['9' => 'energy_kcal', '10' => 'fat_g', '11' => 'carbohydrate_g', '12' => 'protein_g', '13' => 'sugars_g', '14' => 'salt_g']])->assertNoContent();
    expect(DB::table('import_issues')->where('batch_id', $b->id)->where('severity', 'blocker')->count())->toBe(0);
    $m = publishBatch($u, $b->fresh());
    $item = $m->categories->first()->items->last();
    expect($item->variants->first()->nutrition['basis'])->toBe('per_100g');
    $this->getJson($url.'/report')->assertOk()->assertHeader('Content-Disposition', 'attachment; filename="migration-'.$b->public_id.'.json"');
    $again = ImportBatch::create(['organization_id' => $l->organization_id, 'location_id' => $l->id, 'user_id' => $u->id, 'settings' => ['size_prices' => 'base_plus_size', 'nutrition_basis' => 'per_100g', 'tax_basis_points' => 0]]);
    app(ProcessImport::class)->execute($again, $s);
    expect(DB::table('import_issues')->where('batch_id', $again->id)->where('code', 'nutrient_id')->count())->toBe(0);
});
it('imports hundreds of products without changing a second location sharing the original menu', function () {
    [$u,$l] = tenantFixture();
    $second = $l->replicate(['public_id', 'slug']);
    $second->name = 'Second location';
    $second->save();
    $original = $l->menus()->first();
    $second->menus()->attach($original->id, ['organization_id' => $l->organization_id]);
    $s = ['id' => 99, 'restaurant_id' => 101, 'currency' => 'RON', 'categories' => [['id' => 1, 'name' => 'Large menu', 'items' => array_map(fn ($n) => ['id' => $n, 'name' => 'Product '.$n, 'price' => 10, 'sizes' => [], 'groups' => []], range(1, 300))]]];
    $b = migrationBatch($u, $l, $s);
    $m = publishBatch($u, $b);
    expect(MenuItem::whereHas('category', fn ($q) => $q->where('menu_id', $m->id))->count())->toBe(300);
    expect($second->menus()->first()->id)->toBe($original->id);
    expect($original->fresh()->is_active)->toBeTrue();
    expect(DB::table('outbox_messages')->where('type', 'menu.updated')->where('location_id', $l->id)->count())->toBe(1);
    expect(DB::table('outbox_messages')->where('type', 'menu.updated')->where('location_id', $second->id)->count())->toBe(0);
});
it('removes stale queue secrets and expires only unpublished snapshots', function () {
    [$u,$l] = tenantFixture();
    $b = migrationBatch($u, $l);
    $queued = ImportBatch::create(['organization_id' => $l->organization_id, 'location_id' => $l->id, 'user_id' => $u->id, 'settings' => $b->settings, 'credential' => 'stale-private-key']);
    $queued->update(['created_at' => now()->subHours(2)]);
    $b->update(['created_at' => now()->subDays(40)]);
    $this->artisan('zionfood:imports-prune')->assertSuccessful();
    expect($queued->fresh()->credential)->toBeNull();
    expect($b->fresh()->status)->toBe('expired');
    expect(DB::table('import_snapshots')->where('batch_id', $b->id)->exists())->toBeFalse();
});
it('preserves variant IDs rules and nutrition when a restaurant edits the product', function () {
    [$u,$l] = tenantFixture();
    $b = migrationBatch($u, $l);
    $m = publishBatch($u, $b);
    $item = $m->categories->first()->items->last();
    $variant = $item->variants->first();
    $before = $variant->nutrition;
    $saved = app(UpdateMenuItem::class)->execute($u, $l->organization_id, ['category_id' => $item->category_id, 'name' => 'Edited dish', 'description' => 'Description', 'price' => $item->price, 'is_available' => true, 'variants' => $item->variants->map(fn ($v) => ['id' => $v->id, 'name' => $v->name, 'price' => $v->price, 'is_default' => $v->is_default, 'modifier_group_ids' => $v->modifierGroups->pluck('id')->all()])->all()], $item);
    expect($saved->variants->first()->id)->toBe($variant->id);
    expect($saved->variants->first()->nutrition)->toBe($before);
    expect($saved->variants->first()->modifierGroups)->toHaveCount(1);
});

it('matches source-derived reference baskets through the real checkout action', function () {
    [$u,$l] = tenantFixture();
    $b = migrationBatch($u, $l);
    $menu = publishBatch($u, $b);
    $canonical = app(ProcessImport::class)->canonical($b);
    $baskets = app(ImportVerification::class)->evidence($canonical);
    foreach ($baskets as $basket) {
        $item = MenuItem::where('source_key', $basket['product_key'])->whereHas('category', fn ($q) => $q->where('menu_id', $menu->id))->firstOrFail();
        $variant = $basket['variant'] ? $item->variants->firstWhere('name', $basket['variant']) : null;
        $groups = app(EffectiveModifiers::class)->groups($item, $variant);
        $ids = [];
        foreach ($basket['choices'] as $choice) {
            $ids[] = $groups->firstWhere('name', $choice['group'])->options->firstWhere('name', $choice['option'])->id;
        }$data = checkoutData($item);
        $data['items'] = [['product_id' => $item->public_id, 'variant_id' => $variant?->id, 'quantity' => 1, 'modifier_ids' => $ids]];
        $key = (string) Str::uuid();
        $result = app(PlaceOrderAction::class)->execute($l, $data, $key);
        expect($result['order']->subtotal)->toBe($basket['total']);
        expect(app(PlaceOrderAction::class)->execute($l, $data, $key)['replayed'])->toBeTrue();
    }
});

it('clears the key immediately when job dispatch fails and never flashes secret input', function () {
    [$u,$l] = tenantFixture();
    Bus::shouldReceive('dispatch')->once()->andThrow(new RuntimeException('Queue unavailable'));
    $url = '/app/locations/'.$l->public_id.'/migration-center';
    $response = $this->actingAs($u)->postJson($url, ['source' => 'api', 'key' => 'test-secret-not-logged', 'authorized' => true, 'size_prices' => 'base_plus_size', 'nutrition_basis' => 'per_100g', 'tax_basis_points' => 0])->assertCreated();
    $b = ImportBatch::where('public_id', $response->json('batch_id'))->first();
    expect($b->credential)->toBeNull();
    expect($b->failure_code)->toBe('queue_unavailable');
    $this->post($url, ['source' => 'api', 'key' => 'do-not-flash-this-secret', 'authorized' => false])->assertSessionMissing('_old_input.key');
});
