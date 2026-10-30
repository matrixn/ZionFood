<?php

namespace App\Domain\Integrations;

use App\Domain\Tenancy\TenantAccess;
use App\Models\RestaurantLocation;
use App\Models\WebhookEndpoint;
use App\Models\WordpressConnection;
use Laravel\Sanctum\PersonalAccessToken;

class WordpressCallbackAccess
{
    public function allows(WebhookEndpoint $endpoint, string $event): bool
    {
        if (! $endpoint->wordpress_connection_id) {
            return true;
        }
        $c = WordpressConnection::whereKey($endpoint->wordpress_connection_id)->where('active', true)->first();
        $l = RestaurantLocation::find($endpoint->location_id);
        if (! $c || ! $l || ! $c->user || (int) $c->organization_id !== (int) $l->organization_id || (int) $c->restaurant_id !== (int) $l->restaurant_id || ! in_array($l->id, $c->location_ids, true)) {
            return false;
        }
        if (! app(TenantAccess::class)->canManage($c->user, $l->organization_id)) {
            return false;
        }
        [$ability, $feature] = match (explode('.', $event)[0]) {
            'order' => ['orders:read', 'orders'], 'reservation' => ['reservations:read', 'reservations'], 'menu', 'item' => ['menu:read', 'menu'], 'promotion' => ['promotions:manage', 'orders'], default => [null, null]
        };
        if (! $ability || ! in_array($ability, $c->abilities, true) || ! in_array('webhooks:manage', $c->abilities, true) || ! app(TenantAccess::class)->canFeature($c->user, $l, $feature)) {
            return false;
        }

        return PersonalAccessToken::where('wordpress_connection_id', $c->id)->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))->exists();
    }
}
