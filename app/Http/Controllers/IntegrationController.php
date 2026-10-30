<?php

namespace App\Http\Controllers;

use App\Domain\Tenancy\TenantAccess;
use App\Models\RestaurantLocation;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Laravel\Sanctum\TransientToken;

class IntegrationController extends Controller
{
    private function context(Request $request, string $location): RestaurantLocation
    {
        $token = $request->user()->currentAccessToken();
        abort_if($token && ! ($token instanceof TransientToken), 403, 'Manage integrations using your browser session.');
        $context = RestaurantLocation::where('public_id', $location)->firstOrFail();
        app(TenantAccess::class)->requireManage($request->user(), $context->organization_id);

        return $context;
    }

    public function index(Request $request, string $location)
    {
        $context = $this->context($request, $location);

        return response()->json(['tokens' => $request->user()->tokens()->whereNull('wordpress_connection_id')->where('organization_id', $context->organization_id)->get(['id', 'name', 'abilities', 'last_used_at', 'expires_at', 'created_at'])]);
    }

    public function store(Request $request, string $location)
    {
        $context = $this->context($request, $location);
        $data = $request->validate(['name' => ['required', 'string', 'max:100'], 'abilities' => ['required', 'array', 'min:1', 'max:4'], 'abilities.*' => ['required', 'distinct', Rule::in(['menu:read', 'menu:write', 'orders:read', 'orders:manage'])]]);
        $created = $request->user()->createToken($data['name'], $data['abilities'], now()->addDays(90));
        $created->accessToken->forceFill(['organization_id' => $context->organization_id])->save();

        return response()->json(['token' => $created->plainTextToken, 'expires_at' => $created->accessToken->expires_at], 201)->header('Cache-Control', 'no-store');
    }

    public function destroy(Request $request, string $location, int $token)
    {
        $context = $this->context($request, $location);
        $request->user()->tokens()->whereNull('wordpress_connection_id')->where('organization_id', $context->organization_id)->findOrFail($token)->delete();

        return response()->noContent();
    }
}
