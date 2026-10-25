<?php

namespace App\Http\Middleware;

use App\Models\OrganizationMembership;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function share(Request $request): array
    {
        return [...parent::share($request), 'auth' => ['user' => $request->user()?->only('id', 'name', 'email', 'is_platform_admin'), 'organizations' => fn () => $request->user() ? OrganizationMembership::with('organization')->where('user_id', $request->user()->id)->get()->map(fn ($m) => ['id' => $m->organization_id, 'name' => $m->organization->name, 'role' => $m->role]) : []], 'version' => config('zionfood.version'), 'flash' => ['success' => fn () => $request->session()->get('success')]];
    }
}
