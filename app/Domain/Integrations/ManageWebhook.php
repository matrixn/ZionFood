<?php

namespace App\Domain\Integrations;

use App\Domain\Tenancy\TenantAccess;
use App\Models\RestaurantLocation;
use App\Models\User;
use App\Models\WebhookEndpoint;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Validation\ValidationException;

class ManageWebhook
{
    public function execute(User $actor, RestaurantLocation $location, array $data, ?WebhookEndpoint $endpoint = null): WebhookEndpoint
    {
        app(TenantAccess::class)->requireManage($actor, $location->organization_id);
        abort_if($endpoint && ($endpoint->organization_id !== $location->organization_id || $endpoint->location_id !== $location->id), 403);
        try {
            app(PublicWebhookDestination::class)->resolve($data['url']);
        } catch (UnsafeWebhookDestination) {
            throw ValidationException::withMessages(['url' => __('Use a public HTTPS address without credentials, query parameters or redirects.')]);
        }
        $fields = array_intersect_key($data, array_flip(['name', 'url', 'enabled', 'events']));
        if (! empty($data['secret'])) {
            $fields['secret_encrypted'] = Crypt::encryptString($data['secret']);
        }
        if ($endpoint) {
            $endpoint->update($fields);
        } else {
            $endpoint = WebhookEndpoint::create(['organization_id' => $location->organization_id, 'location_id' => $location->id, ...$fields]);
        }

        return $endpoint;
    }
}
