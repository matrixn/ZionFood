<?php

namespace App\Http\Middleware;

use App\Domain\Tenancy\StaffPermissions;
use App\Domain\Tenancy\TenantAccess;
use App\Models\OrganizationMembership;
use App\Models\RestaurantLocation;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function share(Request $request): array
    {
        return [...parent::share($request), 'permissions' => function () use ($request) {
            $location = $request->is('app/locations/*') ? RestaurantLocation::where('public_id', (string) $request->route('location'))->first() : null;

            return $location && $request->user() && app(TenantAccess::class)->canOperate($request->user(), $location) ? app(TenantAccess::class)->permissions($request->user(), $location->organization_id) : array_fill_keys(StaffPermissions::FEATURES, 'none');
        }, 'locale' => app()->getLocale(), 'auth' => ['user' => $request->user()?->only('id', 'name', 'email', 'is_platform_admin'), 'organizations' => fn () => $request->user() ? OrganizationMembership::with('organization')->where('user_id', $request->user()->id)->get()->map(fn ($m) => ['id' => $m->organization_id, 'name' => $m->organization->name, 'role' => $m->role]) : []], 'version' => config('zionfood.version'), 'flash' => ['success' => fn () => $request->session()->get('success')]];
    }
}
