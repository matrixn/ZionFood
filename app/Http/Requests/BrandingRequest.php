<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BrandingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150'], 'slug' => ['required', 'string', 'min:3', 'max:50', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', Rule::notIn(config('zionfood.reserved_slugs'))],
            'description' => ['nullable', 'string', 'max:1000'], 'tagline' => ['nullable', 'string', 'max:180'], 'primary_color' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'contact_email' => ['nullable', 'email:rfc', 'max:255'], 'contact_phone' => ['nullable', 'string', 'max:30'], 'website' => ['nullable', 'url:https', 'max:255'],
            'logo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096', 'dimensions:max_width=3000,max_height=3000'],
            'cover' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096', 'dimensions:max_width=6000,max_height=6000'],
            'remove_logo' => ['sometimes', 'boolean'], 'remove_cover' => ['sometimes', 'boolean'],
        ];
    }
}
