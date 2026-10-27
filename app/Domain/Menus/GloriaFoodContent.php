<?php

namespace App\Domain\Menus;

use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class GloriaFoodContent
{
    public function read(array $item): array
    {
        Validator::make($item, ['id' => ['required', 'integer'], 'source_restaurant_id' => ['nullable', 'integer'], 'name' => ['required', 'string', 'max:150'], 'tags' => ['nullable', 'array', 'max:50'], 'tags.*' => ['string', 'max:100'], 'extras' => ['nullable', 'array'], 'extras.menu_item_allergens_values' => ['nullable', 'array', 'max:50'], 'extras.menu_item_allergens_values.*' => ['array'], 'extras.menu_item_allergens_values.*.name' => ['nullable', 'string', 'max:100'], 'extras.menu_item_nutritional_values' => ['nullable', 'array', 'max:50'], 'extras.menu_item_nutritional_values.*' => ['array'], 'extras.menu_item_nutritional_values.*.name' => ['nullable', 'string', 'max:100'], 'sizes' => ['sometimes', 'array', 'max:20'], 'sizes.*' => ['array']])->validate();
        $extras = $item['extras'] ?? [];
        $aliases = ['cereals containing gluten' => 'gluten', 'gluten' => 'gluten', 'crustaceans' => 'crustaceans', 'eggs' => 'eggs', 'fish' => 'fish', 'peanuts' => 'peanuts', 'soybeans' => 'soy', 'soy' => 'soy', 'milk' => 'milk', 'nuts' => 'nuts', 'celery' => 'celery', 'mustard' => 'mustard', 'sesame seeds' => 'sesame', 'sulphur dioxide and sulphites' => 'sulphites', 'lupin' => 'lupin', 'molluscs' => 'molluscs'];
        $allergens = [];
        $unknown = [];
        foreach ($extras['menu_item_allergens_values'] ?? [] as $row) {
            $name = is_array($row) ? ($row['name'] ?? '') : '';
            if (isset($aliases[Str::lower(trim((string) $name))])) {
                $allergens[] = $aliases[Str::lower(trim((string) $name))];
            } else {
                $unknown[] = $row;
            }
        }
        $tags = array_map(fn ($tag) => Str::lower($tag), $item['tags'] ?? []);
        $nutrition = null;
        $values = [];
        $nutrients = ['energy (kj)' => 'energy_kj', 'energy (kcal)' => 'energy_kcal', 'fat' => 'fat_g', 'saturated fat' => 'saturates_g', 'carbohydrate' => 'carbohydrate_g', 'sugars' => 'sugars_g', 'protein' => 'protein_g', 'salt' => 'salt_g', 'fibre' => 'fibre_g'];
        $unmappedNutrition = [];
        foreach ($extras['menu_item_nutritional_values'] ?? [] as $row) {
            $key = $nutrients[Str::lower((string) ($row['name'] ?? ''))] ?? null;
            if ($key && isset($row['value']) && is_numeric($row['value']) && is_finite((float) $row['value']) && (float) $row['value'] >= 0) {
                $values[$key] = (float) $row['value'];
            } else {
                $unmappedNutrition[] = $row;
            }
        }
        $basis = $extras['menu_item_nutritional_values_size'] ?? null;
        if ($values && in_array($basis, ['per_100g', 'per_100ml', 'per_serving'], true)) {
            $nutrition = ['basis' => $basis, 'values' => $values, 'estimated' => false, 'reference' => 'GloriaFood Fetch Menu v2'];
        }
        $raw = array_intersect_key($extras, array_flip(['menu_item_allergens_ids', 'menu_item_allergens_values', 'menu_item_nutritional_values', 'menu_item_nutritional_values_size']));
        $proposal = [...(! empty($extras['menu_item_allergens_values']) && $allergens ? ['allergens' => array_values(array_unique($allergens))] : []), ...($tags ? ['labels' => array_values(array_intersect($tags, MenuContent::LABELS))] : []), ...($nutrition ? ['nutrition' => $nutrition] : [])];

        return ['proposal' => $proposal, 'report' => ['source' => 'gloriafood_fetch_menu_v2', 'source_namespace' => 'gloriafood:'.($item['source_restaurant_id'] ?? 'unknown').':item:'.$item['id'], 'source_item_id' => $item['id'], 'source_name' => $item['name'], 'checksum' => hash('sha256', json_encode([$item['id'], $raw, $tags], JSON_THROW_ON_ERROR)), 'raw_extras' => $raw, 'unmapped_allergens' => $unknown, 'unmapped_tags' => array_values(array_diff($tags, MenuContent::LABELS)), 'unmapped_nutrition' => $unmappedNutrition, 'nutrition_basis_missing' => $values && ! $nutrition, 'legacy_allergen_ids_unmapped' => ! empty($extras['menu_item_allergens_ids']) && empty($extras['menu_item_allergens_values']), 'sizes' => array_map(fn ($size) => array_intersect_key($size, array_flip(['id', 'name', 'extras'])), $item['sizes'] ?? []), 'warnings' => ['Metadata only: prices, variants, modifier rules and photographs are not imported here.', 'Review source identity, allergens, dietary claims and nutritional basis before publishing. Internal provider IDs are never treated as EU allergen numbers.']]];
    }
}
