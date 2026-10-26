<?php

namespace App\Domain\Tenancy;

use App\Models\StaffInvitation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;

class PendingStaffInvitation
{
    public function prepare(Request $request, string $id, string $token): StaffInvitation
    {
        $record = StaffInvitation::where('public_id', $id)->firstOrFail();
        abort_unless(hash_equals($record->token_hash, hash('sha256', $token)), 403);
        abort_if($record->revoked_at || $record->expires_at->isPast() || $record->accepted_at, 410, __('This invitation has expired or was revoked.'));
        if ($request->user()) {
            abort_unless(strtolower($request->user()->email) === strtolower($record->email), 403);
        }
        $request->session()->put('staff_invitation', ['id' => $id, 'proof' => Crypt::encryptString($token)]);
        $request->session()->put('url.intended', '/invitations/'.$id);

        return $record;
    }

    public function current(Request $request): ?array
    {
        if (! $request->hasSession()) {
            return null;
        }
        $pending = $request->session()->get('staff_invitation');
        if (! is_array($pending)) {
            return null;
        }
        $record = StaffInvitation::where('public_id', $pending['id'] ?? '')->firstOrFail();
        try {
            $token = Crypt::decryptString($pending['proof']);
        } catch (\Throwable) {
            abort(403);
        }
        abort_unless(hash_equals($record->token_hash, hash('sha256', $token)), 403);
        abort_if($record->revoked_at || $record->accepted_at || $record->expires_at->isPast(), 410, __('This invitation has expired or was revoked.'));

        return ['record' => $record, 'token' => $token];
    }

    public function clear(Request $request): void
    {
        $request->session()->forget('staff_invitation');
        $request->session()->put('url.intended', '/app');
    }
}
