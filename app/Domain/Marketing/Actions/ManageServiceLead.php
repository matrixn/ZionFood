<?php

namespace App\Domain\Marketing\Actions;

use App\Models\ServiceLead;
use App\Models\User;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class ManageServiceLead
{
    public function update(User $actor, ServiceLead $lead, mixed $status): void
    {
        abort_unless($actor->is_platform_admin, 403);
        $data = Validator::make(['status' => $status], ['status' => ['required', Rule::in(ServiceLead::STATUSES)]])->validate();
        $lead->update(['status' => $data['status'], 'updated_by' => $actor->id]);
    }

    public function delete(User $actor, ServiceLead $lead): void
    {
        abort_unless($actor->is_platform_admin, 403);
        $lead->delete();
    }
}
