<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\ValidationException;

class MenuItemRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        if ($this->has('payload')) {
            try {
                $payload = json_decode($this->input('payload'), true, 32, JSON_THROW_ON_ERROR);
            } catch (\JsonException) {
                throw ValidationException::withMessages(['payload' => __('Invalid product payload.')]);
            }
            if (! is_array($payload)) {
                throw ValidationException::withMessages(['payload' => __('Invalid product payload.')]);
            }
            $this->merge(array_intersect_key($payload, array_flip(['category_id', 'name', 'description', 'price', 'tax_basis_points', 'is_available', 'variants', 'modifier_group_ids', 'ingredient_ids', 'portion_size', 'contains_allergens', 'from_frozen'])));
        }
    }

    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'category_id' => ['required', 'integer'], 'name' => ['required', 'string', 'max:150'], 'description' => ['nullable', 'string', 'max:2000'],
            'portion_size' => ['nullable', 'string', 'max:50'], 'contains_allergens' => ['sometimes', 'boolean'], 'from_frozen' => ['sometimes', 'boolean'],
            'ingredient_ids' => ['sometimes', 'array', 'max:100'], 'ingredient_ids.*' => ['required', 'integer', 'distinct'],
            'price' => ['required', 'integer', 'min:0', 'max:10000000'], 'tax_basis_points' => ['sometimes', 'integer', 'min:0', 'max:10000'],
            'is_available' => ['required', 'boolean'], 'variants' => ['sometimes', 'array', 'max:20'],
            'variants.*.name' => ['required', 'string', 'max:100'], 'variants.*.price' => ['required', 'integer', 'min:0', 'max:10000000'],
            'modifier_group_ids' => ['sometimes', 'array', 'max:20'], 'modifier_group_ids.*' => ['integer', 'distinct'],
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096', 'dimensions:max_width=6000,max_height=6000'],
        ];
    }
}
