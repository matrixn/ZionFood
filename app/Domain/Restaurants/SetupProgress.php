<?php

namespace App\Domain\Restaurants;

use App\Models\RestaurantLocation;

class SetupProgress
{
    public const STEPS = ['basics', 'cuisines', 'locations', 'seating', 'services', 'hours', 'delivery', 'menu', 'branding', 'reservations', 'staff', 'legal', 'publish', 'payments'];

    public function for(RestaurantLocation $location): array
    {
        $profile = $location->setup_profile ?? [];
        $basics = $profile['basics'] ?? [];
        $menuReady = $location->menus()->where('is_active', true)->whereHas('categories.items', fn ($query) => $query->where('is_available', true))->exists();
        $servicesReady = (bool) ($location->fulfillment?->pickup_enabled || $location->fulfillment?->dine_in_enabled || ($location->fulfillment?->delivery_enabled && $location->zones()->where('is_active', true)->exists()));
        $ready = [
            'basics' => filled($location->name) && filled($location->address) && filled($basics['city'] ?? null) && filled($basics['phone'] ?? null),
            'locations' => true,
            'delivery' => ! $location->fulfillment?->delivery_enabled || $location->zones()->where('is_active', true)->exists(),
            'seating' => isset($profile['seating']),
            'cuisines' => $location->cuisines()->where('is_active', true)->exists(),
            'services' => $servicesReady,
            'hours' => $location->hours()->exists(),
            'menu' => $menuReady,
            'branding' => filled($location->restaurant->name) && filled($location->restaurant->primary_color),
            'reservations' => isset($profile['reservations']),
            'staff' => true, // The owner is already authorized; inviting staff is optional.
            'legal' => filled($profile['legal']['company_name'] ?? null) && filled($profile['legal']['company_address'] ?? null),
            'publish' => (bool) $location->accepting_orders,
            'payments' => false,
        ];
        $blockers = array_values(array_filter(['basics', 'services', 'hours', 'menu'], fn ($key) => ! $ready[$key]));
        if ($location->fulfillment?->delivery_enabled && ! $location->zones()->where('is_active', true)->exists()) {
            $blockers[] = 'delivery_zones';
        }

        return ['steps' => $ready, 'blockers' => array_unique($blockers), 'ready_to_publish' => empty($blockers), 'completed' => count(array_filter(array_intersect_key($ready, array_flip(['basics', 'cuisines', 'seating', 'services', 'hours', 'menu', 'branding', 'reservations', 'staff', 'legal'])))), 'total' => 10];
    }
}
