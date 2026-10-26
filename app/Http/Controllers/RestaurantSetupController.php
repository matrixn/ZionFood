<?php

namespace App\Http\Controllers;

use App\Domain\Restaurants\Actions\SaveRestaurantSetup;
use App\Domain\Restaurants\SetupProgress;
use App\Domain\Tenancy\TenantAccess;
use App\Http\Requests\RestaurantSetupRequest;
use App\Models\Cuisine;
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
            'cuisines' => $step === 'cuisines' ? Cuisine::where('is_active', true)->orWhereIn('id', $context->cuisines()->select('cuisines.id'))->orderBy('sort_order')->orderBy('id')->get() : [],
            'selectedCuisines' => $step === 'cuisines' ? $context->cuisines()->get()->map(fn ($c) => ['id' => $c->id, 'selected_at' => $c->pivot->created_at?->toIso8601String()]) : [],
        ]);
    }

    public function update(RestaurantSetupRequest $request, string $location, string $step, SaveRestaurantSetup $action)
    {
        $action->execute($request->user(), RestaurantLocation::where('public_id', $location)->firstOrFail(), $step, $request->validated());

        return back()->with('success', __('Restaurant setup saved.'));
    }
}
