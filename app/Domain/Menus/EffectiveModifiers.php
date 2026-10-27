<?php

namespace App\Domain\Menus;

use App\Models\MenuItem;
use App\Models\MenuItemVariant;
use Illuminate\Support\Collection;

class EffectiveModifiers
{
    public function groups(MenuItem $item, ?MenuItemVariant $variant = null): Collection
    {
        return $item->category->modifierGroups->concat($item->modifierGroups)->concat($variant?->modifierGroups ?? collect())->unique('id')->values();
    }

    public function validCount($group, int $count): bool
    {
        $required = $group->is_required ?? ($group->min_choices > 0);

        return (! $required && $count === 0) || ($count >= max($required ? 1 : 0, $group->min_choices) && $count <= $group->max_choices);
    }

    public function publicGroup($g): array
    {
        return [...$g->only('id', 'name', 'min_choices', 'max_choices', 'is_required', 'allow_quantity'), 'options' => $g->options->where('is_available', true)->map(fn ($o) => $o->only('id', 'name', 'price', 'is_default', 'max_quantity'))->values()];
    }
}
