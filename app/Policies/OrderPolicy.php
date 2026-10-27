<?php

namespace App\Policies;

use App\Domain\Tenancy\TenantAccess;
use App\Models\Order;
use App\Models\User;

class OrderPolicy
{
    public function view(User $user, Order $order): bool
    {
        return app(TenantAccess::class)->canFeature($user, $order->location, 'orders') || app(TenantAccess::class)->canFeature($user, $order->location, 'kitchen');
    }

    public function update(User $user, Order $order): bool
    {
        return app(TenantAccess::class)->canFeature($user, $order->location, 'orders', true) || app(TenantAccess::class)->canFeature($user, $order->location, 'kitchen', true);
    }
}
