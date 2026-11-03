<?php

namespace App\Domain\Marketing\Actions;

use App\Domain\Auth\RegistrationPhone;
use App\Models\CampaignVisit;
use App\Models\ServiceLead;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

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
        if (is_string($input['phone_country'] ?? null)) {
            $input['phone_country'] = strtoupper(trim($input['phone_country']));
        }
        $phone = app(RegistrationPhone::class);
        $data = Validator::make($input, [
            'name' => ['required', 'string', 'min:2', 'max:120', 'not_regex:/[\x00-\x1F\x7F]/'],
            'email' => 'required|string|email:rfc|max:254',
            'phone_country' => ['required', 'string', Rule::in($phone->countries())],
            'phone' => ['bail', 'required', 'string', 'max:40', function ($attribute, $value, $fail) use ($phone, $input) {
                if ($phone->normalize($input['phone_country'] ?? null, $value) === null) {
                    $fail(__('Enter a valid phone number for the selected country.'));
                }
            }],
            'locale' => 'required|in:ro,en',
        ], [], ['name' => __('Contact name'), 'email' => __('Email address'), 'phone' => __('Phone number'), 'phone_country' => __('Phone country and calling code')])->validate();
        $data['phone'] = $phone->normalize($data['phone_country'], $data['phone']);
        unset($data['phone_country']);

        // Unique email identity also handles racing submissions. Anonymous retries never overwrite a lead.
        ServiceLead::firstOrCreate(['email_hash' => ServiceLead::emailHash($data['email'])], [...$data, 'status' => 'new', 'campaign_visit_id' => $campaignVisit?->id]);
    }
}
