<?php

namespace App\Domain\Restaurants;

use App\Domain\Orders\CommerceSettings;
use App\Domain\Tenancy\TenantAccess;
use App\Models\RestaurantLocation;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class LocationPreferences
{
    public const DATES = ['d.m.Y', 'd/m/Y', 'Y-m-d', 'm/d/Y'];

    public const DATE_TIMES = ['d.m.Y H:i', 'd.m.Y H:i:s', 'd/m/Y H:i', 'd/m/Y H:i:s', 'Y-m-d H:i', 'Y-m-d H:i:s', 'm/d/Y h:i A', 'm/d/Y h:i:s A'];

    public const DEFAULTS = ['date_format' => 'd.m.Y', 'datetime_format' => 'd.m.Y H:i', 'show_phone' => true, 'show_email' => true, 'show_website' => true, 'show_address' => true, 'show_description' => true, 'show_product_images' => true];

    public function values(RestaurantLocation $location): array
    {
        return array_replace(self::DEFAULTS, array_intersect_key($location->preferences ?? [], self::DEFAULTS));
    }

    public function save(User $actor, RestaurantLocation $location, array $input): void
    {
        app(TenantAccess::class)->requireManage($actor, $location->organization_id);
        $rules = ['revision' => 'required|integer|min:0', 'date_format' => ['required', Rule::in(self::DATES)], 'datetime_format' => ['required', Rule::in(self::DATE_TIMES)]];
        foreach (array_keys(self::DEFAULTS) as $key) {
            if (str_starts_with($key, 'show_')) {
                $rules[$key] = 'required|boolean';
            }
        }
        $rules['block_unavailable_online_orders'] = 'sometimes|boolean';
        foreach (CommerceSettings::DEFAULTS as $key => $default) {
            $rules[$key] = is_bool($default) ? 'sometimes|boolean' : 'sometimes|integer|between:1,1440';
        }
        $rules['scheduling_days'] = 'sometimes|integer|between:1,90';
        $rules['communication_logs_enabled'] = 'sometimes|boolean';
        $data = Validator::make($input, $rules)->validate();
        if (array_filter(array_intersect_key($data, CommerceSettings::DEFAULTS), fn ($v, $k) => $v !== ($location->commerce_settings[$k] ?? null), ARRAY_FILTER_USE_BOTH)) {
            app(TenantAccess::class)->requireFeature($actor, $location, 'orders', true);
        }
        DB::transaction(function () use ($location, $data) {
            $locked = RestaurantLocation::whereKey($location->id)->lockForUpdate()->firstOrFail();
            abort_unless($locked->preferences_revision === $data['revision'], 409);
            $locked->update(['preferences' => [...($locked->preferences ?? []), ...array_intersect_key($data, CommerceSettings::DEFAULTS), ...array_intersect_key($data, self::DEFAULTS), 'block_unavailable_online_orders' => (bool) ($data['block_unavailable_online_orders'] ?? ($locked->preferences['block_unavailable_online_orders'] ?? true))], 'preferences_revision' => $locked->preferences_revision + 1]);
            if (array_key_exists('communication_logs_enabled', $data)) {
                $locked->restaurant->update(['communication_logs_enabled' => $data['communication_logs_enabled']]);
            }
        });
    }

    public function publicBrand(RestaurantLocation $location): array
    {
        $brand = $location->restaurant->only('name', 'slug', 'description', 'tagline', 'primary_color', 'logo_path', 'cover_path', 'contact_email', 'contact_phone', 'website');
        $settings = $this->values($location);
        foreach (['contact_phone' => 'show_phone', 'contact_email' => 'show_email', 'website' => 'show_website', 'description' => 'show_description', 'tagline' => 'show_description'] as $field => $flag) {
            if (! $settings[$flag]) {
                $brand[$field] = null;
            }
        }

        return $brand;
    }
}
