<?php

namespace App\Domain\Imports;

use App\Domain\Menus\MenuContent;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class GloriaFoodAdapter
{
    public const VERSION = '1.0.1';

    private array $issues = [];

    public function identity(array $source): array
    {
        Validator::make($source, ['id' => 'required|integer|min:1', 'restaurant_id' => 'nullable|integer|min:1'])->validate();
        $restaurant = isset($source['restaurant_id']) && $source['restaurant_id'] !== '' ? (string) $source['restaurant_id'] : null;

        return ['source_restaurant' => $restaurant ?? 'menu:'.$source['id'], 'source_menu' => (string) $source['id'], 'reported_restaurant_id' => $restaurant, 'resolved_restaurant_id' => $restaurant];
    }

    public function normalize(array $source, array $settings): array
    {
        $this->issues = [];
        $identity = $this->identity($source);
        Validator::make($source, ['currency' => 'required|string|size:3', 'categories' => 'required|array|min:1|max:200', 'categories.*.id' => 'required|integer|distinct', 'categories.*.name' => 'required|string|max:100', 'categories.*.items' => 'present|array|max:2000'])->validate();
        $categories = [];
        $seen = [];
        $count = 0;
        $evidenceBudget = [0, 0, 0];
        foreach ($source['categories'] as $ci => $c) {
            $category = ['key' => (string) $c['id'], 'name' => $c['name'], 'groups' => $this->groups($c['groups'] ?? [], "categories.$ci.groups"), 'items' => []];
            foreach ($c['items'] as $ii => $i) {
                if (++$count > 2000) {
                    throw ValidationException::withMessages(['source' => __('The menu exceeds the import limit.')]);
                }
                $path = "categories.$ci.items.$ii";
                Validator::make($i, ['id' => 'required|integer|min:1', 'name' => 'required|string|max:150', 'description' => 'nullable|string|max:2000', 'price' => 'required|numeric', 'sizes' => 'sometimes|array|max:20', 'sizes.*.id' => 'required|integer|distinct', 'groups' => 'sometimes|array|max:20', 'extras' => 'nullable|array', 'tags' => 'nullable|array|max:8'])->validate();
                if (isset($seen[$i['id']])) {
                    $this->issue('blocker', 'duplicate_item', $path, 'Duplicate source product ID.');
                }$seen[$i['id']] = true;
                $extras = $i['extras'] ?? [];
                $content = app(MenuContent::class)->empty();
                $aliases = ['milk' => 'milk', 'cereals' => 'gluten', 'cereals containing gluten' => 'gluten', 'wheat' => 'gluten', 'crustaceans' => 'crustaceans', 'shellfish' => 'crustaceans', 'egg' => 'eggs', 'soybeans' => 'soy', 'soya' => 'soy', 'tree nuts' => 'nuts', 'sesame seeds' => 'sesame', 'sulphur dioxide and sulphites' => 'sulphites'];
                $allergens = $extras['menu_item_allergens_values'] ?? [];
                if (! $allergens && ! empty($extras['menu_item_allergens_ids'])) {
                    $this->issue('blocker', 'allergen_ids', $path, 'Deprecated allergen IDs have no names; confirm them in the source before importing.');
                }
                foreach ($allergens as $a) {
                    $name = strtolower(trim((string) ($a['name'] ?? '')));
                    $code = $aliases[$name] ?? $name;
                    if (! in_array($code, MenuContent::ALLERGENS, true)) {
                        $this->issue('blocker', 'unknown_allergen', $path, 'Unmapped allergen: '.$name);
                    } else {
                        $content['allergens'][] = $code;
                    }
                }
                $content['allergens'] = array_values(array_unique($content['allergens']));
                foreach ($i['tags'] ?? [] as $tag) {
                    $label = strtolower((string) $tag);
                    if (! in_array($label, MenuContent::LABELS, true)) {
                        $this->issue('blocker', 'unknown_tag', $path, 'Unmapped dietary label: '.$tag);
                    } else {
                        $content['labels'][] = $label;
                    }
                }
                $content['nutrition'] = $this->nutrition($extras['menu_item_nutritional_values'] ?? null, $settings, $path, $extras['menu_item_nutritional_values_size'] ?? null);
                try {
                    $content = app(MenuContent::class)->validate($content);
                } catch (ValidationException) {
                    $this->issue('blocker', 'food_conflict', $path, 'Food declarations conflict or contain invalid values; correct the source.');
                }
                $types = $extras['menu_item_order_types'] ?? null;
                if ($types !== null && (! is_array($types) || array_diff($types, ['pickup', 'delivery', 'dine_in']))) {
                    $this->issue('blocker', 'order_types', $path, 'Unsupported order type. Correct it before publication.');
                }
                $price = $this->money($i['price'], $path);
                $sizes = [];
                foreach ($i['sizes'] ?? [] as $si => $size) {
                    Validator::make($size, ['id' => 'required|integer|distinct', 'name' => 'required|string|max:100', 'price' => 'required|numeric', 'default' => 'sometimes|boolean'])->validate();
                    $sizePrice = $this->money($size['price'], "$path.sizes.$si", true);
                    $absolute = $settings['size_prices'] === 'base_plus_size' ? $price + $sizePrice : $sizePrice;
                    if ($absolute < 0 || $absolute > 10000000) {
                        $this->issue('blocker', 'size_price', $path, 'Size price is outside the supported range.');
                    }$sizes[] = ['key' => (string) $size['id'], 'name' => $size['name'], 'price' => $absolute, 'is_default' => (bool) ($size['default'] ?? false), 'nutrition' => $this->nutrition($size['extras']['menu_item_nutritional_values'] ?? null, $settings, "$path.sizes.$si", $size['extras']['menu_item_nutritional_values_size'] ?? null), 'groups' => $this->groups($size['groups'] ?? [], "$path.sizes.$si.groups")];
                }
                if (count(array_filter($sizes, fn ($s) => $s['is_default'])) > 1) {
                    $this->issue('blocker', 'defaults', $path, 'More than one default size.');
                }
                $item = ['key' => (string) $i['id'], 'name' => $i['name'], 'description' => $i['description'] ?? '', 'price' => $price, 'order_types' => $types, 'kitchen_name' => $extras['menu_item_kitchen_internal_name'] ?? null, 'content' => $content, 'variants' => $sizes, 'groups' => $this->groups($i['groups'] ?? [], "$path.groups")];
                app(ImportVerification::class)->includeItemInBudget($category['groups'], $item, strtoupper($source['currency']), $evidenceBudget);
                $category['items'][] = $item;
            }$categories[] = $category;
        }
        $this->issue('warning', 'photos', 'menu', 'Photos are not supplied by this API; upload or retain them in the menu editor.');
        $this->issue('warning', 'operations', 'menu', 'Review hours, delivery zones, taxes and services separately; payments remain placeholders.');
        $this->issue('warning', 'source_rules', 'menu', 'Undocumented active/schedule/promotion fields are not interpreted. Confirm availability against the source.');
        $this->issue('warning', 'semantics', 'menu', 'Confirm size-price mode, distinct-option selection limits and nutrition basis against your source menu.');

        return ['menu' => [...$identity, 'currency' => strtoupper($source['currency']), 'categories' => $categories], 'issues' => $this->issues];
    }

    private function groups(array $groups, string $path): array
    {
        Validator::make(['groups' => $groups], ['groups' => 'array|max:20', 'groups.*.id' => 'required|integer|distinct', 'groups.*.name' => 'required|string|max:100', 'groups.*.required' => 'required|boolean', 'groups.*.allow_quantity' => 'required|boolean', 'groups.*.force_min' => 'required|integer|min:0|max:20', 'groups.*.force_max' => 'required|integer|min:0|max:20', 'groups.*.options' => 'required|array|min:1|max:30'])->validate();

        return array_map(function ($g) use ($path) {
            $options = [];
            foreach ($g['options'] as $o) {
                Validator::make($o, ['id' => 'required|integer', 'name' => 'required|string|max:100', 'price' => 'required|numeric', 'default' => 'sometimes|boolean'])->validate();
                $options[] = ['key' => (string) $o['id'], 'name' => $o['name'], 'price' => $this->money($o['price'], $path), 'is_default' => (bool) ($o['default'] ?? false), 'kitchen_name' => $o['extras']['menu_option_kitchen_internal_name'] ?? null];
            }
            $min = max($g['required'] ? 1 : 0, $g['force_min']);
            $defaults = count(array_filter($options, fn ($o) => $o['is_default']));
            if ($g['force_max'] < $min || $min > count($options) || $defaults > $g['force_max'] || ($defaults > 0 && $defaults < $min) || count(array_unique(array_column($options, 'key'))) !== count($options)) {
                $this->issue('blocker', 'group_rules', $path, 'Impossible selection rules, duplicate option IDs or invalid defaults.');
            }

            return ['key' => (string) $g['id'], 'name' => $g['name'], 'min_choices' => $g['force_min'], 'max_choices' => $g['force_max'], 'is_required' => $g['required'], 'allow_quantity' => $g['allow_quantity'], 'options' => $options];
        }, $groups);
    }

    private function nutrition($rows, array $settings, string $path, ?string $basis = null): ?array
    {
        if (! $rows) {
            return null;
        }
        if (! is_array($rows)) {
            $this->issue('blocker', 'nutrition', $path, 'Invalid nutritional values.');

            return null;
        }
        $values = [];
        $aliases = ['calories' => 'energy_kcal', 'energy' => 'energy_kj', 'fat' => 'fat_g', 'saturated fat' => 'saturates_g', 'saturates' => 'saturates_g', 'carbohydrates' => 'carbohydrate_g', 'carbohydrate' => 'carbohydrate_g', 'sugars' => 'sugars_g', 'protein' => 'protein_g', 'salt' => 'salt_g', 'fiber' => 'fibre_g', 'fibre' => 'fibre_g'];
        foreach ($rows as $row) {
            if (! is_array($row)) {
                $this->issue('blocker', 'nutrition', $path, 'Nutrition entry must include name and value.');

                continue;
            }$name = strtolower(trim((string) ($row['name'] ?? '')));
            $key = $aliases[$name] ?? $name;
            if (! $name && isset($row['id'])) {
                $key = $settings['nutrition_map'][(string) $row['id']] ?? '';
                if (! $key) {
                    $this->issue('blocker', 'nutrient_id', $path.'.nutrition.'.(string) $row['id'], 'Map source nutrient ID '.(string) $row['id'].' to a confirmed nutrient.');

                    continue;
                }
            }$unit = strtolower((string) ($row['unit'] ?? ''));
            if ($name === 'energy' && $unit === 'kcal') {
                $key = 'energy_kcal';
            }$value = $row['value'] ?? null;
            if (! in_array($key, MenuContent::NUTRIENTS, true) || ! is_numeric($value) || $value < 0 || ($unit && ! in_array($unit, ['g', 'kj', 'kcal'], true))) {
                $this->issue('blocker', 'nutrition', $path, 'Unmapped nutritional name, unit or missing numeric value: '.$name);

                continue;
            }
            $expected = str_ends_with($key, '_g') ? 'g' : (str_ends_with($key, '_kcal') ? 'kcal' : 'kj');
            if ($unit && $unit !== $expected) {
                $this->issue('blocker', 'nutrition_unit', $path, 'Nutrient unit does not match '.$key);
            }
            if (array_key_exists($key, $values)) {
                $this->issue('blocker', 'nutrition_duplicate', $path, 'Multiple source values map to the same nutrient.');
            }$values[$key] = (float) $value;
        }
        if ($basis !== null && ! in_array($basis, ['per_100g', 'per_100ml', 'per_serving'], true)) {
            $this->issue('blocker', 'nutrition_basis', $path, 'Unknown source nutrition basis.');
        }

        $nutrition = ['basis' => $basis ?? $settings['nutrition_basis'], 'values' => $values, 'reference' => 'GloriaFood Fetch Menu v2; restaurant confirmation required', 'estimated' => false];
        try {
            app(MenuContent::class)->validate([...app(MenuContent::class)->empty(), 'nutrition' => $nutrition]);
        } catch (ValidationException) {
            $this->issue('blocker', 'nutrition_range', $path, 'Nutrition exceeds supported ranges or has an invalid basis.');
        }

        return $nutrition;
    }

    private function money($value, string $path, bool $signed = false): int
    {
        $s = (string) $value;
        if (! preg_match('/^-?\d+(?:\.\d{1,2})?$/D', $s)) {
            $this->issue('blocker', 'price_precision', $path, 'Price must have at most two decimal places.');

            return 0;
        }
        $negative = str_starts_with($s, '-');
        $parts = explode('.', ltrim($s, '-'));
        $n = ((int) $parts[0] * 100 + (int) str_pad($parts[1] ?? '', 2, '0')) * ($negative ? -1 : 1);
        if ($n > 10000000 || $n < ($signed ? -10000000 : 0)) {
            $this->issue('blocker', 'price_range', $path, 'Price is outside the supported range.');
        }

        return $n;
    }

    private function issue(string $severity, string $code, string $path, string $message): void
    {
        $this->issues[] = compact('severity', 'code', 'path', 'message');
    }
}
