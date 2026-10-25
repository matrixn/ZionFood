<?php

namespace App\Policies;

use App\Domain\Tenancy\TenantAccess;
use App\Models\RestaurantLocation;
use App\Models\User;

class RestaurantLocationPolicy
{
    public function view(User $user, RestaurantLocation $location): bool
    {
        return app(TenantAccess::class)->canOperate($user, $location);
    }

    public function update(User $user, RestaurantLocation $location): bool
    {
        return app(TenantAccess::class)->canManage($user, $location->organization_id);
    }
}
