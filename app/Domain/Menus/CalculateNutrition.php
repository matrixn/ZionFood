<?php

namespace App\Domain\Menus;

use Illuminate\Validation\ValidationException;

class CalculateNutrition
{
    public function calculate(array $content): array
    {
        $content = app(MenuContent::class)->validate($content);
        if (! $content['recipe'] || ! ($content['yield_grams'] ?? null) || ! ($content['servings'] ?? null)) {
            throw ValidationException::withMessages(['recipe' => __('Enter recipe quantities, cooked yield and servings before calculating nutrition.')]);
        }
        $total = [];
        foreach (MenuContent::NUTRIENTS as $key) {
            // Missing supplier/reference data remains unknown, never silently zero.
            $complete = collect($content['recipe'])->every(fn ($row) => isset($row['values'][$key]));
            $total[$key] = $complete ? array_sum(array_map(fn ($row) => (float) $row['grams'] * (float) $row['values'][$key] / 100, $content['recipe'])) : null;
        }
        if (count(array_filter($total, fn ($value) => $value !== null)) === 0) {
            throw ValidationException::withMessages(['recipe' => __('No nutrient has complete reference data for every ingredient.')]);
        }
        $scale = fn ($divisor) => array_map(fn ($value) => $value === null ? null : round($value / $divisor, 3), $total);

        return ['basis' => 'per_100g', 'values' => $scale($content['yield_grams'] / 100), 'reference' => __('Calculated from saved ingredient references and cooked yield.'), 'estimated' => false, 'per_serving' => $scale($content['servings'])];
    }
}
