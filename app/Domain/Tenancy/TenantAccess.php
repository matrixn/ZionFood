<?php

namespace App\Domain\Tenancy;

use App\Models\LocationStaff;
use App\Models\OrganizationMembership;
use App\Models\RestaurantLocation;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Laravel\Sanctum\TransientToken;

class TenantAccess
{
    public function role(User $user, int $organizationId): ?string
    {
        if (($token = $user->currentAccessToken()) && ! ($token instanceof TransientToken) && $token->organization_id && (int) $token->organization_id !== $organizationId) {
            return null;
        }

        return OrganizationMembership::where('organization_id', $organizationId)->where('user_id', $user->id)->value('role');
    }

    public function canManage(User $user, int $organizationId): bool
    {
        return in_array($this->role($user, $organizationId), ['owner', 'manager'], true);
    }

    public function canOperate(User $user, RestaurantLocation $location): bool
    {
        return $this->canManage($user, $location->organization_id) ||
            ($this->role($user, $location->organization_id) === 'staff' && LocationStaff::where('organization_id', $location->organization_id)->where('location_id', $location->id)->where('user_id', $user->id)->exists());
    }

    public function requireManage(User $user, int $organizationId): void
    {
        if (! $this->canManage($user, $organizationId)) {
            throw new AuthorizationException;
        }
    }

    public function requireOperate(User $user, RestaurantLocation $location): void
    {
        if (! $this->canOperate($user, $location)) {
            throw new AuthorizationException;
        }
    }
}
