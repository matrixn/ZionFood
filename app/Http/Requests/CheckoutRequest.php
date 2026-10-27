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
            'fulfillment' => ['required', 'in:pickup,delivery,dine_in'], 'table_session_token' => ['required_if:fulfillment,dine_in', 'nullable', 'string', 'size:64'], 'delivery_address' => ['required_if:fulfillment,delivery', 'nullable', 'string', 'max:500'],
            'postal_code' => ['nullable', 'string', 'max:20'], 'delivery_zone_id' => ['nullable', 'integer'],
            'delivery_latitude' => ['nullable', 'numeric', 'between:-85,85', 'required_with:delivery_longitude'], 'delivery_longitude' => ['nullable', 'numeric', 'between:-180,180', 'required_with:delivery_latitude'],
            'notes' => ['nullable', 'string', 'max:1000'], 'items' => ['required', 'array', 'min:1', 'max:50'],
            'items.*' => ['required', 'array'],
            'items.*.product_id' => ['required', 'string', 'ulid'], 'items.*.quantity' => ['required', 'integer', 'min:1', 'max:50'],
            'items.*.modifiers' => ['sometimes', 'array', 'max:100'], 'items.*.modifiers.*' => ['array:id,quantity'], 'items.*.modifiers.*.id' => ['required', 'integer', 'distinct'], 'items.*.modifiers.*.quantity' => ['required', 'integer', 'min:1', 'max:50'],
            'items.*.variant_id' => ['nullable', 'integer'], 'items.*.modifier_ids' => ['sometimes', 'array', 'max:30'], 'items.*.modifier_ids.*' => ['integer'],
        ];
    }

    public function rules(): array
    {
        return self::constraints();
    }
}
