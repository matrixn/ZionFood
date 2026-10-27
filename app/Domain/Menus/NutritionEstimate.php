<?php

namespace App\Domain\Menus;

use App\Models\MenuItem;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class NutritionEstimate
{
    public function inputs(MenuItem $item): array
    {
        $saved = $item->content['nutrition_inputs'] ?? [];
        $grams = $saved['portion_grams'] ?? null;
        if (! $grams && preg_match('/^\s*(\d+(?:[.,]\d+)?)\s*(g|gr|kg)\s*$/iu', $item->portion_size ?? '', $match)) {
            $grams = (float) str_replace(',', '.', $match[1]) * (strtolower($match[2]) === 'kg' ? 1000 : 1);
        }
        $item->loadMissing('ingredients');
        $ingredients = $saved['ingredients_text'] ?? ($item->ingredients->isNotEmpty() ? $item->ingredients->pluck('name')->implode(', ') : ($item->description ?? ''));

        return ['portion_grams' => $grams, 'ingredients_text' => $ingredients, 'preparation' => $saved['preparation'] ?? ''];
    }

    public function requireInputs(array $inputs): array
    {
        return Validator::make($inputs, ['portion_grams' => ['required', 'numeric', 'min:1', 'max:10000'], 'ingredients_text' => ['required', 'string', 'min:3', 'max:3000'], 'preparation' => ['nullable', 'string', 'max:500']])->validate();
    }

    public function fromOutput(array $output, array $inputs): array
    {
        $inputs = $this->requireInputs($inputs);
        $grams = (float) $inputs['portion_grams'];
        $rules = ['values' => ['required', 'array:'.implode(',', array_diff(MenuContent::NUTRIENTS, ['energy_kj']))], 'assumptions' => ['present', 'array', 'max:8'], 'assumptions.*' => ['string', 'max:500']];
        foreach (array_diff(MenuContent::NUTRIENTS, ['energy_kj']) as $key) {
            $rules['values.'.$key] = [$key === 'fibre_g' ? 'present' : 'required', ...($key === 'fibre_g' ? ['nullable'] : []), 'numeric', 'min:0', 'max:'.($key === 'energy_kcal' ? $grams * 9 : $grams)];
        }
        $data = Validator::make($output, $rules)->validate();
        $values = $data['values'];
        if ($values['saturates_g'] > $values['fat_g'] || $values['sugars_g'] > $values['carbohydrate_g'] || $grams + 0.1 < $values['fat_g'] + $values['carbohydrate_g'] + $values['protein_g'] + $values['salt_g']) {
            throw ValidationException::withMessages(['nutrition' => __('The nutritional estimate is inconsistent. Please generate it again.')]);
        }
        $values['energy_kj'] = round($values['energy_kcal'] * 4.184, 3);
        $perServing = array_map(fn ($value) => $value === null ? null : round((float) $value, 3), $values);

        return ['basis' => 'per_100g', 'values' => array_map(fn ($value) => $value === null ? null : round($value * 100 / $grams, 3), $perServing), 'per_serving' => $perServing, 'portion_grams' => $grams, 'reference' => __('AI estimate based on the supplied ingredients and portion weight.'), 'estimated' => true, 'method' => 'ai_estimate', 'assumptions' => $data['assumptions']];
    }
}
