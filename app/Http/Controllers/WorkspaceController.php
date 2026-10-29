<?php

namespace App\Http\Controllers;

use App\Domain\Analytics\RestaurantOverview;
use App\Domain\Menus\MenuWriteLock;
use App\Domain\Menus\RecordMenuChange;
use App\Domain\Restaurants\Actions\SaveLocationSettings;
use App\Domain\Tenancy\Actions\OnboardOrganization;
use App\Domain\Tenancy\StaffPermissions;
use App\Domain\Tenancy\TenantAccess;
use App\Http\Requests\LocationSettingsRequest;
use App\Http\Requests\OnboardingRequest;
use App\Models\Ingredient;
use App\Models\LocationStaff;
use App\Models\MenuCategory;
use App\Models\ModifierGroup;
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
        $context = RestaurantLocation::where('public_id', $location)->firstOrFail();
        app(TenantAccess::class)->requireFeature($request->user(), $context, 'menu', true);
        $data = $request->validate(['name' => ['required', 'string', 'max:100'], 'menu_id' => ['required', 'integer'], 'id' => ['nullable', 'integer'], 'modifier_group_ids' => ['sometimes', 'array', 'max:20'], 'modifier_group_ids.*' => ['integer', 'distinct']]);
        abort_unless($context->menus()->where('menus.id', $data['menu_id'])->exists(), 422);
        app(TenantAccess::class)->requireMenu($request->user(), $context->organization_id, $data['menu_id']);
        DB::transaction(function () use ($data, $context) {
            MenuWriteLock::organization($context->organization_id);
            $category = isset($data['id']) ? MenuCategory::whereKey($data['id'])->where('menu_id', $data['menu_id'])->where('organization_id', $context->organization_id)->firstOrFail() : new MenuCategory(['organization_id' => $context->organization_id, 'menu_id' => $data['menu_id']]);
            $category->fill(['name' => $data['name']])->save();
            if (isset($data['modifier_group_ids'])) {
                abort_unless(ModifierGroup::where('organization_id', $context->organization_id)->whereIn('id', $data['modifier_group_ids'])->count() === count($data['modifier_group_ids']), 422);
                $category->modifierGroups()->sync(collect($data['modifier_group_ids'])->mapWithKeys(fn ($id) => [$id => ['organization_id' => $context->organization_id]])->all());
            }

            app(RecordMenuChange::class)->execute($context->organization_id, $data['menu_id']);
        });

        return back()->with('success', __('Category saved.'));
    }

    public function modifier(Request $request, string $location)
    {
        $context = RestaurantLocation::where('public_id', $location)->firstOrFail();
        app(TenantAccess::class)->requireFeature($request->user(), $context, 'menu', true);
        abort_if(RestaurantLocation::where('organization_id', $context->organization_id)->get()->contains(fn ($l) => ! app(TenantAccess::class)->canFeature($request->user(), $l, 'menu', true)), 403);
        $data = $request->validate(['id' => ['nullable', 'integer'], 'name' => ['required', 'string', 'max:100'], 'is_required' => ['sometimes', 'boolean'], 'allow_quantity' => ['sometimes', 'boolean'], 'min_choices' => ['required', 'integer', 'min:0', 'max:20'], 'max_choices' => ['required', 'integer', 'gte:min_choices', 'max:20'], 'options' => ['required', 'array', 'min:1', 'max:30'], 'options.*.name' => ['required', 'string', 'max:100'], 'options.*.price' => ['required', 'integer', 'min:0', 'max:1000000'], 'options.*.is_default' => ['sometimes', 'boolean'], 'options.*.max_quantity' => ['sometimes', 'integer', 'min:1', 'max:50'], 'options.*.kitchen_name' => ['nullable', 'string', 'max:150']]);
        abort_if($data['min_choices'] > count($data['options']), 422, 'Not enough options for the minimum selection.');
        $defaultCount = count(array_filter($data['options'], fn ($o) => $o['is_default'] ?? false));
        abort_if($defaultCount > $data['max_choices'] || ($defaultCount > 0 && $defaultCount < max(($data['is_required'] ?? ($data['min_choices'] > 0)) ? 1 : 0, $data['min_choices'])) || (($data['is_required'] ?? false) && $data['max_choices'] < 1), 422, 'Invalid modifier defaults or selection limits.');
        DB::transaction(function () use ($data, $context) {
            MenuWriteLock::organization($context->organization_id);
            $group = isset($data['id']) ? ModifierGroup::whereKey($data['id'])->where('organization_id', $context->organization_id)->firstOrFail() : new ModifierGroup(['organization_id' => $context->organization_id]);
            $group->fill(array_intersect_key($data, array_flip(['name', 'min_choices', 'max_choices', 'is_required', 'allow_quantity'])))->save();
            $group->options()->delete();
            foreach ($data['options'] as $option) {
                $group->options()->create(array_intersect_key($option, array_flip(['name', 'price', 'is_default', 'max_quantity', 'kitchen_name'])));
            }
            app(RecordMenuChange::class)->execute($context->organization_id);
        });

        return back()->with('success', __('Modifier group saved.'));
    }

    public function settings(LocationSettingsRequest $request, string $location)
    {
        $context = RestaurantLocation::where('public_id', $location)->firstOrFail();
        app(SaveLocationSettings::class)->execute($request->user(), $context, $request->validated());

        return back()->with('success', __('Location settings saved.'));
    }

    public function admin(Request $request, ?string $organization = null)
    {
        abort_unless($request->user()->is_platform_admin, 403);

        return Inertia::render('admin/Organizations', ['organizations' => Organization::withCount('restaurants', 'memberships')->orderBy('id')->paginate(25), 'selected' => $organization ? Organization::where('public_id', $organization)->with('restaurants.locations', 'memberships.user')->firstOrFail() : null, 'subscriptions' => OrganizationSubscription::with('plan')->get()]);
    }
}
