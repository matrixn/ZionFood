<?php

namespace App\Domain\Imports;

use App\Models\Menu;
use App\Models\MenuItem;

class MenuGraph
{
    public function product(MenuItem $item): array
    {
        $item->load('ingredients', 'variants.modifierGroups.options', 'modifierGroups.options', 'category.modifierGroups.options');

        return ['fields' => $item->only('source_key', 'name', 'description', 'price', 'tax_basis_points', 'image_path', 'portion_size', 'contains_allergens', 'from_frozen', 'content', 'order_types', 'kitchen_name', 'is_available', 'deleted_at'), 'category' => $item->category->only('name', 'sort_order'), 'ingredients' => $item->ingredients->map(fn ($i) => ['id' => $i->id, 'position' => $i->pivot->position, 'name' => $i->name, 'contains_allergens' => $i->contains_allergens, 'from_frozen' => $i->from_frozen])->all(), 'variants' => $item->variants->map(fn ($v) => ['fields' => $v->only('name', 'price', 'is_available', 'is_default', 'nutrition'), 'groups' => $this->groups($v->modifierGroups)])->all(), 'groups' => $this->groups($item->modifierGroups), 'category_groups' => $this->groups($item->category->modifierGroups)];
    }

    public function groups($groups): array
    {
        return $groups->map(fn ($g) => ['source_id' => $g->id, 'fields' => $g->only('name', 'min_choices', 'max_choices', 'is_required', 'allow_quantity'), 'options' => $g->options->map(fn ($o) => $o->only('name', 'price', 'is_available', 'is_default', 'max_quantity', 'kitchen_name'))->all()])->all();
    }

    public function hashes(Menu $menu): array
    {
        $result = [];
        foreach (MenuItem::withTrashed()->whereHas('category', fn ($q) => $q->where('menu_id', $menu->id))->orderBy('id')->get() as $item) {
            $result[$item->source_key ?? ('local:'.$item->public_id)] = $this->hash($this->product($item));
        }ksort($result);

        return $result;
    }

    public function fingerprint(Menu $menu): string
    {
        return $this->hash([$menu->fresh()->only('name', 'is_active'), $this->hashes($menu), $menu->categories()->orderBy('id')->get()->map(fn ($c) => [$c->name, $c->sort_order, $this->groups($c->modifierGroups)])->all()]);
    }

    public function hash($data): string
    {
        return hash('sha256', json_encode($data, JSON_THROW_ON_ERROR));
    }
}
