<?php

namespace App\Domain\Menus;

use App\Models\MenuItem;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class MenuContent
{
    public const SCHEMA_VERSION = 1;

    public const ALLERGENS = ['gluten', 'crustaceans', 'eggs', 'fish', 'peanuts', 'soy', 'milk', 'nuts', 'celery', 'mustard', 'sesame', 'sulphites', 'lupin', 'molluscs'];

    public const LABELS = ['vegetarian', 'vegan', 'gluten_free', 'halal', 'nut_free', 'dairy_free', 'hot', 'raw'];

    public const NUTRIENTS = ['energy_kj', 'energy_kcal', 'fat_g', 'saturates_g', 'carbohydrate_g', 'sugars_g', 'protein_g', 'salt_g', 'fibre_g'];

    public function validate(array $data): array
    {
        $rules = [
            'allergens' => ['present', 'array', 'max:14'], 'allergens.*' => ['string', 'distinct', Rule::in(self::ALLERGENS)],
            'may_contain' => ['present', 'array', 'max:14'], 'may_contain.*' => ['string', 'distinct', Rule::in(self::ALLERGENS)],
            'labels' => ['present', 'array', 'max:8'], 'labels.*' => ['string', 'distinct', Rule::in(self::LABELS)],
            'nutrition' => ['nullable', 'array:basis,values,reference,estimated'],
            'nutrition.basis' => ['required_with:nutrition', Rule::in(['per_100g', 'per_100ml', 'per_serving'])],
            'nutrition.reference' => ['required_with:nutrition', 'string', 'max:500'], 'nutrition.estimated' => ['required_with:nutrition', 'boolean'],
            'nutrition.values' => ['required_with:nutrition', 'array:'.implode(',', self::NUTRIENTS)],
            'translations' => ['present', 'array:'.implode(',', array_keys(config('menu-content.languages')))],
            'recipe' => ['present', 'array', 'max:100'],
            'recipe.*' => ['array:name,grams,reference,values'], 'recipe.*.name' => ['required', 'string', 'max:150'],
            'recipe.*.grams' => ['required', 'numeric', 'gt:0', 'max:100000'],
            'recipe.*.reference' => ['required', 'string', 'max:500'],
            'recipe.*.values' => ['required', 'array:'.implode(',', self::NUTRIENTS)],
            'yield_grams' => ['nullable', 'numeric', 'gt:0', 'max:100000'],
            'servings' => ['nullable', 'numeric', 'gt:0', 'max:1000'],
        ];
        foreach (self::NUTRIENTS as $key) {
            $max = str_starts_with($key, 'energy_') ? 10000 : 100;
            $rules['nutrition.values.'.$key] = ['nullable', 'numeric', 'min:0', 'max:'.(in_array($data['nutrition']['basis'] ?? null, ['per_100g', 'per_100ml'], true) ? $max : $max * 100)];
            $rules['recipe.*.values.'.$key] = ['nullable', 'numeric', 'min:0', 'max:'.$max];
        }
        foreach (array_keys(config('menu-content.languages')) as $language) {
            $rules['translations.'.$language] = ['sometimes', 'array:name,description,ingredients_text,portion_size,category_name,menu_name,variants,groups,source_hash'];
            foreach (['name' => 150, 'description' => 2000, 'ingredients_text' => 3000, 'portion_size' => 50, 'category_name' => 100, 'menu_name' => 150] as $field => $length) {
                $rules["translations.$language.$field"] = ['nullable', 'string', 'max:'.$length];
            }
            $rules["translations.$language.source_hash"] = ['nullable', 'string', 'size:64'];
            $rules["translations.$language.variants"] = ['sometimes', 'array', 'max:20'];
            $rules["translations.$language.variants.*"] = ['string', 'max:100'];
            $rules["translations.$language.groups"] = ['sometimes', 'array', 'max:20'];
            $rules["translations.$language.groups.*"] = ['array:name,options'];
            $rules["translations.$language.groups.*.name"] = ['required', 'string', 'max:100'];
            $rules["translations.$language.groups.*.options"] = ['required', 'array', 'max:30'];
            $rules["translations.$language.groups.*.options.*"] = ['string', 'max:100'];
        }
        $validated = Validator::make($data, $rules)->validate();
        if ($validated['nutrition']['estimated'] ?? false) {
            throw ValidationException::withMessages(['nutrition' => __('AI nutrition estimates are disabled. Use recipe reference data.')]);
        }
        $allergens = $validated['allergens'];
        $conflicts = ['gluten_free' => ['gluten'], 'dairy_free' => ['milk'], 'nut_free' => ['nuts', 'peanuts'], 'vegan' => ['milk', 'eggs', 'fish', 'crustaceans', 'molluscs'], 'vegetarian' => ['fish', 'crustaceans', 'molluscs']];
        foreach ($validated['labels'] as $label) {
            if (array_intersect($allergens, $conflicts[$label] ?? [])) {
                throw ValidationException::withMessages(['labels' => __('A dietary label conflicts with the declared allergens.')]);
            }
        }

        return $validated;
    }

    public function empty(): array
    {
        return ['allergens' => [], 'may_contain' => [], 'labels' => [], 'nutrition' => null, 'translations' => [], 'recipe' => [], 'yield_grams' => null, 'servings' => null];
    }

    public function source(MenuItem $item): array
    {
        $item->loadMissing('ingredients', 'variants', 'modifierGroups.options', 'category.menu');

        return [...$item->only('name', 'description', 'portion_size'), 'contains_allergens' => (bool) $item->contains_allergens, 'from_frozen' => (bool) $item->from_frozen, 'ingredients' => $item->ingredients->map(fn ($i) => $i->only('id', 'name', 'contains_allergens', 'from_frozen'))->all(), 'variants' => $item->variants->map(fn ($v) => $v->only('id', 'name'))->all(), 'groups' => $item->modifierGroups->map(fn ($g) => ['id' => $g->id, 'name' => $g->name, 'options' => $g->options->map(fn ($o) => $o->only('id', 'name'))->all()])->all(), 'category_name' => $item->category->name, 'menu_name' => $item->category->menu->name];
    }

    public function translationHash(MenuItem $item): string
    {
        return hash('sha256', json_encode($this->source($item), JSON_THROW_ON_ERROR));
    }

    public function hash(MenuItem $item): string
    {
        return hash('sha256', json_encode([$this->source($item), $item->content, (int) $item->content_revision, $item->deleted_at], JSON_THROW_ON_ERROR));
    }

    public function publicData(MenuItem $item): array
    {
        $content = $item->content ?? $this->empty();
        $hash = $this->translationHash($item);
        $translations = array_filter($content['translations'] ?? [], fn ($row) => ($row['source_hash'] ?? null) === $hash);

        return ['allergens' => $content['allergens'] ?? [], 'may_contain' => $content['may_contain'] ?? [], 'labels' => $content['labels'] ?? [], 'nutrition' => isset($content['nutrition']) ? array_intersect_key($content['nutrition'], array_flip(['basis', 'values', 'estimated'])) : null, 'translations' => $translations];
    }
}
