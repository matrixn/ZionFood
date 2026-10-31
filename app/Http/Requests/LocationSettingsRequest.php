<?php

namespace App\Http\Requests;

use App\Domain\Delivery\Polygon;
use App\Domain\Tenancy\TenantAccess;
use App\Models\RestaurantLocation;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class LocationSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        $location = RestaurantLocation::where('public_id', $this->route('location'))->firstOrFail();

        return app(TenantAccess::class)->canManage($this->user(), $location->organization_id);
    }

    public function rules(): array
    {
        return ['dine_in_cutoff_minutes' => ['sometimes', 'integer', 'between:0,240'], 'name' => ['required', 'string', 'max:150'], 'address' => ['required', 'string', 'max:255'], 'accepting_orders' => ['required', 'boolean'], 'pickup_enabled' => ['required', 'boolean'], 'delivery_enabled' => ['required', 'boolean'],
            'hours' => ['present', 'array', 'max:7'], 'hours.*.weekday' => ['required', 'integer', 'between:1,7', 'distinct'], 'hours.*.opens_at' => ['required', 'date_format:H:i'], 'hours.*.closes_at' => ['required', 'date_format:H:i', 'after:hours.*.opens_at'],
            'zones' => ['present', 'array', 'max:30'], 'zones.*' => ['array:name,postal_prefix,fee,minimum_order,geometry,priority,id'], 'zones.*.id' => ['sometimes', 'nullable', 'integer', 'min:1', 'distinct'], 'zones.*.name' => ['required', 'string', 'max:100'], 'zones.*.postal_prefix' => ['nullable', 'string', 'max:20'], 'zones.*.fee' => ['required', 'integer', 'between:0,1000000'], 'zones.*.minimum_order' => ['required', 'integer', 'between:0,100000000'], 'zones.*.priority' => ['sometimes', 'integer', 'between:0,1000'],
            'zones.*.geometry' => ['nullable', function ($attribute, $value, $fail) {
                if (! Polygon::valid($value)) {
                    $fail(__('Draw a valid non-intersecting delivery polygon with at most 200 points.'));
                }
            }],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator) {
            $zones = $this->input('zones', []);
            if (! is_array($zones)) {
                return;
            } foreach ($zones as $i => $zone) {
                if (is_array($zone) && empty($zone['geometry']) && blank($zone['postal_prefix'] ?? null)) {
                    $validator->errors()->add('zones.'.$i.'.geometry', __('Draw a delivery polygon or enter a postal prefix.'));
                }
            }
        }];
    }
}
