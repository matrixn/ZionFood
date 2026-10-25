<?php

namespace App\Domain\Menus\Actions;

use App\Domain\Tenancy\TenantAccess;
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
            throw ValidationException::withMessages(['category_id' => 'Category and modifiers must belong to this organization.']);
        }
        if ($item && $item->category->menu->restaurant_id !== $category->menu->restaurant_id) {
            throw ValidationException::withMessages(['category_id' => 'Cannot move products between restaurants.']);
        }

        return DB::transaction(function () use ($data, $organizationId, $item, $ids) {
            $fields = array_intersect_key($data, array_flip(['category_id', 'name', 'description', 'price', 'tax_basis_points', 'is_available', 'image_path']));
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
            $item->modifierGroups()->sync(collect($ids)->mapWithKeys(fn ($id) => [$id => ['organization_id' => $organizationId]])->all());

            return $item->fresh(['variants', 'modifierGroups.options']);
        });
    }
}
