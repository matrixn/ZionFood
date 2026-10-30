<?php

namespace App\Http\Controllers;

use App\Domain\Integrations\Actions\ManageWordpressConnection;
use App\Domain\Integrations\WordpressAccess;
use App\Domain\Tenancy\TenantAccess;
use App\Models\RestaurantLocation;
use App\Models\WordpressConnection;
use Illuminate\Http\Request;
use Laravel\Sanctum\TransientToken;

class WordpressConnectionController extends Controller
{
    private function context(Request $r, string $location): RestaurantLocation
    {
        $token = $r->user()->currentAccessToken();
        abort_if($token && ! $token instanceof TransientToken, 403);
        $l = RestaurantLocation::where('public_id', $location)->firstOrFail();
        app(TenantAccess::class)->requireManage($r->user(), $l->organization_id);

        return $l;
    }

    public function index(Request $r, string $location)
    {
        $l = $this->context($r, $location);

        return response()->json(['connections' => WordpressConnection::where('restaurant_id', $l->restaurant_id)->where('organization_id', $l->organization_id)->latest('id')->get()->map(fn ($c) => [...$c->only('public_id', 'name', 'site_url', 'location_ids', 'abilities', 'active', 'created_at'), 'can_manage' => $r->user()->id === $c->user_id || app(TenantAccess::class)->role($r->user(), $l->organization_id) === 'owner']), 'locations' => RestaurantLocation::where('restaurant_id', $l->restaurant_id)->where('organization_id', $l->organization_id)->get(['id', 'public_id', 'name'])->map(fn ($place) => $place->only('id', 'public_id', 'name')), 'abilities' => WordpressAccess::ABILITIES, 'api_url' => config('wordpress.api_url')])->header('Cache-Control', 'private, no-store');
    }

    public function create(Request $r, string $location, ManageWordpressConnection $action)
    {
        return response()->json($action->create($r->user(), $this->context($r, $location), $r->all()), 201)->header('Cache-Control', 'private, no-store');
    }

    public function change(Request $r, string $location, string $connection, ManageWordpressConnection $action)
    {
        $l = $this->context($r, $location);
        $c = WordpressConnection::where('public_id', $connection)->where('restaurant_id', $l->restaurant_id)->where('organization_id', $l->organization_id)->firstOrFail();
        if ($r->isMethod('DELETE')) {
            $action->revoke($r->user(), $c);

            return response()->noContent();
        }

        return response()->json($action->rotate($r->user(), $c))->header('Cache-Control', 'private, no-store');
    }
}
