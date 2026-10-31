<?php

namespace App\Http\Controllers;

use App\Domain\Analytics\RestaurantOverview;
use App\Domain\Menus\SaveMenuCategory;
use App\Domain\Menus\SaveModifierGroup;
use App\Domain\Restaurants\Actions\SaveLocationSettings;
use App\Domain\Tenancy\Actions\OnboardOrganization;
use App\Domain\Tenancy\StaffPermissions;
use App\Domain\Tenancy\TenantAccess;
use App\Http\Requests\LocationSettingsRequest;
use App\Http\Requests\OnboardingRequest;
use App\Models\Ingredient;
use App\Models\LocationStaff;
use App\Models\ModifierGroup;
use App\Models\OrganizationMembership;
use App\Models\OrganizationSubscription;
use App\Models\RestaurantLocation;
use App\Models\StaffInvitation;
use Illuminate\Http\Request;
use Inertia\Inertia;

class WorkspaceController extends Controller
{
    public function locations(Request $request)
    {
        $memberships = OrganizationMembership::where('user_id', $request->user()->id)->get();

        return RestaurantLocation::with('restaurant')->where(function ($query) use ($memberships, $request) {
            $query->whereIn('organization_id', $memberships->whereIn('role', ['owner', 'manager'])->pluck('organization_id'))
                ->orWhereIn('id', LocationStaff::where('user_id', $request->user()->id)->whereIn('organization_id', $memberships->where('role', 'staff')->pluck('organization_id'))->pluck('location_id'));
        })->get();
    }

    public function home(Request $request)
    {
        $location = $this->locations($request)->first();

        return $location ? redirect('/app/locations/'.$location->public_id) : redirect($request->user()->is_platform_admin ? '/admin' : '/onboarding');
    }

    public function onboard(OnboardingRequest $request, OnboardOrganization $action)
    {
        $location = $action->execute($request->user(), $request->validated());

        return redirect('/app/locations/'.$location->public_id.'/setup');
    }

    public function show(Request $request, string $location, string $section = 'dashboard')
    {
        return Inertia::render('restaurant/'.ucfirst($section), $this->workspaceProps($request, $location, $section));
    }

    public function workspaceProps(Request $request, string $location, string $section): array
    {
        $context = RestaurantLocation::where('public_id', $location)->firstOrFail();
        app(TenantAccess::class)->requireOperate($request->user(), $context);
        abort_unless(in_array($section, ['dashboard', 'orders', 'menu', 'settings', 'kitchen', 'integrations', 'staff', 'branding', 'webhooks', 'locations']), 404);
        $manage = app(TenantAccess::class)->canManage($request->user(), $context->organization_id);
        if (in_array($section, ['orders', 'kitchen', 'menu'], true)) {
            app(TenantAccess::class)->requireFeature($request->user(), $context, $section);
        }
        abort_if(in_array($section, ['settings', 'integrations', 'staff', 'branding', 'webhooks', 'locations']) && ! $manage, 403);
        $props = ['location' => $context->load('restaurant', 'hours', 'fulfillment', 'zones'), 'locations' => $this->locations($request), 'canManage' => $manage, 'permissions' => app(TenantAccess::class)->permissions($request->user(), $context->organization_id)];
        if ($section === 'locations') {
            $props['manageableLocationIds'] = $props['locations']->filter(fn ($place) => app(TenantAccess::class)->canManage($request->user(), $place->organization_id))->pluck('public_id')->values();
        }
        if ($section === 'staff') {
            $props['actorRole'] = app(TenantAccess::class)->role($request->user(), $context->organization_id);
            $props['members'] = OrganizationMembership::where('organization_id', $context->organization_id)->with('user:id,name,email')->get()->map(fn ($m) => [...$m->only('id', 'role', 'user_id', 'permissions'), 'effective_permissions' => StaffPermissions::effective($m), 'user' => $m->user, 'location_ids' => LocationStaff::where('organization_id', $context->organization_id)->where('user_id', $m->user_id)->pluck('location_id')]);
            $props['invitations'] = StaffInvitation::where('organization_id', $context->organization_id)->whereNull('accepted_at')->latest()->get();
        }
        if ($section === 'settings') {
            $basics = $context->setup_profile['basics'] ?? [];
            $props['mapCenter'] = isset($basics['latitude'], $basics['longitude']) ? [(float) $basics['latitude'], (float) $basics['longitude']] : null;
            $props['mapConfig'] = config('maps');
            $props['mapAddress'] = ['address' => $context->address, 'city' => $basics['city'] ?? '', 'country' => $basics['country'] ?? 'RO', 'postal_code' => $basics['postal_code'] ?? ''];
        }
        if ($section === 'menu') {
            $props['menus'] = $context->menus()->with('categories.items.variants.modifierGroups', 'categories.items.ingredients', 'categories.items.modifierGroups', 'categories.modifierGroups')->get();
            $props['menuWritable'] = app(TenantAccess::class)->canFeature($request->user(), $context, 'menu', true) && ! RestaurantLocation::where('organization_id', $context->organization_id)->whereHas('menus', fn ($q) => $q->whereIn('menus.id', $props['menus']->pluck('id')))->get()->contains(fn ($l) => ! app(TenantAccess::class)->canFeature($request->user(), $l, 'menu', true));
            $props['modifierWritable'] = $props['menuWritable'] && ! RestaurantLocation::where('organization_id', $context->organization_id)->get()->contains(fn ($l) => ! app(TenantAccess::class)->canFeature($request->user(), $l, 'menu', true));
            $props['ingredients'] = Ingredient::availableFor($context->restaurant_id)->withTrashed()->orderBy('name')->get();
            if (! $manage) {
                $props['ingredients'] = $props['menuWritable'] ? $props['ingredients']->map(fn ($ingredient) => $ingredient->only('id', 'name', 'name_en', 'contains_allergens', 'from_frozen', 'is_active', 'deleted_at', 'restaurant_id')) : [];
            }
            $props['groups'] = ModifierGroup::with('options')->forMenus($props['menus']->pluck('id'))->where('organization_id', $context->organization_id)->get();
        }
        if ($section === 'dashboard') {
            $days = (int) $request->query('days', 30);
            abort_unless(in_array($days, [7, 30, 90], true), 422);
            $props['analytics'] = app(RestaurantOverview::class)->build($request->user(), $context, $days);
            $props['metrics'] = null;
            $props['subscription'] = $manage ? OrganizationSubscription::with('plan')->where('organization_id', $context->organization_id)->first() : null;
        }

        return $props;
    }

    public function category(Request $request, string $location)
    {
        app(SaveMenuCategory::class)->execute($request->user(), RestaurantLocation::where('public_id', $location)->firstOrFail(), $request->all());

        return back()->with('success', __('Category saved.'));
    }

    public function modifier(Request $request, string $location)
    {
        app(SaveModifierGroup::class)->execute($request->user(), RestaurantLocation::where('public_id', $location)->firstOrFail(), $request->all());

        return back()->with('success', __('Modifier group saved.'));
    }

    public function settings(LocationSettingsRequest $request, string $location)
    {
        $context = RestaurantLocation::where('public_id', $location)->firstOrFail();
        app(SaveLocationSettings::class)->execute($request->user(), $context, $request->validated());

        return back()->with('success', __('Location settings saved.'));
    }
}
