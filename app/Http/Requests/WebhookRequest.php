<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class WebhookRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return ['name' => ['required', 'string', 'max:100'], 'url' => ['required', 'url:https', 'max:2048'], 'enabled' => ['required', 'boolean'], 'events' => ['required', 'array', 'min:1', 'max:4'], 'events.*' => ['required', 'distinct', Rule::in(['order.created', 'order.updated', 'menu.updated', 'item.updated'])], 'secret' => [$this->isMethod('POST') ? 'required' : 'nullable', 'string', 'min:32', 'max:128', 'regex:/^[A-Za-z0-9_-]+$/']];
    }
}
