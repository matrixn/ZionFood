<?php

namespace App\Domain\Marketing\Actions;

use App\Models\CampaignVisit;
use App\Models\ServiceLead;
use Illuminate\Support\Facades\Validator;

class CaptureServiceLead
{
    public function execute(array $input, ?CampaignVisit $campaignVisit = null): void
    {
        // A filled honeypot receives the same public confirmation without saving data.
        if (filled($input['website'] ?? null)) {
            return;
        }
        foreach (['name', 'email', 'phone'] as $key) {
            if (is_string($input[$key] ?? null)) {
                $input[$key] = trim($input[$key]);
            }
        }
        if (is_string($input['email'] ?? null)) {
            $input['email'] = mb_strtolower($input['email']);
        }
        $data = Validator::make($input, [
            'name' => ['required', 'string', 'min:2', 'max:120', 'not_regex:/[\x00-\x1F\x7F]/'],
            'email' => 'required|string|email:rfc|max:254',
            'phone' => ['required', 'string', 'max:30', 'regex:/^\+?[0-9][0-9 ().-]{5,28}[0-9]$/'],
            'locale' => 'required|in:ro,en',
        ], [], ['name' => __('Contact name'), 'email' => __('Email address'), 'phone' => __('Phone number')])->validate();

        // Unique email identity also handles racing submissions. Anonymous retries never overwrite a lead.
        ServiceLead::firstOrCreate(['email_hash' => ServiceLead::emailHash($data['email'])], [...$data, 'status' => 'new', 'campaign_visit_id' => $campaignVisit?->id]);
    }
}
