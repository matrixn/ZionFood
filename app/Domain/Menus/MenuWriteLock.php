<?php

namespace App\Domain\Menus;

use App\Models\RestaurantLocation;

class MenuWriteLock
{
    public static function organization(int $organization): void
    {
        RestaurantLocation::where('organization_id', $organization)->orderBy('id')->lockForUpdate()->get();
    }
}
