<?php

namespace App\Domain\Menus;

use App\Models\RestaurantLocation;

class PublicMenuTree
{
    public function build(RestaurantLocation $location): array
    {
        $menus = $location->menus()->where('is_active', true)->with(['categories' => fn ($q) => $q->orderBy('sort_order'), 'categories.items' => fn ($q) => $q->where('is_available', true), 'categories.items.ingredients', 'categories.items.variants', 'categories.items.modifierGroups.options'])->get();

        return $menus->map(function ($menu) {
            return ['id' => $menu->id, 'name' => $menu->name, 'categories' => $menu->categories->map(function ($category) use ($menu) {
                $category->setRelation('menu', $menu);

                return ['id' => $category->id, 'name' => $category->name, 'items' => $category->items->map(function ($item) use ($category) {
                    $item->setRelation('category', $category);
                    $content = app(MenuContent::class)->publicData($item);

                    return [...$item->only('id', 'public_id', 'name', 'description', 'price', 'tax_basis_points', 'image_path', 'portion_size', 'from_frozen'), 'content' => $content,
                        'contains_allergens' => $item->contains_allergens || count($content['allergens']) > 0 || $item->ingredients->contains('contains_allergens', true), 'from_frozen' => $item->from_frozen || $item->ingredients->contains('from_frozen', true),
                        'ingredients' => $item->ingredients->map(fn ($i) => $i->only('id', 'name', 'name_en', 'contains_allergens', 'from_frozen')),
                        'variants' => $item->variants->where('is_available', true)->map(fn ($v) => $v->only('id', 'name', 'price'))->values(),
                        'modifier_groups' => $item->modifierGroups->map(fn ($g) => [...$g->only('id', 'name', 'min_choices', 'max_choices'), 'options' => $g->options->where('is_available', true)->map(fn ($o) => $o->only('id', 'name', 'price'))->values()]),
                    ];
                })];
            })];
        })->all();
    }
}
