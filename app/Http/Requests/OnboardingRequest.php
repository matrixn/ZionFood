<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class OnboardingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'organization_name' => ['required', 'string', 'max:150'], 'restaurant_name' => ['required', 'string', 'max:150'],
            'slug' => ['required', 'string', 'min:3', 'max:50', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', 'unique:restaurants,slug', Rule::notIn(config('zionfood.reserved_slugs'))],
            'location_name' => ['required', 'string', 'max:150'], 'address' => ['required', 'string', 'max:255'],
        ];
    }
}
