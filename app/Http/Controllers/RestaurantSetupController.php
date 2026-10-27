<?php

namespace App\Http\Controllers;

use App\Domain\Companies\ResolveCompany;
use App\Domain\Restaurants\Actions\SaveRestaurantSetup;
use App\Domain\Restaurants\LocateRestaurant;
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
            'editor' => in_array($step, ['hours', 'delivery', 'menu', 'branding', 'staff', 'locations', 'general'], true) ? app(WorkspaceController::class)->workspaceProps($request, $location, in_array($step, ['hours', 'delivery', 'general'], true) ? 'settings' : $step) : null,
            'step' => $step, 'profile' => $context->setup_profile ?? [], 'revision' => $context->setup_revision,
            'progress' => app(SetupProgress::class)->for($context),
            'mapConfig' => config('maps'),
            'cuisines' => $step === 'cuisines' ? Cuisine::where('is_active', true)->orWhereIn('id', $context->cuisines()->select('cuisines.id'))->orderBy('sort_order')->orderBy('id')->get() : [],
            'selectedCuisines' => $step === 'cuisines' ? $context->cuisines()->get()->map(fn ($c) => ['id' => $c->id, 'selected_at' => $c->pivot->created_at?->toIso8601String()]) : [],
        ]);
    }

    public function update(RestaurantSetupRequest $request, string $location, string $step, SaveRestaurantSetup $action)
    {
        $action->execute($request->user(), RestaurantLocation::where('public_id', $location)->firstOrFail(), $step, $request->validated());

        return back()->with('success', __('Restaurant setup saved.'));
    }

    public function locate(Request $request, string $location, LocateRestaurant $locator)
    {
        $context = RestaurantLocation::where('public_id', $location)->firstOrFail();
        app(TenantAccess::class)->requireManage($request->user(), $context->organization_id);
        $data = $request->validate(['address' => ['required', 'string', 'min:5', 'max:255'], 'city' => ['required', 'string', 'min:2', 'max:100'], 'country' => ['required', 'string', 'regex:/^[A-Z]{2}$/'], 'postal_code' => ['nullable', 'string', 'max:20']]);

        return response()->json(['results' => $locator->execute($data)])->header('Cache-Control', 'no-store');
    }

    public function reverse(Request $request, string $location, LocateRestaurant $locator)
    {
        $context = RestaurantLocation::where('public_id', $location)->firstOrFail();
        app(TenantAccess::class)->requireManage($request->user(), $context->organization_id);
        $data = $request->validate(['latitude' => ['required', 'numeric', 'between:-85,85'], 'longitude' => ['required', 'numeric', 'between:-180,180']]);

        return response()->json(['address' => $locator->reverse($data)])->header('Cache-Control', 'no-store');
    }

    public function company(Request $request, string $location, ResolveCompany $resolver)
    {
        $context = RestaurantLocation::where('public_id', $location)->firstOrFail();
        app(TenantAccess::class)->requireManage($request->user(), $context->organization_id);
        $data = $request->validate(['tax_id' => ['required', 'string', 'max:40']]);

        return response()->json(['company' => $resolver->execute($data['tax_id']), 'source' => 'ANAF', 'checked_at' => now('Europe/Bucharest')->toIso8601String()])->header('Cache-Control', 'no-store');
    }
}
