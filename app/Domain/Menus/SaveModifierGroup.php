<?php

namespace App\Domain\Menus;

use App\Domain\Tenancy\TenantAccess;
use App\Models\ModifierGroup;
use App\Models\RestaurantLocation;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class SaveModifierGroup
{
    public function execute(User $actor, RestaurantLocation $location, array $input): ModifierGroup
    {
        $context = $location;
        app(TenantAccess::class)->requireFeature($actor, $context, 'menu', true);
        abort_if(RestaurantLocation::where('organization_id', $context->organization_id)->get()->contains(fn ($l) => ! app(TenantAccess::class)->canFeature($actor, $l, 'menu', true)), 403);
        $data = validator($input, ['id' => ['nullable', 'integer'], 'name' => ['required', 'string', 'max:100'], 'is_required' => ['sometimes', 'boolean'], 'allow_quantity' => ['sometimes', 'boolean'], 'min_choices' => ['required', 'integer', 'min:0', 'max:20'], 'max_choices' => ['required', 'integer', 'gte:min_choices', 'max:20'], 'options' => ['required', 'array', 'min:1', 'max:30'], 'options.*.name' => ['required', 'string', 'max:100'], 'options.*.price' => ['required', 'integer', 'min:0', 'max:1000000'], 'options.*.is_default' => ['sometimes', 'boolean'], 'options.*.max_quantity' => ['sometimes', 'integer', 'min:1', 'max:50'], 'options.*.kitchen_name' => ['nullable', 'string', 'max:150']])->validate();
        abort_if($data['min_choices'] > count($data['options']), 422, 'Not enough options for the minimum selection.');
        $defaultCount = count(array_filter($data['options'], fn ($o) => $o['is_default'] ?? false));
        abort_if($defaultCount > $data['max_choices'] || ($defaultCount > 0 && $defaultCount < max(($data['is_required'] ?? ($data['min_choices'] > 0)) ? 1 : 0, $data['min_choices'])) || (($data['is_required'] ?? false) && $data['max_choices'] < 1), 422, 'Invalid modifier defaults or selection limits.');

        return DB::transaction(function () use ($data, $context) {
            MenuWriteLock::organization($context->organization_id);
            $group = isset($data['id']) ? ModifierGroup::whereKey($data['id'])->where('organization_id', $context->organization_id)->firstOrFail() : new ModifierGroup(['organization_id' => $context->organization_id]);
            $group->fill(array_intersect_key($data, array_flip(['name', 'min_choices', 'max_choices', 'is_required', 'allow_quantity'])))->save();
            $group->options()->delete();
            foreach ($data['options'] as $option) {
                $group->options()->create(array_intersect_key($option, array_flip(['name', 'price', 'is_default', 'max_quantity', 'kitchen_name'])));
            }
            app(RecordMenuChange::class)->execute($context->organization_id);

            return $group;
        });

    }
}
