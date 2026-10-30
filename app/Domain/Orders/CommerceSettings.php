<?php

namespace App\Domain\Orders;

use App\Models\RestaurantLocation;

class CommerceSettings
{
    public const DEFAULTS = ['scheduled_orders_enabled' => false, 'reservation_preorders_enabled' => false, 'scheduling_notice_minutes' => 30, 'scheduling_days' => 7, 'preparation_minutes' => 30, 'scheduled_orders_per_slot' => 10];

    public function values(RestaurantLocation $location): array
    {
        return array_replace(self::DEFAULTS, array_intersect_key($location->preferences ?? [], self::DEFAULTS));
    }
}
