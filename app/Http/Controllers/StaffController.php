<?php

namespace App\Http\Controllers;

use App\Domain\Tenancy\Actions\AcceptStaffInvitation;
use App\Domain\Tenancy\Actions\InviteStaff;
use App\Domain\Tenancy\Actions\ManageStaff;
use App\Http\Requests\StaffRequest;
use App\Models\Organization;
use App\Models\RestaurantLocation;
use App\Models\StaffInvitation;
use Illuminate\Http\Request;
use Inertia\Inertia;

class StaffController extends Controller
{
    public function invite(StaffRequest $request, string $location, InviteStaff $action)
    {
        $context = RestaurantLocation::where('public_id', $location)->firstOrFail();
        $action->execute($request->user(), $context->organization_id, $request->validated());

        return back()->with('success', __('Invitation sent.'));
    }

    public function update(StaffRequest $request, string $location, int $membership, ManageStaff $action)
    {
        $context = RestaurantLocation::where('public_id', $location)->firstOrFail();
        $action->update($request->user(), $context->organization_id, $membership, $request->validated('role'), $request->validated('location_ids'));

        return back()->with('success', __('Staff access updated.'));
    }

    public function destroy(Request $request, string $location, int $membership, ManageStaff $action)
    {
        $context = RestaurantLocation::where('public_id', $location)->firstOrFail();
        $action->remove($request->user(), $context->organization_id, $membership);

        return back()->with('success', __('Staff access removed.'));
    }

    public function revoke(Request $request, string $location, string $invitation)
    {
        $context = RestaurantLocation::where('public_id', $location)->firstOrFail();
        $record = StaffInvitation::where('organization_id', $context->organization_id)->where('public_id', $invitation)->firstOrFail();
        app(ManageStaff::class)->authorize($request->user(), $context->organization_id, $record->role);
        $record->update(['revoked_at' => now()]);

        return back()->with('success', __('Invitation revoked.'));
    }

    public function invitation(Request $request, string $invitation)
    {
        if (! $request->user()?->hasVerifiedEmail()) {
            $request->session()->put('url.intended', '/invitations/'.$invitation);

            return Inertia::render('invitations/Accept', ['invitationId' => $invitation, 'invitation' => null]);
        }
        $record = StaffInvitation::where('public_id', $invitation)->firstOrFail();
        abort_unless(strtolower($record->email) === strtolower($request->user()->email), 403);

        return Inertia::render('invitations/Accept', ['invitationId' => $invitation, 'invitation' => [...$record->only('role', 'expires_at', 'accepted_at', 'revoked_at'), 'organization' => Organization::findOrFail($record->organization_id)->name]]);
    }

    public function accept(Request $request, string $invitation, AcceptStaffInvitation $action)
    {
        $data = $request->validate(['token' => ['required', 'string', 'size:64']]);
        $action->execute($request->user(), $invitation, $data['token']);

        return redirect('/app')->with('success', __('Invitation accepted.'));
    }
}
