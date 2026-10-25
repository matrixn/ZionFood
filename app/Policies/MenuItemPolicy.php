<?php

namespace App\Policies;

use App\Domain\Tenancy\TenantAccess;
use App\Models\MenuItem;
use App\Models\User;

class MenuItemPolicy
{
    public function update(User $user, MenuItem $item): bool
    {
        return app(TenantAccess::class)->canManage($user, $item->organization_id);
    }
}
