<?php

namespace App\Domain\Tenancy\Actions;

use App\Models\LocationStaff;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\StaffInvitation;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class AcceptStaffInvitation
{
    public function execute(User $user, string $publicId, string $token): StaffInvitation
    {
        return DB::transaction(function () use ($user, $publicId, $token) {
            $context = StaffInvitation::where('public_id', $publicId)->firstOrFail();
            Organization::whereKey($context->organization_id)->lockForUpdate()->firstOrFail();
            $invitation = StaffInvitation::whereKey($context->id)->lockForUpdate()->firstOrFail();
            abort_unless(strtolower($user->email) === strtolower($invitation->email) && hash_equals($invitation->token_hash, hash('sha256', $token)), 403);
            if ($invitation->accepted_at && $invitation->accepted_by === $user->id) {
                return $invitation;
            }
            abort_if($invitation->revoked_at || $invitation->expires_at->isPast(), 410, __('This invitation has expired or was revoked.'));
            app(ManageStaff::class)->authorize(User::findOrFail($invitation->invited_by), $invitation->organization_id, $invitation->role);
            $ids = app(ManageStaff::class)->locations($invitation->organization_id, $invitation->role, $invitation->location_ids);
            abort_if(OrganizationMembership::where('organization_id', $invitation->organization_id)->where('user_id', $user->id)->exists(), 409);
            // Possession of the emailed, email-bound invitation proves this address.
            if (! $user->hasVerifiedEmail()) {
                $user->forceFill(['email_verified_at' => now()])->save();
            }
            OrganizationMembership::create(['organization_id' => $invitation->organization_id, 'user_id' => $user->id, 'role' => $invitation->role]);
            foreach ($ids as $id) {
                LocationStaff::create(['organization_id' => $invitation->organization_id, 'location_id' => $id, 'user_id' => $user->id]);
            }
            $invitation->update(['accepted_at' => now(), 'accepted_by' => $user->id]);

            return $invitation;
        }, 3);
    }
}
