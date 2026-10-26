<?php

namespace App\Http\Requests;

use App\Domain\Tenancy\TenantAccess;
use App\Models\RestaurantLocation;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RestaurantSetupRequest extends FormRequest
{
    public function authorize(): bool
    {
        $location = RestaurantLocation::where('public_id', $this->route('location'))->firstOrFail();

        return app(TenantAccess::class)->canManage($this->user(), $location->organization_id);
    }

    public function rules(): array
    {
        return ['revision' => ['required', 'integer', 'min:0']] + match ($this->route('step')) {
            'basics' => [
                'restaurant_name' => ['required', 'string', 'max:150'], 'name' => ['required', 'string', 'max:150'],
                'address' => ['required', 'string', 'max:255'], 'city' => ['required', 'string', 'max:100'], 'postal_code' => ['nullable', 'string', 'max:20'],
                'country' => ['required', 'string', 'regex:/^[A-Z]{2}$/'], 'phone' => ['required', 'string', 'max:35', 'regex:/^\+?[0-9][0-9 ().-]{5,33}[0-9]$/'], 'email' => ['nullable', 'email', 'max:254'],
                'website' => ['nullable', 'url:http,https', 'max:255'], 'cuisine' => ['nullable', 'string', 'max:150'],
                'timezone' => ['required', 'timezone'], 'latitude' => ['nullable', 'numeric', 'between:-90,90', 'required_with:longitude'], 'longitude' => ['nullable', 'numeric', 'between:-180,180', 'required_with:latitude'],
            ],
            'seating' => [
                'mode' => ['required', Rule::in(['capacity', 'tables'])], 'capacity' => ['required', 'integer', 'between:0,10000'],
                'tables' => ['present', 'array', 'max:300', 'min:'.($this->input('mode') === 'tables' ? 1 : 0)],
                'tables.*' => ['array:code,area,seats'],
                'tables.*.code' => ['required', 'string', 'max:30', 'distinct:ignore_case'],
                'tables.*.area' => ['required', 'string', 'max:80'], 'tables.*.seats' => ['required', 'integer', 'between:1,100'],
            ],
            'services' => ['pickup_enabled' => ['required', 'boolean'], 'delivery_enabled' => ['required', 'boolean'], 'dine_in_planned' => ['required', 'boolean'], 'scheduled_orders_planned' => ['required', 'boolean']],
            'reservations' => [
                'planned' => ['required', 'boolean'], 'confirmation' => ['required', Rule::in(['manual', 'automatic'])],
                'duration_minutes' => ['required', 'integer', 'between:15,480'], 'interval_minutes' => ['required', 'integer', Rule::in([15, 30, 60])],
                'minimum_notice_minutes' => ['required', 'integer', 'between:0,10080'], 'advance_days' => ['required', 'integer', 'between:1,365'],
                'min_party_size' => ['required', 'integer', 'between:1,100'], 'max_party_size' => ['required', 'integer', 'gte:min_party_size', 'max:100'],
                'cancellation_hours' => ['required', 'integer', 'between:0,720'], 'guest_notes' => ['nullable', 'string', 'max:1500'],
            ],
            'legal' => [
                'company_name' => ['required', 'string', 'max:200'], 'company_address' => ['required', 'string', 'max:255'],
                'tax_id' => ['nullable', 'string', 'max:40'], 'registration_number' => ['nullable', 'string', 'max:40'],
                'terms_url' => ['nullable', 'url:http,https', 'max:255'], 'privacy_url' => ['nullable', 'url:http,https', 'max:255'],
                'cancellation_policy' => ['nullable', 'string', 'max:3000'],
            ],
            'publish' => ['accepting_orders' => ['required', 'boolean']],
            default => ['unsupported_step' => ['required', 'prohibited']],
        };
    }
}
