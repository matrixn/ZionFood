<?php

namespace App\Http\Requests;

use App\Domain\Tenancy\StaffPermissions;
use Illuminate\Foundation\Http\FormRequest;

class StaffRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        $rules = ['permissions' => ['sometimes', 'array:'.implode(',', StaffPermissions::FEATURES)]];
        foreach (StaffPermissions::FEATURES as $feature) {
            $rules['permissions.'.$feature] = ['required_with:permissions', 'in:none,view,manage'];
        }

        return $rules + ['email' => [$this->isMethod('POST') ? 'required' : 'sometimes', 'email:rfc', 'max:255'], 'role' => ['required', 'in:manager,staff'], 'location_ids' => ['present', 'array', 'max:100'], 'location_ids.*' => ['integer', 'distinct']];
    }
}
