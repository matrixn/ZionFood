<?php

namespace App\Domain\Integrations;

use App\Domain\Tenancy\TenantAccess;
use App\Models\RestaurantLocation;
use App\Models\WordpressConnection;
use Illuminate\Http\Request;
use Laravel\Sanctum\TransientToken;

class WordpressAccess
{
    public const ABILITIES = ['storefront:read', 'checkout:write', 'reservations:create', 'orders:read', 'orders:manage', 'reservations:read', 'reservations:manage', 'tables:read', 'tables:manage', 'menu:read', 'menu:write', 'promotions:manage', 'analytics:read', 'events:read', 'webhooks:manage'];

    public function authenticate(Request $request): WordpressConnection
    {
        $token = $request->user()?->currentAccessToken();
        abort_unless($request->bearerToken() && $token && ! $token instanceof TransientToken && $token->wordpress_connection_id, 401);
        $connection = WordpressConnection::whereKey($token->wordpress_connection_id)->where('active', true)->where('organization_id', $token->organization_id)->where('user_id', $request->user()->id)->firstOrFail();
        $access = app(TenantAccess::class);
        $access->requireManage($request->user(), $connection->organization_id);
        abort_unless(RestaurantLocation::where('organization_id', $connection->organization_id)->where('restaurant_id', $connection->restaurant_id)->whereIn('id', $connection->location_ids)->count() === count($connection->location_ids), 403);
        $permissions = $access->permissions($request->user(), $connection->organization_id);
        foreach ($token->abilities as $ability) {
            $feature = match (explode(':', $ability)[0]) {
                'orders' => 'orders', 'reservations' => $ability === 'reservations:create' ? null : 'reservations', 'tables' => 'tables', 'menu' => 'menu', 'promotions' => 'orders', default => null
            };
            if ($feature) {
                $write = in_array(explode(':', $ability)[1], ['write', 'manage'], true);
                abort_unless($write ? ($permissions[$feature] ?? 'none') === 'manage' : in_array($permissions[$feature] ?? 'none', ['view', 'manage'], true), 403);
            }
        }
        $request->attributes->set('wordpress_connection', $connection);

        return $connection;
    }

    public function connection(Request $request): WordpressConnection
    {
        return $request->attributes->get('wordpress_connection');
    }

    public function ability(Request $request, string $ability): void
    {
        abort_unless($request->user()->tokenCan($ability), 403);
    }

    public function location(Request $request, string $id, ?string $ability = null): RestaurantLocation
    {
        if ($ability) {
            $this->ability($request, $ability);
        }
        $c = $this->connection($request);
        $location = RestaurantLocation::where('public_id', $id)->where('organization_id', $c->organization_id)->where('restaurant_id', $c->restaurant_id)->whereIn('id', $c->location_ids)->firstOrFail();
        $request->attributes->set('wordpress_location', $location);

        return $location;
    }
}
