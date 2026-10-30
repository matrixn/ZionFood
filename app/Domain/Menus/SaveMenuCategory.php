<?php

namespace App\Domain\Menus;

use App\Domain\Tenancy\TenantAccess;
use App\Models\MenuCategory;
use App\Models\ModifierGroup;
use App\Models\RestaurantLocation;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class SaveMenuCategory
{
    public function execute(User $actor, RestaurantLocation $location, array $input): MenuCategory
    {
        $context = $location;
        app(TenantAccess::class)->requireFeature($actor, $context, 'menu', true);
        $data = validator($input, ['name' => ['required', 'string', 'max:100'], 'menu_id' => ['required', 'integer'], 'id' => ['nullable', 'integer'], 'modifier_group_ids' => ['sometimes', 'array', 'max:20'], 'modifier_group_ids.*' => ['integer', 'distinct']])->validate();
        abort_unless($context->menus()->where('menus.id', $data['menu_id'])->exists(), 422);
        app(TenantAccess::class)->requireMenu($actor, $context->organization_id, $data['menu_id']);

        return DB::transaction(function () use ($data, $context) {
            MenuWriteLock::organization($context->organization_id);
            $category = isset($data['id']) ? MenuCategory::whereKey($data['id'])->where('menu_id', $data['menu_id'])->where('organization_id', $context->organization_id)->firstOrFail() : new MenuCategory(['organization_id' => $context->organization_id, 'menu_id' => $data['menu_id']]);
            $category->fill(['name' => $data['name']])->save();
            if (isset($data['modifier_group_ids'])) {
                abort_unless(ModifierGroup::where('organization_id', $context->organization_id)->whereIn('id', $data['modifier_group_ids'])->count() === count($data['modifier_group_ids']), 422);
                $category->modifierGroups()->sync(collect($data['modifier_group_ids'])->mapWithKeys(fn ($id) => [$id => ['organization_id' => $context->organization_id]])->all());
            }

            app(RecordMenuChange::class)->execute($context->organization_id, $data['menu_id']);

            return $category;
        });

    }
}
