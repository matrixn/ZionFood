<?php

use App\Domain\Tenancy\TenantAccess;
use App\Models\RestaurantLocation;
use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('organization.{organization}.location.{location}.orders', function (User $user, int $organization, int $location) {
    $context = RestaurantLocation::whereKey($location)->where('organization_id', $organization)->first();

    return $context && app(TenantAccess::class)->canOperate($user, $context);
});
