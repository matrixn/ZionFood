<?php

namespace App\Domain\Restaurants;

use App\Domain\Tenancy\TenantAccess;
use App\Models\RestaurantLocation;
use App\Models\User;

class AuthorizeOrderingChanges
{
    public function execute(User $actor, RestaurantLocation $location, array $data): void
    {
        foreach (['accepting_orders', 'pickup_enabled', 'delivery_enabled'] as $field) {
            $current = $field === 'accepting_orders' ? $location->accepting_orders : $location->fulfillment?->{$field};
            if (array_key_exists($field, $data) && (bool) $data[$field] !== (bool) $current) {
                app(TenantAccess::class)->requireFeature($actor, $location, 'orders', true);
            }
        }
    }
}
