<?php

namespace App\Http\Controllers;

use App\Domain\Menus\RecordMenuChange;
use App\Domain\Tenancy\TenantAccess;
use App\Models\Ingredient;
use App\Models\RestaurantLocation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class IngredientController extends Controller
{
    public function adminIndex(Request $request)
    {
        abort_unless($request->user()->is_platform_admin, 403);

        return Inertia::render('admin/Ingredients', ['ingredients' => Ingredient::whereNull('restaurant_id')->orderBy('name')->get()]);
    }

    public function adminSave(Request $request, ?string $ingredient = null)
    {
        abort_unless($request->user()->is_platform_admin, 403);

        return $this->persist($request, null, $ingredient);
    }

    public function adminDestroy(Request $request, string $ingredient)
    {
        abort_unless($request->user()->is_platform_admin, 403);

        return $this->remove(null, $ingredient);
    }

    public function index(Request $request, string $location)
    {
        $context = $this->context($request, $location);

        return Inertia::render('restaurant/Ingredients', [
            'location' => $context->load('restaurant'), 'locations' => app(WorkspaceController::class)->locations($request), 'canManage' => true,
            'ingredients' => Ingredient::availableFor($context->restaurant_id)->orderBy('name')->get(),
        ]);
    }

    public function save(Request $request, string $location, ?string $ingredient = null)
    {
        return $this->persist($request, $this->context($request, $location), $ingredient);
    }

    public function destroy(Request $request, string $location, string $ingredient)
    {
        return $this->remove($this->context($request, $location), $ingredient);
    }

    private function context(Request $request, string $location): RestaurantLocation
    {
        $context = RestaurantLocation::where('public_id', $location)->firstOrFail();
        app(TenantAccess::class)->requireManage($request->user(), $context->organization_id);
        app(TenantAccess::class)->requireFeature($request->user(), $context, 'menu', ! $request->isMethod('GET'));

        return $context;
    }

    private function target(?RestaurantLocation $context, string $id): Ingredient
    {
        $model = Ingredient::findOrFail($id);
        abort_unless($model->restaurant_id === $context?->restaurant_id, 403);

        return $model;
    }

    private function persist(Request $request, ?RestaurantLocation $context, ?string $id)
    {
        $model = $id ? $this->target($context, $id) : null;
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150', 'not_regex:/\*/'], 'name_en' => ['nullable', 'string', 'max:150', 'not_regex:/\*/'],
            'contains_allergens' => ['required', 'boolean'], 'from_frozen' => ['required', 'boolean'],
            'is_active' => ['required', 'boolean'], 'needs_review' => ['required', 'boolean'],
        ]);
        $name = trim(preg_replace('/\s+/u', ' ', $data['name']));
        $normalized = Ingredient::normalized($name);
        $scope = $context ? 'restaurant:'.$context->restaurant_id : 'global';
        if ($normalized === '' || Ingredient::withTrashed()->where('scope_key', $scope)->where('normalized_name', $normalized)->when($model, fn ($q) => $q->whereKeyNot($model->id))->exists()
            || ($context && Ingredient::withTrashed()->whereNull('restaurant_id')->where('normalized_name', $normalized)->exists())) {
            throw ValidationException::withMessages(['name' => __('An ingredient with this name already exists. Choose it from the catalogue.')]);
        }
        DB::transaction(function () use ($model, $data, $name, $normalized, $scope, $context) {
            $fields = [...$data, 'name' => $name, 'normalized_name' => $normalized];
            if ($model) {
                $model->update($fields);
                $this->changed($model);
            } else {
                Ingredient::create([...$fields, 'scope_key' => $scope, 'restaurant_id' => $context?->restaurant_id, 'organization_id' => $context?->organization_id]);
            }
        });

        return back()->with('success', __('Ingredient saved.'));
    }

    private function remove(?RestaurantLocation $context, string $id)
    {
        $model = $this->target($context, $id);
        DB::transaction(function () use ($model) {
            $model->update(['is_active' => false]);
            $model->delete();
            $this->changed($model);
        });

        return back()->with('success', __('Ingredient removed. Existing recipes retain their ingredient information.'));
    }

    private function changed(Ingredient $model): void
    {
        $organizations = $model->items()->pluck('menu_items.organization_id')->unique();
        foreach ($organizations as $organization) {
            app(RecordMenuChange::class)->execute($organization);
            foreach (RestaurantLocation::where('organization_id', $organization)->pluck('public_id') as $id) {
                DB::afterCommit(fn () => Cache::forget('public-menu:'.$id));
            }
        }
    }
}
