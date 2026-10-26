<?php

namespace App\Http\Controllers;

use App\Domain\Restaurants\Actions\SaveRestaurantSetup;
use App\Domain\Restaurants\SetupProgress;
use App\Domain\Tenancy\TenantAccess;
use App\Http\Requests\RestaurantSetupRequest;
use App\Models\RestaurantLocation;
use Illuminate\Http\Request;
use Inertia\Inertia;

class RestaurantSetupController extends Controller
{
    public function show(Request $request, string $location, string $step = 'basics')
    {
        $context = RestaurantLocation::where('public_id', $location)->firstOrFail();
        app(TenantAccess::class)->requireManage($request->user(), $context->organization_id);
        abort_unless(in_array($step, SetupProgress::STEPS, true), 404);

        return Inertia::render('restaurant/Setup', [
            'location' => $context->load('restaurant', 'hours', 'fulfillment', 'zones'),
            'locations' => app(WorkspaceController::class)->locations($request), 'canManage' => true,
            'step' => $step, 'profile' => $context->setup_profile ?? [], 'revision' => $context->setup_revision,
            'progress' => app(SetupProgress::class)->for($context),
        ]);
    }

    public function update(RestaurantSetupRequest $request, string $location, string $step, SaveRestaurantSetup $action)
    {
        $action->execute($request->user(), RestaurantLocation::where('public_id', $location)->firstOrFail(), $step, $request->validated());

        return back()->with('success', __('Restaurant setup saved.'));
    }
}
