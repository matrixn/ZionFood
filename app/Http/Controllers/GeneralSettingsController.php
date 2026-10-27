<?php

namespace App\Http\Controllers;

use App\Domain\Restaurants\LocationPreferences;
use App\Domain\Tenancy\TenantAccess;
use App\Models\RestaurantLocation;
use Illuminate\Http\Request;
use Inertia\Inertia;

class GeneralSettingsController extends Controller
{
    public function show(Request $request, string $location, WorkspaceController $workspace)
    {
        $context = RestaurantLocation::where('public_id', $location)->firstOrFail();
        app(TenantAccess::class)->requireManage($request->user(), $context->organization_id);

        return Inertia::render('restaurant/GeneralSettings', $workspace->workspaceProps($request, $location, 'settings'));
    }

    public function update(Request $request, string $location, LocationPreferences $preferences)
    {
        $preferences->save($request->user(), RestaurantLocation::where('public_id', $location)->firstOrFail(), $request->all());

        return back();
    }
}
