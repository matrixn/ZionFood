<?php

namespace App\Domain\Tenancy\Actions;

use App\Domain\Tenancy\TenantAccess;
use App\Models\LocationStaff;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\RestaurantLocation;
use App\Models\StaffInvitation;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ManageStaff
{
    public function authorize(User $actor, int $organization, string $role = 'staff'): void
    {
        $access = app(TenantAccess::class);
        $access->requireManage($actor, $organization);
        abort_unless(in_array($role, ['staff', 'manager'], true), 403);
        abort_if($role === 'manager' && $access->role($actor, $organization) !== 'owner', 403);
    }

    public function locations(int $organization, string $role, array $locations): array
    {
        $ids = array_values(array_unique(array_map('intval', $locations)));
        if (($role === 'staff' && ! $ids) || RestaurantLocation::where('organization_id', $organization)->whereIn('id', $ids)->count() !== count($ids)) {
            throw ValidationException::withMessages(['location_ids' => __('Select locations belonging to this organization.')]);
        }

        return $role === 'staff' ? $ids : [];
    }

    public function update(User $actor, int $organization, int $membershipId, string $role, array $locations): void
    {
        $this->authorize($actor, $organization, $role);
        DB::transaction(function () use ($actor, $organization, $membershipId, $role, $locations) {
            Organization::whereKey($organization)->lockForUpdate()->firstOrFail();
            $membership = OrganizationMembership::where('organization_id', $organization)->findOrFail($membershipId);
            abort_if($membership->role === 'owner', 403, __('Owner access cannot be changed here.'));
            $this->authorize($actor, $organization, $membership->role);
            $ids = $this->locations($organization, $role, $locations);
            LocationStaff::where('organization_id', $organization)->where('user_id', $membership->user_id)->delete();
            $membership->update(['role' => $role]);
            foreach ($ids as $id) {
                LocationStaff::create(['organization_id' => $organization, 'location_id' => $id, 'user_id' => $membership->user_id]);
            }
        }, 3);
    }

    public function remove(User $actor, int $organization, int $membershipId): void
    {
        DB::transaction(function () use ($actor, $organization, $membershipId) {
            Organization::whereKey($organization)->lockForUpdate()->firstOrFail();
            $membership = OrganizationMembership::where('organization_id', $organization)->findOrFail($membershipId);
            abort_if($membership->role === 'owner', 403, __('Owner access cannot be changed here.'));
            $this->authorize($actor, $organization, $membership->role);
            LocationStaff::where('organization_id', $organization)->where('user_id', $membership->user_id)->delete();
            User::findOrFail($membership->user_id)->tokens()->where('organization_id', $organization)->delete();
            $membership->delete();
            StaffInvitation::where('organization_id', $organization)->where('email', $membership->user->email)->whereNull('accepted_at')->update(['revoked_at' => now()]);
        }, 3);
    }
}
