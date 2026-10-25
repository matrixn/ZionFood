<?php

namespace App\Policies;

use App\Domain\Tenancy\TenantAccess;
use App\Models\Order;
use App\Models\User;

class OrderPolicy
{
    public function view(User $user, Order $order): bool
    {
        return app(TenantAccess::class)->canOperate($user, $order->location);
    }

    public function update(User $user, Order $order): bool
    {
        return $this->view($user, $order);
    }
}
