<?php

namespace App\Domain\Tenancy;

use App\Models\LocationStaff;
use App\Models\OrganizationMembership;
use App\Models\RestaurantLocation;
use App\Models\User;
use App\Models\WordpressConnection;
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
        $token = $user->currentAccessToken();
        if ($token && ! $token instanceof TransientToken && $token->wordpress_connection_id) {
            $connection = WordpressConnection::whereKey($token->wordpress_connection_id)->where('active', true)->first();
            if (! $connection || ! $this->canManage($user, $location->organization_id) || (int) $connection->restaurant_id !== (int) $location->restaurant_id || ! in_array($location->id, $connection->location_ids, true)) {
                return false;
            }
        }

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

    public function permissions(User $user, int $organization): array
    {
        if (! $this->role($user, $organization)) {
            return array_fill_keys(StaffPermissions::FEATURES, 'none');
        }
        $membership = OrganizationMembership::where('organization_id', $organization)->where('user_id', $user->id)->first();

        return $membership ? StaffPermissions::effective($membership) : array_fill_keys(StaffPermissions::FEATURES, 'none');
    }

    public function canFeatureInOrganization(User $user, int $organization, string $feature, bool $write = false): bool
    {
        $level = $this->permissions($user, $organization)[$feature] ?? 'none';

        return $write ? $level === 'manage' : in_array($level, ['view', 'manage'], true);
    }

    public function canFeature(User $user, RestaurantLocation $location, string $feature, bool $write = false): bool
    {
        return $this->canOperate($user, $location) && $this->canFeatureInOrganization($user, $location->organization_id, $feature, $write);
    }

    public function requireFeature(User $user, RestaurantLocation $location, string $feature, bool $write = false): void
    {
        if (! $this->canFeature($user, $location, $feature, $write)) {
            throw new AuthorizationException;
        }
    }

    public function canMenu(User $user, int $organization, int $menu, bool $write = true): bool
    {
        $locations = RestaurantLocation::where('organization_id', $organization)->whereHas('menus', fn ($query) => $query->where('menus.id', $menu))->get();
        if ($locations->isEmpty()) {
            if (($token = $user->currentAccessToken()) && ! $token instanceof TransientToken && $token->wordpress_connection_id) {
                return false;
            }

            return $this->canManage($user, $organization) && $this->canFeatureInOrganization($user, $organization, 'menu', $write);
        }

        return $locations->contains(fn ($location) => $this->canFeature($user, $location, 'menu', $write)) && (! $write || ! $locations->contains(fn ($location) => ! $this->canFeature($user, $location, 'menu', true)));
    }

    public function requireMenu(User $user, int $organization, int $menu, bool $write = true): void
    {
        abort_unless($this->canMenu($user, $organization, $menu, $write), 403);
    }
}
