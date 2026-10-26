<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CuisineRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->is_platform_admin;
    }

    public function rules(): array
    {
        return [
            'slug' => ['required', 'string', 'max:100', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', Rule::unique('cuisines')->ignore($this->route('cuisine'))],
            'name_ro' => ['required', 'string', 'max:100'], 'name_en' => ['required', 'string', 'max:100'],
            'description_ro' => ['required', 'string', 'max:1000'], 'description_en' => ['required', 'string', 'max:1000'],
            'is_active' => ['required', 'boolean'], 'sort_order' => ['required', 'integer', 'between:0,65535'],
            'image' => [$this->route('cuisine') ? 'nullable' : 'required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096', 'dimensions:max_width=6000,max_height=6000'],
            'image_credit' => ['nullable', 'string', 'max:500'], 'image_source_url' => ['nullable', 'url:http,https', 'max:1000'], 'image_license' => ['nullable', 'string', 'max:150'], 'image_license_url' => ['nullable', 'url:http,https', 'max:1000'],
        ];
    }
}
