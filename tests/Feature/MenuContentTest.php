<?php

use App\Domain\Menus\Actions\UpdateMenuItem;
use App\Domain\Menus\CalculateNutrition;
use App\Domain\Menus\GloriaFoodContent;
use App\Domain\Menus\ManageMenuContent;
use App\Domain\Menus\MenuAi;
use App\Domain\Menus\MenuContent;
use App\Jobs\GenerateMenuContent;
use App\Models\LocationStaff;
use App\Models\MenuAiSetting;
use App\Models\MenuContentDraft;
use App\Models\MenuItem;
use App\Models\ModifierGroup;
use App\Models\OrganizationMembership;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Validation\ValidationException;

function menuTranslation(array $extra = []): array
{
    return ['name' => 'Translated dish', 'description' => 'Translated description', 'ingredients_text' => 'milk *', 'portion_size' => '200 g', 'category_name' => 'Category', 'menu_name' => 'Menu', 'variants' => [], 'groups' => [], ...$extra];
}

it('sends populated strict schema object maps for every provider operation and sparse product shape', function (string $provider, string $kind, string $shape) {
    Http::preventStrayRequests();
    MenuAiSetting::create(['id' => 1, 'enabled' => true, 'provider' => $provider, 'model' => $provider === 'groq' ? 'openai/gpt-oss-20b' : 'configured-model', 'api_key' => 'fake_schema_key', 'daily_limit' => 5]);
    $source = ['name' => 'Preparat de test', 'description' => 'Descriere', 'variants' => [], 'groups' => []];
    if ($shape === 'variants') {
        $source['variants'] = [['id' => 15, 'name' => 'Mare']];
        $source['groups'] = [['id' => 26, 'name' => 'Sosuri', 'options' => [['id' => 37, 'name' => 'Roșii']]]];
    } elseif ($shape === 'empty_options') {
        $source['groups'] = [['id' => 26, 'name' => 'Sosuri', 'options' => []]];
    }
    $languages = $kind === 'translate' ? ['en', 'de'] : [];
    Http::fake(function ($request) use ($provider, $kind, $languages) {
        // Inspect actual wire JSON as objects: associative decoding hides {} versus [].
        $wire = json_decode($request->body(), flags: JSON_THROW_ON_ERROR);
        $schema = $provider === 'groq' ? $wire->response_format->json_schema->schema : $wire->text->format->schema;
        $check = function ($node) use (&$check) {
            if (is_object($node)) {
                if (($node->type ?? null) === 'object') {
                    expect($node->properties)->toBeInstanceOf(stdClass::class);
                    expect(get_object_vars($node->properties))->not->toBeEmpty();
                    expect($node->additionalProperties)->toBeFalse();
                    expect($node->required)->toBe(array_map('strval', array_keys(get_object_vars($node->properties))));
                }
                foreach (get_object_vars($node) as $child) {
                    $check($child);
                }
            } elseif (is_array($node)) {
                foreach ($node as $child) {
                    $check($child);
                }
            }
        };
        $check($schema);
        if ($kind !== 'translate') {
            expect(property_exists($schema->properties, 'translations'))->toBeFalse();
        }
        $result = match ($kind) {
            'translate' => ['translations' => array_fill_keys($languages, menuTranslation()), 'notes' => ''],
            'rewrite' => ['description' => 'Descriere rescrisă.', 'notes' => ''],
            default => ['allergens' => ['milk'], 'may_contain' => [], 'labels' => ['vegetarian'], 'notes' => ''],
        };

        return Http::response($provider === 'groq' ? ['choices' => [['finish_reason' => 'stop', 'message' => ['content' => json_encode($result)]]]] : ['status' => 'completed', 'output' => [['content' => [['type' => 'output_text', 'text' => json_encode($result)]]]]]);
    });
    expect(app(MenuAi::class)->generate($source, $languages, $kind))->toBeArray();
    Http::assertSentCount(1);
})->with(['groq', 'openai'])->with(['suggest', 'rewrite', 'translate'])->with(['empty', 'variants', 'empty_options']);

function menuRecipe(): array
{
    return [...app(MenuContent::class)->empty(), 'yield_grams' => 240, 'servings' => 2, 'recipe' => [
        ['name' => 'Ingredient A', 'grams' => 100, 'reference' => 'Supplier A per 100 g', 'values' => ['energy_kcal' => 100, 'protein_g' => 10, 'salt_g' => 1]],
        ['name' => 'Ingredient B', 'grams' => 200, 'reference' => 'Supplier B per 100 g', 'values' => ['energy_kcal' => 200, 'protein_g' => 20]],
    ]];
}

it('calculates from recipe references and cooked yield without inventing missing nutrients', function () {
    $result = app(CalculateNutrition::class)->calculate(menuRecipe());
    expect($result['values']['energy_kcal'])->toBe(208.333)->and($result['per_serving']['energy_kcal'])->toBe(250.0)->and($result['values']['protein_g'])->toBe(20.833)->and($result['values']['salt_g'])->toBeNull()->and($result['estimated'])->toBeFalse();
    expect(fn () => app(CalculateNutrition::class)->calculate(app(MenuContent::class)->empty()))->toThrow(ValidationException::class);
    $content = menuRecipe();
    $content['recipe'][0]['reference'] = '';
    expect(fn () => app(CalculateNutrition::class)->calculate($content))->toThrow(ValidationException::class);
});

it('saves food information and manual translations with stale write protection and public field filtering', function () {
    [$owner, $location, $item] = tenantFixture('content-save');
    $schema = app(MenuContent::class);
    $base = '/app/locations/'.$location->public_id.'/menu-content';
    $content = [...menuRecipe(), 'allergens' => ['milk'], 'may_contain' => ['nuts'], 'labels' => ['vegetarian'], 'translations' => ['de' => menuTranslation()]];
    $hash = $schema->hash($item);
    $this->actingAs($owner)->putJson($base.'/items/'.$item->public_id, ['content' => $content, 'hash' => $hash])->assertOk();
    $this->putJson($base.'/items/'.$item->public_id, ['content' => $content, 'hash' => $hash])->assertConflict();
    $public = $schema->publicData($item->fresh());
    expect($public['translations']['de']['name'])->toBe('Translated dish')->and($public)->not->toHaveKey('recipe')->and($public)->not->toHaveKey('yield_grams');
    $item->update(['description' => 'A changed source']);
    expect($schema->publicData($item->fresh())['translations'])->toBeEmpty();
    $this->get('/restaurant/'.$location->restaurant->slug)->assertOk();
});

it('rejects unknown allergen codes contradictory labels and AI nutritional estimates', function () {
    $schema = app(MenuContent::class);
    expect(fn () => $schema->validate([...$schema->empty(), 'allergens' => ['999']]))->toThrow(ValidationException::class);
    expect(fn () => $schema->validate([...$schema->empty(), 'allergens' => ['milk'], 'labels' => ['vegan']]))->toThrow(ValidationException::class);
    expect(fn () => $schema->validate([...$schema->empty(), 'nutrition' => ['basis' => 'per_100g', 'values' => ['protein_g' => 5], 'estimated' => true, 'reference' => 'An unsupported AI estimate']]))->toThrow(ValidationException::class);
});

it('stages GloriaFood food data without guessing internal allergen or nutrient identifiers', function () {
    $source = ['id' => 58426, 'name' => 'Source dish', 'tags' => ['VEGETARIAN', 'UNKNOWN'], 'extras' => ['menu_item_allergens_values' => [['id' => 1, 'name' => 'Milk'], ['id' => 7, 'name' => 'Shellfish']], 'menu_item_nutritional_values' => [['id' => 9, 'value' => '123']], 'menu_item_nutritional_values_size' => 'per_100g']];
    $import = app(GloriaFoodContent::class)->read($source);
    expect($import['proposal']['allergens'])->toBe(['milk'])->and($import['proposal']['labels'])->toBe(['vegetarian'])->and($import['proposal'])->not->toHaveKey('nutrition')->and($import['report']['unmapped_allergens'][0]['name'])->toBe('Shellfish')->and($import['report']['unmapped_nutrition'][0]['id'])->toBe(9);
    [$owner, $location, $item] = tenantFixture('content-gloria');
    $base = '/app/locations/'.$location->public_id.'/menu-content';
    $this->actingAs($owner)->postJson($base.'/items/'.$item->public_id.'/gloriafood', ['source_item' => $source])->assertCreated();
    $draft = MenuContentDraft::firstOrFail();
    expect($item->fresh()->content)->toBeNull()->and($draft->getRawOriginal('input'))->not->toContain('Source dish')->and($draft->getRawOriginal('proposal'))->not->toContain('milk');
    $this->postJson($base.'/drafts/'.$draft->public_id, ['action' => 'approve'])->assertUnprocessable();
    $this->postJson($base.'/drafts/'.$draft->public_id, ['action' => 'reject'])->assertOk();
    expect($item->fresh()->content)->toBeNull();
});

it('publishes a reviewed recipe calculation and invalidates it when its recipe changes', function () {
    [$owner, $location, $item] = tenantFixture('content-calculation');
    $schema = app(MenuContent::class);
    $manager = app(ManageMenuContent::class);
    $item = $manager->save($owner, $location, $item, menuRecipe(), $schema->hash($item));
    $draft = $manager->draft($owner, $location, $item, 'calculation');
    expect($item->fresh()->content['nutrition'])->toBeNull()->and($draft->proposal['nutrition']['values']['energy_kcal'])->toBe(208.333);
    $manager->approve($owner, $location, $draft);
    expect($item->fresh()->content['nutrition']['values']['energy_kcal'])->toBe(208.333)->and($draft->fresh()->reviewed_by)->toBe($owner->id);
    $item = $item->fresh();
    $content = $item->content;
    $content['yield_grams'] = 400;
    $manager->save($owner, $location, $item, $content, $schema->hash($item));
    expect($item->fresh()->content['nutrition'])->toBeNull();
    $item = $item->fresh();
    $calculation = $manager->draft($owner, $location, $item, 'calculation');
    $manager->approve($owner, $location, $calculation);
    $item = $item->fresh();
    app(UpdateMenuItem::class)->execute($owner, $location->organization_id, ['category_id' => $item->category_id, 'name' => $item->name, 'price' => $item->price, 'portion_size' => 'A changed serving', 'is_available' => true], $item);
    expect($item->fresh()->content['nutrition'])->toBeNull();
});

it('enforces location and feature access for content drafts reads saves and review', function () {
    [$owner, $location, $item] = tenantFixture('content-auth');
    [$foreign, $other] = tenantFixture('content-auth-foreign');
    $base = '/app/locations/'.$location->public_id.'/menu-content';
    $this->actingAs($foreign)->get($base)->assertForbidden();
    $this->putJson($base.'/items/'.$item->public_id, ['content' => app(MenuContent::class)->empty(), 'hash' => str_repeat('a', 64)])->assertForbidden();
    $staff = User::factory()->create();
    $membership = OrganizationMembership::create(['organization_id' => $location->organization_id, 'user_id' => $staff->id, 'role' => 'staff', 'permissions' => ['orders' => 'none', 'reservations' => 'none', 'tables' => 'none', 'kitchen' => 'none', 'menu' => 'view']]);
    LocationStaff::create(['organization_id' => $location->organization_id, 'location_id' => $location->id, 'user_id' => $staff->id]);
    $this->actingAs($staff)->get($base)->assertOk();
    $this->postJson($base.'/generate', ['kind' => 'calculation', 'item' => $item->public_id, 'languages' => []])->assertForbidden();
    $membership->update(['permissions' => ['orders' => 'none', 'reservations' => 'none', 'tables' => 'none', 'kitchen' => 'none', 'menu' => 'none']]);
    $this->get($base)->assertForbidden();
    $this->actingAs($owner)->get($base.'?item=foreign-id')->assertNotFound();
});

it('encrypts administrator keys and never returns them to tenant or platform pages', function () {
    $admin = User::factory()->create(['is_platform_admin' => true]);
    $settings = ['enabled' => true, 'provider' => 'groq', 'model' => 'openai/gpt-oss-20b', 'api_key' => 'gsk_test_secret', 'clear_key' => false, 'daily_limit' => 5];
    $this->actingAs($admin)->postJson('/admin/menu-ai', $settings)->assertOk();
    expect(MenuAiSetting::find(1)->getRawOriginal('api_key'))->not->toContain('gsk_test_secret');
    $this->get('/admin/menu-ai')->assertOk()->assertDontSee('gsk_test_secret');
    [$owner] = tenantFixture('content-settings');
    $this->actingAs($owner)->postJson('/admin/menu-ai', $settings)->assertForbidden();
    $this->actingAs($admin)->postJson('/admin/menu-ai', [...$settings, 'provider' => 'openai', 'model' => 'configured-model', 'api_key' => ''])->assertUnprocessable();
    expect(MenuAiSetting::find(1)->provider)->toBe('groq');
});

it('saves validated AI translations locally without publication then requires current-source approval', function () {
    Queue::fake();
    Http::preventStrayRequests();
    MenuAiSetting::create(['id' => 1, 'enabled' => true, 'provider' => 'groq', 'model' => 'openai/gpt-oss-20b', 'api_key' => 'gsk_fake', 'daily_limit' => 5]);
    [$owner, $location, $item] = tenantFixture('content-ai');
    Http::fake(['api.groq.com/*' => Http::response(['choices' => [['finish_reason' => 'stop', 'message' => ['content' => json_encode(['translations' => ['de' => array_diff_key(menuTranslation(), ['variants' => true, 'groups' => true])], 'notes' => 'Check terminology.'])]]]])]);
    $manager = app(ManageMenuContent::class);
    $draft = $manager->draft($owner, $location, $item, 'translate', ['de']);
    (new GenerateMenuContent($draft->id))->handle();
    expect($draft->fresh()->status)->toBe('ready')->and($item->fresh()->content)->toBeNull();
    expect($draft->fresh()->proposal['translations']['de']['variants'])->toBe([])->and($draft->fresh()->proposal['translations']['de']['groups'])->toBe([]);
    Http::assertSent(fn ($r) => $r->url() === 'https://api.groq.com/openai/v1/chat/completions' && $r->hasHeader('Authorization', 'Bearer gsk_fake') && $r['response_format']['json_schema']['strict'] === true && ! str_contains($r->body(), $owner->email));
    $manager->approve($owner, $location, $draft->fresh());
    expect(app(MenuContent::class)->publicData($item->fresh())['translations']['de']['name'])->toBe('Translated dish');
    $draft = $manager->draft($owner, $location, $item->fresh(), 'rewrite');
    $item->update(['name' => 'Changed source']);
    (new GenerateMenuContent($draft->id))->handle();
    expect($draft->fresh()->status)->toBe('stale');
});

it('keeps failed provider calls unpublished and rejects cross-language or nutritional output injection', function () {
    Queue::fake();
    Http::preventStrayRequests();
    MenuAiSetting::create(['id' => 1, 'enabled' => true, 'provider' => 'groq', 'model' => 'openai/gpt-oss-20b', 'api_key' => 'gsk_fake', 'daily_limit' => 5]);
    [$owner, $location, $item] = tenantFixture('content-ai-errors');
    Http::fake(['api.groq.com/*' => Http::response([], 429)]);
    $draft = app(ManageMenuContent::class)->draft($owner, $location, $item, 'translate', ['de']);
    (new GenerateMenuContent($draft->id))->handle();
    expect($draft->fresh()->status)->toBe('failed')->and($draft->fresh()->error_code)->toBe('ai_rate_limited')->and($item->fresh()->content)->toBeNull();
    Http::fake(['api.groq.com/*' => Http::response(['choices' => [['finish_reason' => 'stop', 'message' => ['content' => json_encode(['translations' => ['en' => menuTranslation()], 'nutrition' => ['values' => ['salt_g' => 0]], 'notes' => ''])]]]])]);
    $draft = app(ManageMenuContent::class)->draft($owner, $location, $item, 'translate', ['de']);
    (new GenerateMenuContent($draft->id))->handle();
    expect($draft->fresh()->status)->toBe('failed')->and($item->fresh()->content)->toBeNull();
});

it('applies a rolling global AI quota and atomic batch rollback across products', function () {
    Queue::fake();
    MenuAiSetting::create(['id' => 1, 'enabled' => true, 'provider' => 'groq', 'model' => 'openai/gpt-oss-20b', 'api_key' => 'gsk_fake', 'daily_limit' => 1]);
    [$owner, $location, $item] = tenantFixture('content-budget');
    app(ManageMenuContent::class)->draft($owner, $location, $item, 'translate', ['en']);
    [$otherOwner, $otherLocation, $otherItem] = tenantFixture('content-budget-other');
    expect(fn () => app(ManageMenuContent::class)->draft($otherOwner, $otherLocation, $otherItem, 'translate', ['en']))->toThrow(ValidationException::class);
    expect(MenuContentDraft::count())->toBe(1);
    MenuContentDraft::query()->delete();
    MenuItem::create(['organization_id' => $location->organization_id, 'category_id' => $item->category_id, 'name' => 'Second batch product', 'price' => 100]);
    $this->actingAs($owner)->postJson('/app/locations/'.$location->public_id.'/menu-content/generate', ['kind' => 'translate', 'item' => null, 'languages' => ['en']])->assertUnprocessable();
    expect(MenuContentDraft::count())->toBe(0);
});

it('uses the explicitly selected OpenAI endpoint with no provider fallback or nutrition authority', function () {
    Queue::fake();
    Http::preventStrayRequests();
    MenuAiSetting::create(['id' => 1, 'enabled' => true, 'provider' => 'openai', 'model' => 'configured-model', 'api_key' => 'sk_fake', 'daily_limit' => 5]);
    [$owner, $location, $item] = tenantFixture('content-openai');
    Http::fake(['api.openai.com/*' => Http::response(['status' => 'completed', 'output' => [['content' => [['type' => 'output_text', 'text' => json_encode(['allergens' => ['milk'], 'may_contain' => [], 'labels' => ['vegetarian'], 'nutrition' => ['values' => ['protein_g' => 999]], 'notes' => 'Review suggested milk.'])]]]]])]);
    $draft = app(ManageMenuContent::class)->draft($owner, $location, $item, 'suggest');
    (new GenerateMenuContent($draft->id))->handle();
    expect($draft->fresh()->status)->toBe('ready')->and($draft->fresh()->proposal)->not->toHaveKey('nutrition');
    Http::assertSent(fn ($r) => $r->url() === 'https://api.openai.com/v1/responses' && $r['store'] === false && $r['text']['format']['strict'] === true);
    Http::assertSentCount(1);
});

it('marks an interrupted provider attempt failed without making a duplicate request', function () {
    Queue::fake();
    Http::preventStrayRequests();
    MenuAiSetting::create(['id' => 1, 'enabled' => true, 'provider' => 'groq', 'model' => 'openai/gpt-oss-20b', 'api_key' => 'gsk_fake', 'daily_limit' => 5]);
    [$owner, $location, $item] = tenantFixture('content-interrupted');
    $draft = app(ManageMenuContent::class)->draft($owner, $location, $item, 'translate', ['en']);
    $draft->update(['status' => 'processing', 'updated_at' => now()->subMinutes(2)]);
    (new GenerateMenuContent($draft->id))->handle();
    expect($draft->fresh()->status)->toBe('failed');
    Http::assertNothingSent();
});

it('restores known empty option maps after a sparse translation response', function () {
    Queue::fake();
    Http::preventStrayRequests();
    MenuAiSetting::create(['id' => 1, 'enabled' => true, 'provider' => 'groq', 'model' => 'openai/gpt-oss-20b', 'api_key' => 'gsk_fake', 'daily_limit' => 5]);
    [$owner, $location, $item] = tenantFixture('content-empty-options');
    $group = ModifierGroup::create(['organization_id' => $location->organization_id, 'name' => 'Sosuri', 'min_choices' => 0, 'max_choices' => 1]);
    $item->modifierGroups()->attach($group->id, ['organization_id' => $location->organization_id]);
    $row = array_diff_key(menuTranslation(), ['variants' => true, 'groups' => true]);
    $row['groups'] = [(string) $group->id => ['name' => 'Sauces']];
    Http::fake(['api.groq.com/*' => Http::response(['choices' => [['finish_reason' => 'stop', 'message' => ['content' => json_encode(['translations' => ['en' => $row], 'notes' => ''])]]]])]);
    $manager = app(ManageMenuContent::class);
    $draft = $manager->draft($owner, $location, $item, 'translate', ['en']);
    (new GenerateMenuContent($draft->id))->handle();
    expect($draft->fresh()->status)->toBe('ready')->and($draft->fresh()->proposal['translations']['en']['groups'][$group->id]['options'])->toBe([]);
    $manager->approve($owner, $location, $draft->fresh());
    expect($item->fresh()->content['translations']['en']['groups'][$group->id]['name'])->toBe('Sauces');
});
