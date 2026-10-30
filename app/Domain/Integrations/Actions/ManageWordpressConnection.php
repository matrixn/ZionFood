<?php

namespace App\Domain\Integrations\Actions;

use App\Domain\Integrations\WordpressAccess;
use App\Domain\Tenancy\TenantAccess;
use App\Models\RestaurantLocation;
use App\Models\User;
use App\Models\WebhookEndpoint;
use App\Models\WordpressConnection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\PersonalAccessToken;

class ManageWordpressConnection
{
    public function create(User $actor, RestaurantLocation $location, array $input): array
    {
        $access = app(TenantAccess::class);
        $access->requireManage($actor, $location->organization_id);
        $d = Validator::make($input, ['name' => 'required|string|max:100', 'site_url' => 'required|url:https|max:255', 'location_ids' => 'required|array|min:1|max:100', 'location_ids.*' => 'required|integer|distinct', 'abilities' => 'required|array|min:1|max:15', 'abilities.*' => ['required', 'distinct', Rule::in(WordpressAccess::ABILITIES)], 'expires_in_days' => 'required|integer|in:0,30,90,180,365'])->validate();
        $d['location_ids'] = array_map('intval', $d['location_ids']);
        if (array_intersect(['user', 'pass', 'query', 'fragment'], array_keys(parse_url($d['site_url'])))) {
            throw ValidationException::withMessages(['site_url' => __('Use a website address without credentials, query parameters or fragments.')]);
        }
        abort_unless(RestaurantLocation::where('restaurant_id', $location->restaurant_id)->where('organization_id', $location->organization_id)->whereIn('id', $d['location_ids'])->count() === count($d['location_ids']), 422);
        foreach ($d['abilities'] as $ability) {
            $feature = match (explode(':', $ability)[0]) {
                'orders' => 'orders','reservations' => str_ends_with($ability, ':create') ? null : 'reservations','tables' => 'tables','menu' => 'menu','promotions' => 'orders',default => null
            };
            if ($feature) {
                foreach ($d['location_ids'] as $id) {
                    $access->requireFeature($actor, RestaurantLocation::findOrFail($id), $feature, in_array(explode(':', $ability)[1], ['write', 'manage'], true));
                }
            }
        }

        return DB::transaction(function () use ($actor, $location, $d) {
            $connection = WordpressConnection::create(['organization_id' => $location->organization_id, 'restaurant_id' => $location->restaurant_id, 'user_id' => $actor->id, ...$d, 'webhook_secret' => Str::random(64)]);

            return $this->issue($connection);
        });
    }

    public function requireLifecycle(User $actor, WordpressConnection $connection): void
    {
        app(TenantAccess::class)->requireManage($actor, $connection->organization_id);
        abort_unless($actor->id === $connection->user_id || app(TenantAccess::class)->role($actor, $connection->organization_id) === 'owner', 403);
    }

    private function issue(WordpressConnection $c): array
    {
        $token = $c->user->createToken('WordPress: '.$c->name, $c->abilities, (int) $c->expires_in_days === 0 ? null : now()->addDays($c->expires_in_days));
        $token->accessToken->forceFill(['organization_id' => $c->organization_id, 'wordpress_connection_id' => $c->id])->save();

        return ['connection' => $c->only('public_id', 'name', 'site_url', 'abilities', 'location_ids', 'active'), 'token' => $token->plainTextToken, 'webhook_secret' => $c->webhook_secret, 'expires_at' => $token->accessToken->expires_at, 'api_url' => config('wordpress.api_url')];
    }

    public function rotate(User $actor, WordpressConnection $context): array
    {
        $this->requireLifecycle($actor, $context);

        return DB::transaction(function () use ($context) {
            $c = WordpressConnection::whereKey($context->id)->lockForUpdate()->firstOrFail();
            abort_unless($c->active, 409);
            PersonalAccessToken::where('wordpress_connection_id', $c->id)->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()->addMinutes(15)))->update(['expires_at' => now()->addMinutes(15)]);

            return $this->issue($c);
        });
    }

    public function revoke(User $actor, WordpressConnection $context): void
    {
        $this->requireLifecycle($actor, $context);
        DB::transaction(function () use ($context) {
            $c = WordpressConnection::whereKey($context->id)->lockForUpdate()->firstOrFail();
            $c->update(['active' => false]);
            PersonalAccessToken::where('wordpress_connection_id', $c->id)->delete();
            WebhookEndpoint::where('wordpress_connection_id', $c->id)->update(['enabled' => false]);
        });
    }
}
