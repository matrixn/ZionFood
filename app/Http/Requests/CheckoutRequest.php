<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CheckoutRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public static function constraints(): array
    {
        return [
            'customer_name' => ['required', 'string', 'max:150'], 'customer_phone' => ['required', 'string', 'max:30'],
            'fulfillment' => ['required', 'in:pickup,delivery'], 'delivery_address' => ['required_if:fulfillment,delivery', 'nullable', 'string', 'max:500'],
            'postal_code' => ['required_if:fulfillment,delivery', 'nullable', 'string', 'max:20'], 'delivery_zone_id' => ['required_if:fulfillment,delivery', 'nullable', 'integer'],
            'notes' => ['nullable', 'string', 'max:1000'], 'items' => ['required', 'array', 'min:1', 'max:50'],
            'items.*' => ['required', 'array'],
            'items.*.product_id' => ['required', 'string', 'ulid'], 'items.*.quantity' => ['required', 'integer', 'min:1', 'max:50'],
            'items.*.variant_id' => ['nullable', 'integer'], 'items.*.modifier_ids' => ['sometimes', 'array', 'max:30'], 'items.*.modifier_ids.*' => ['integer'],
        ];
    }

    public function rules(): array
    {
        return self::constraints();
    }
}
