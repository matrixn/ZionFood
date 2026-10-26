<?php

namespace App\Domain\Menus\Actions;

use App\Domain\Menus\RecordMenuChange;
use App\Domain\Tenancy\TenantAccess;
use App\Models\Ingredient;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\ModifierGroup;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class UpdateMenuItem
{
    public function execute(User $actor, int $organizationId, array $data, ?MenuItem $item = null): MenuItem
    {
        app(TenantAccess::class)->requireManage($actor, $organizationId);
        abort_if($item && $item->organization_id !== $organizationId, 403);
        $category = MenuCategory::whereKey($data['category_id'])->where('organization_id', $organizationId)->first();
        $ids = $data['modifier_group_ids'] ?? [];
        if (! $category || ModifierGroup::where('organization_id', $organizationId)->whereIn('id', $ids)->count() !== count($ids)) {
            throw ValidationException::withMessages(['category_id' => __('Category and modifiers must belong to this organization.')]);
        }
        if ($item && $item->category->menu->restaurant_id !== $category->menu->restaurant_id) {
            throw ValidationException::withMessages(['category_id' => __('Cannot move products between restaurants.')]);
        }

        $oldMenuId = $item?->category?->menu_id;

        return DB::transaction(function () use ($data, $organizationId, $item, $ids, $category, $oldMenuId) {
            $fields = array_intersect_key($data, array_flip(['category_id', 'name', 'description', 'price', 'tax_basis_points', 'is_available', 'image_path', 'portion_size', 'contains_allergens', 'from_frozen']));
            if ($item) {
                $item->update($fields);
            } else {
                $item = MenuItem::create(['organization_id' => $organizationId, ...$fields]);
            }
            if (array_key_exists('variants', $data)) {
                $item->variants()->delete();
                foreach ($data['variants'] as $variant) {
                    $item->variants()->create(['name' => $variant['name'], 'price' => $variant['price']]);
                }
            }
            if (array_key_exists('ingredient_ids', $data)) {
                $ingredientIds = $data['ingredient_ids'];
                $previous = $item->ingredients()->pluck('ingredients.id');
                $ingredients = Ingredient::withTrashed()->whereIn('id', $ingredientIds)->lockForUpdate()->get();
                if ($ingredients->count() !== count($ingredientIds) || $ingredients->contains(fn ($i) => ($i->restaurant_id !== null && $i->restaurant_id !== $category->menu->restaurant_id) || ((! $i->is_active || $i->trashed()) && ! $previous->contains($i->id)))) {
                    throw ValidationException::withMessages(['ingredient_ids' => __('Choose available ingredients for this restaurant.')]);
                }
                $item->ingredients()->sync(collect($ingredientIds)->mapWithKeys(fn ($id, $position) => [$id => ['organization_id' => $organizationId, 'position' => $position]])->all());
            }
            $item->modifierGroups()->sync(collect($ids)->mapWithKeys(fn ($id) => [$id => ['organization_id' => $organizationId]])->all());
            app(RecordMenuChange::class)->execute($organizationId, $category->menu_id, ['item_id' => $item->public_id]);
            if ($oldMenuId && $oldMenuId !== $category->menu_id) {
                app(RecordMenuChange::class)->execute($organizationId, $oldMenuId, ['item_id' => $item->public_id, 'removed' => true]);
            }

            return $item->fresh(['variants', 'modifierGroups.options', 'ingredients']);
        });
    }
}
