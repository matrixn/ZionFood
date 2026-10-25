<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StaffRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return ['email' => [$this->isMethod('POST') ? 'required' : 'sometimes', 'email:rfc', 'max:255'], 'role' => ['required', 'in:manager,staff'], 'location_ids' => ['present', 'array', 'max:100'], 'location_ids.*' => ['integer', 'distinct']];
    }
}
