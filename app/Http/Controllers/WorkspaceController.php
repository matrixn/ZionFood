<?php

namespace App\Http\Controllers;

use App\Domain\Menus\RecordMenuChange;
use App\Domain\Tenancy\Actions\OnboardOrganization;
use App\Domain\Tenancy\TenantAccess;
use App\Http\Requests\LocationSettingsRequest;
use App\Http\Requests\OnboardingRequest;
use App\Models\LocationStaff;
use App\Models\MenuCategory;
use App\Models\ModifierGroup;
use App\Models\Order;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\OrganizationSubscription;
use App\Models\RestaurantLocation;
use App\Models\StaffInvitation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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

        return $location ? redirect('/app/locations/'.$location->public_id) : redirect('/onboarding');
    }

    public function onboard(OnboardingRequest $request, OnboardOrganization $action)
    {
        $location = $action->execute($request->user(), $request->validated());

        return redirect('/app/locations/'.$location->public_id.'/setup');
    }

    public function show(Request $request, string $location, string $section = 'dashboard')
    {
        $context = RestaurantLocation::where('public_id', $location)->firstOrFail();
        app(TenantAccess::class)->requireOperate($request->user(), $context);
        abort_unless(in_array($section, ['dashboard', 'orders', 'menu', 'settings', 'kitchen', 'integrations', 'staff', 'branding', 'webhooks']), 404);
        $manage = app(TenantAccess::class)->canManage($request->user(), $context->organization_id);
        abort_if(in_array($section, ['menu', 'settings', 'integrations', 'staff', 'branding', 'webhooks']) && ! $manage, 403);
        $props = ['location' => $context->load('restaurant', 'hours', 'fulfillment', 'zones'), 'locations' => $this->locations($request), 'canManage' => $manage];
        if ($section === 'staff') {
            $props['actorRole'] = app(TenantAccess::class)->role($request->user(), $context->organization_id);
            $props['members'] = OrganizationMembership::where('organization_id', $context->organization_id)->with('user:id,name,email')->get()->map(fn ($m) => [...$m->only('id', 'role', 'user_id'), 'user' => $m->user, 'location_ids' => LocationStaff::where('organization_id', $context->organization_id)->where('user_id', $m->user_id)->pluck('location_id')]);
            $props['invitations'] = StaffInvitation::where('organization_id', $context->organization_id)->whereNull('accepted_at')->latest()->get();
        }
        if ($section === 'settings') {
            $basics = $context->setup_profile['basics'] ?? [];
            $props['mapCenter'] = isset($basics['latitude'], $basics['longitude']) ? [(float) $basics['latitude'], (float) $basics['longitude']] : null;
            $props['mapConfig'] = config('maps');
            $props['mapAddress'] = ['address' => $context->address, 'city' => $basics['city'] ?? '', 'country' => $basics['country'] ?? 'RO', 'postal_code' => $basics['postal_code'] ?? ''];
        }
        if ($section === 'menu') {
            $props['menus'] = $context->menus()->with('categories.items.variants', 'categories.items.modifierGroups')->get();
            $props['groups'] = ModifierGroup::with('options')->where('organization_id', $context->organization_id)->get();
        }
        if ($section === 'dashboard') {
            $query = Order::where('organization_id', $context->organization_id)->where('location_id', $context->id);
            $props['metrics'] = ['orders' => (clone $query)->whereDate('created_at', today())->count(), 'revenue' => (clone $query)->whereDate('created_at', today())->where('status', 'completed')->sum('total'), 'active' => (clone $query)->whereNotIn('status', ['completed', 'cancelled', 'rejected'])->count()];
            $props['subscription'] = OrganizationSubscription::with('plan')->where('organization_id', $context->organization_id)->first();
        }

        return Inertia::render('restaurant/'.ucfirst($section), $props);
    }

    public function category(Request $request, string $location)
    {
        $context = RestaurantLocation::where('public_id', $location)->firstOrFail();
        app(TenantAccess::class)->requireManage($request->user(), $context->organization_id);
        $data = $request->validate(['name' => ['required', 'string', 'max:100'], 'menu_id' => ['required', 'integer'], 'id' => ['nullable', 'integer']]);
        abort_unless($context->menus()->where('menus.id', $data['menu_id'])->exists(), 422);
        DB::transaction(function () use ($data, $context) {
            $category = isset($data['id']) ? MenuCategory::whereKey($data['id'])->where('menu_id', $data['menu_id'])->where('organization_id', $context->organization_id)->firstOrFail() : new MenuCategory(['organization_id' => $context->organization_id, 'menu_id' => $data['menu_id']]);
            $category->fill(['name' => $data['name']])->save();

            app(RecordMenuChange::class)->execute($context->organization_id, $data['menu_id']);
        });

        return back()->with('success', __('Category saved.'));
    }

    public function modifier(Request $request, string $location)
    {
        $context = RestaurantLocation::where('public_id', $location)->firstOrFail();
        app(TenantAccess::class)->requireManage($request->user(), $context->organization_id);
        $data = $request->validate(['id' => ['nullable', 'integer'], 'name' => ['required', 'string', 'max:100'], 'min_choices' => ['required', 'integer', 'min:0', 'max:20'], 'max_choices' => ['required', 'integer', 'gte:min_choices', 'max:20'], 'options' => ['required', 'array', 'min:1', 'max:30'], 'options.*.name' => ['required', 'string', 'max:100'], 'options.*.price' => ['required', 'integer', 'min:0', 'max:1000000']]);
        abort_if($data['min_choices'] > count($data['options']), 422, 'Not enough options for the minimum selection.');
        DB::transaction(function () use ($data, $context) {
            $group = isset($data['id']) ? ModifierGroup::whereKey($data['id'])->where('organization_id', $context->organization_id)->firstOrFail() : new ModifierGroup(['organization_id' => $context->organization_id]);
            $group->fill(array_intersect_key($data, array_flip(['name', 'min_choices', 'max_choices'])))->save();
            $group->options()->delete();
            foreach ($data['options'] as $option) {
                $group->options()->create(['name' => $option['name'], 'price' => $option['price']]);
            }
            app(RecordMenuChange::class)->execute($context->organization_id);
        });

        return back()->with('success', __('Modifier group saved.'));
    }

    public function settings(LocationSettingsRequest $request, string $location)
    {
        $context = RestaurantLocation::where('public_id', $location)->firstOrFail();
        app(TenantAccess::class)->requireManage($request->user(), $context->organization_id);
        $data = $request->validated();
        DB::transaction(function () use ($context, $data) {
            RestaurantLocation::whereKey($context->id)->lockForUpdate()->firstOrFail();
            $context->update(array_intersect_key($data, array_flip(['name', 'address', 'accepting_orders'])));
            $context->fulfillment()->updateOrCreate(['location_id' => $context->id], array_intersect_key($data, array_flip(['pickup_enabled', 'delivery_enabled', 'dine_in_cutoff_minutes'])));
            $context->hours()->delete();
            foreach ($data['hours'] as $hours) {
                $context->hours()->create(array_intersect_key($hours, array_flip(['weekday', 'opens_at', 'closes_at'])));
            }
            $context->zones()->delete();
            foreach ($data['zones'] as $zone) {
                $zone['postal_prefix'] ??= '';
                if (isset($zone['geometry'])) {
                    $zone['geometry'] = array_intersect_key($zone['geometry'], array_flip(['type', 'coordinates']));
                }
                $context->zones()->create(array_intersect_key($zone, array_flip(['name', 'postal_prefix', 'fee', 'minimum_order', 'geometry', 'priority'])));
            }
        });

        return back()->with('success', __('Location settings saved.'));
    }

    public function admin(Request $request, ?string $organization = null)
    {
        abort_unless($request->user()->is_platform_admin, 403);

        return Inertia::render('admin/Organizations', ['organizations' => Organization::withCount('restaurants', 'memberships')->orderBy('id')->paginate(25), 'selected' => $organization ? Organization::where('public_id', $organization)->with('restaurants.locations', 'memberships.user')->firstOrFail() : null, 'subscriptions' => OrganizationSubscription::with('plan')->get()]);
    }
}
