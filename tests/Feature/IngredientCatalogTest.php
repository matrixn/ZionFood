<?php

use App\Domain\Menus\Actions\UpdateMenuItem;
use App\Models\Ingredient;
use App\Models\OrganizationMembership;
use App\Models\Restaurant;
use App\Models\User;
use Database\Seeders\IngredientSeeder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;

function ingredientData(array $extra = []): array
{
    return ['name' => 'Ingredient unic', 'name_en' => '', 'contains_allergens' => true, 'from_frozen' => true, 'is_active' => true, 'needs_review' => false, ...$extra];
}

function recipeData($location, array $extra = []): array
{
    return ['category_id' => $location->menus()->first()->categories()->first()->id, 'name' => 'Feta Saganaki', 'price' => 5100, 'portion_size' => '350 g', 'is_available' => true, 'ingredient_ids' => [], ...$extra];
}

it('seeds unique source ingredients with majority flags positive ties and review evidence', function () {
    expect(Ingredient::count())->toBe(183)->and(Ingredient::pluck('normalized_name')->unique()->count())->toBe(183);
    expect(Ingredient::where('name', 'Bagheta')->first()->contains_allergens)->toBeTrue();
    $mozzarella = Ingredient::where('name', 'Mozzarella')->first();
    expect($mozzarella->contains_allergens)->toBeTrue()->and($mozzarella->needs_review)->toBeTrue();
    expect(Ingredient::where('name', 'Calamari')->first()->from_frozen)->toBeTrue()->and(Ingredient::where('name', 'Calamari')->first()->contains_allergens)->toBeTrue();
    expect(Ingredient::where('name', 'Mai')->first()->is_active)->toBeFalse();
    $audit = json_decode(file_get_contents(base_path('docs/INGREDIENT_IMPORT_AUDIT.json')), true, flags: JSON_THROW_ON_ERROR);
    foreach ($audit['ingredients'] as $row) {
        $count = array_sum($row['counts']);
        foreach (['contains_allergens' => ['allergen', 'both'], 'from_frozen' => ['frozen', 'both']] as $flag => $types) {
            $positive = array_sum(array_map(fn ($type) => $row['counts'][$type] ?? 0, $types));
            expect($row[$flag])->toBe($positive > 0 && $positive >= $count - $positive);
        }
    }
});

it('does not overwrite admin changes or resurrect deleted seed ingredients on deployment reseeding', function () {
    $row = Ingredient::where('source_key', 'global-bagheta')->first();
    $row->update(['source_key' => 'queen-bagheta', 'name' => 'Bagheta verificata', 'normalized_name' => 'bagheta verificata', 'contains_allergens' => false, 'is_active' => false]);
    $row->delete();
    $this->seed(IngredientSeeder::class);
    expect(Ingredient::withTrashed()->count())->toBe(183)->and($row->fresh()->trashed())->toBeTrue()->and($row->fresh()->contains_allergens)->toBeFalse()->and($row->fresh()->source_key)->toBe('global-bagheta');
});

it('restricts global edits and scopes restaurant ingredients and accent insensitive duplicates', function () {
    [$owner, $location] = tenantFixture('ingredient-owner');
    [$foreign, $other] = tenantFixture('ingredient-foreign');
    $global = Ingredient::where('name', 'Rosii cherry')->first();
    $base = '/app/locations/'.$location->public_id.'/ingredients';
    $this->actingAs($owner)->get('/admin/ingredients')->assertForbidden();
    $this->post('/admin/ingredients', ingredientData())->assertForbidden();
    $this->post($base.'/'.$global->id, ingredientData())->assertForbidden();
    $this->post($base, ingredientData(['name' => 'Roșii cherry']))->assertSessionHasErrors('name');
    $this->post($base, ingredientData())->assertSessionHasNoErrors();
    $custom = Ingredient::where('name', 'Ingredient unic')->firstOrFail();
    expect($custom->restaurant_id)->toBe($location->restaurant_id)->and($custom->organization_id)->toBe($location->organization_id);
    $this->post($base, ingredientData(['name' => '  INGREDIENT  UNIC ']))->assertSessionHasErrors('name');
    $this->actingAs($foreign)->get($base)->assertForbidden();
    $this->post('/app/locations/'.$other->public_id.'/ingredients/'.$custom->id, ingredientData())->assertForbidden();
    $this->get('/app/locations/'.$other->public_id.'/ingredients')->assertInertia(fn ($p) => $p->where('ingredients', fn ($rows) => ! collect($rows)->pluck('id')->contains($custom->id)));
});

it('allows platform catalogue management and denies ingredient administration to staff', function () {
    $admin = User::factory()->create(['is_platform_admin' => true]);
    $this->actingAs($admin)->post('/admin/ingredients', ingredientData(['name' => 'Ingredient comun nou']))->assertSessionHasNoErrors();
    expect(Ingredient::where('name', 'Ingredient comun nou')->first()->restaurant_id)->toBeNull();
    [$owner, $location] = tenantFixture('ingredient-staff');
    $staff = User::factory()->create();
    OrganizationMembership::create(['organization_id' => $location->organization_id, 'user_id' => $staff->id, 'role' => 'staff']);
    $this->actingAs($staff)->get('/app/locations/'.$location->public_id.'/ingredients')->assertForbidden();
});

it('saves ordered ingredients portion and minor unit price through json and image payloads', function () {
    [$owner, $location] = tenantFixture('recipe-api');
    $ids = Ingredient::whereIn('name', ['Branza feta', 'Sos rosii', 'Bagheta'])->pluck('id')->reverse()->values()->all();
    $url = '/api/v1/locations/'.$location->public_id.'/items';
    $response = $this->actingAs($owner)->postJson($url, recipeData($location, ['ingredient_ids' => $ids]))->assertCreated()->assertJsonPath('price', 5100)->assertJsonPath('portion_size', '350 g');
    expect(array_column($response->json('ingredients'), 'id'))->toBe($ids);
    $id = $response->json('public_id');
    $this->post('/api/v1/items/'.$id, ['_method' => 'PUT', 'payload' => json_encode(recipeData($location, ['ingredient_ids' => [$ids[0]], 'portion_size' => '200/250 g', 'price' => 5299]))])->assertOk()->assertJsonPath('price', 5299)->assertJsonPath('portion_size', '200/250 g')->assertJsonCount(1, 'ingredients');
    $this->postJson($url, recipeData($location, ['price' => 51.99]))->assertUnprocessable()->assertJsonValidationErrors('price');
    $this->postJson($url, recipeData($location, ['ingredient_ids' => [$ids[0], $ids[0]]]))->assertUnprocessable()->assertJsonValidationErrors('ingredient_ids.0');
});

it('rejects foreign restaurant hidden and deleted ingredients without creating partial products', function () {
    [$owner, $location] = tenantFixture('recipe-scope');
    $restaurant = Restaurant::factory()->create(['organization_id' => $location->organization_id]);
    $foreign = Ingredient::create([...ingredientData(), 'restaurant_id' => $restaurant->id, 'organization_id' => $location->organization_id, 'scope_key' => 'restaurant:'.$restaurant->id, 'normalized_name' => 'ingredient unic']);
    $hidden = Ingredient::first();
    $hidden->update(['is_active' => false]);
    $before = $location->menus()->first()->categories()->first()->items()->count();
    foreach ([$foreign->id, $hidden->id, 999999] as $id) {
        expect(fn () => app(UpdateMenuItem::class)->execute($owner, $location->organization_id, recipeData($location, ['ingredient_ids' => [$id]])))->toThrow(ValidationException::class);
    }
    expect($location->menus()->first()->categories()->first()->items()->count())->toBe($before);
});

it('retains hidden and removed recipe information publicly while blocking new selections', function () {
    [$owner, $location] = tenantFixture('recipe-retained');
    $ingredient = Ingredient::where('name', 'Bagheta')->first();
    $item = app(UpdateMenuItem::class)->execute($owner, $location->organization_id, recipeData($location, ['ingredient_ids' => [$ingredient->id]]));
    $admin = User::factory()->create(['is_platform_admin' => true]);
    $this->actingAs($admin)->delete('/admin/ingredients/'.$ingredient->id)->assertSessionHasNoErrors();
    Cache::flush();
    $public = $this->getJson('/api/v1/public/locations/'.$location->public_id.'/menu')->assertOk();
    $product = collect($public->json('menus.0.categories.0.items'))->firstWhere('public_id', $item->public_id);
    expect($product['contains_allergens'])->toBeTrue()->and($product['ingredients'][0]['contains_allergens'])->toBeTrue()->and($product['portion_size'])->toBe('350 g')->and($product['ingredients'][0])->not->toHaveKey('restaurant_id')->not->toHaveKey('normalized_name');
    app(UpdateMenuItem::class)->execute($owner, $location->organization_id, recipeData($location, ['ingredient_ids' => [$ingredient->id], 'price' => 5200]), $item);
    expect($item->fresh('ingredients')->ingredients)->toHaveCount(1);
    expect(fn () => app(UpdateMenuItem::class)->execute($owner, $location->organization_id, recipeData($location, ['ingredient_ids' => [$ingredient->id]])))->toThrow(ValidationException::class);
});
