<?php

namespace App\Domain\Menus;

use App\Models\RestaurantLocation;
use Illuminate\Support\Facades\Cache;

class MenuCache
{
    public static function forgetOrganization(int $organization): void
    {
        foreach (RestaurantLocation::where('organization_id', $organization)->pluck('public_id') as $id) {
            Cache::forget('public-menu:'.$id);
        }
    }
}
