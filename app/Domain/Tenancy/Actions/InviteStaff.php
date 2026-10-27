<?php

namespace App\Domain\Tenancy\Actions;

use App\Domain\Tenancy\StaffPermissions;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\StaffInvitation;
use App\Models\User;
use App\Notifications\StaffInvitationNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class InviteStaff
{
    public function execute(User $actor, int $organization, array $data): StaffInvitation
    {
        $staff = app(ManageStaff::class);
        $staff->authorize($actor, $organization, $data['role']);

        return DB::transaction(function () use ($actor, $organization, $data, $staff) {
            Organization::whereKey($organization)->lockForUpdate()->firstOrFail();
            $staff->authorize($actor, $organization, $data['role']);
            $email = strtolower($data['email']);
            if (OrganizationMembership::where('organization_id', $organization)->whereHas('user', fn ($q) => $q->where('email', $email))->exists()) {
                throw ValidationException::withMessages(['email' => __('This person is already a member.')]);
            }
            $previous = StaffInvitation::where('organization_id', $organization)->where('email', $email)->first();
            if ($previous) {
                $staff->authorize($actor, $organization, $previous->role);
            }
            $ids = $staff->locations($organization, $data['role'], $data['location_ids']);
            $permissions = StaffPermissions::grant($actor, $organization, $data['role'], $data['permissions'] ?? null);
            $token = Str::random(64);
            $invitation = StaffInvitation::updateOrCreate(['organization_id' => $organization, 'email' => $email], ['public_id' => (string) Str::ulid(), 'invited_by' => $actor->id, 'role' => $data['role'], 'location_ids' => $ids, 'permissions' => $permissions, 'token_hash' => hash('sha256', $token), 'expires_at' => now()->addDays(7), 'accepted_at' => null, 'accepted_by' => null, 'revoked_at' => null]);
            DB::afterCommit(fn () => Notification::route('mail', $email)->notify((new StaffInvitationNotification($invitation->public_id, $token, $organization))->locale(app()->getLocale())));

            return $invitation;
        }, 3);
    }
}
