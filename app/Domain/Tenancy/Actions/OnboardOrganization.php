<?php

namespace App\Domain\Tenancy\Actions;

use App\Models\LocationBusinessHour;
use App\Models\LocationFulfillmentSetting;
use App\Models\Menu;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\OrganizationSubscription;
use App\Models\Plan;
use App\Models\Restaurant;
use App\Models\RestaurantLocation;
use App\Models\StorefrontDomain;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OnboardOrganization
{
    public function execute(User $user, array $data): RestaurantLocation
    {
        if (in_array($data['slug'], config('zionfood.reserved_slugs'), true)) {
            throw ValidationException::withMessages(['slug' => __('This address is reserved.')]);
        }

        return DB::transaction(function () use ($user, $data) {
            $organization = Organization::create(['name' => $data['organization_name']]);
            OrganizationMembership::create(['organization_id' => $organization->id, 'user_id' => $user->id, 'role' => 'owner']);
            $restaurant = Restaurant::create(['organization_id' => $organization->id, 'name' => $data['restaurant_name'], 'slug' => $data['slug']]);
            $location = RestaurantLocation::create(['organization_id' => $organization->id, 'restaurant_id' => $restaurant->id, 'name' => $data['location_name'], 'address' => $data['address']]);
            LocationFulfillmentSetting::create(['location_id' => $location->id]);
            foreach (range(1, 7) as $day) {
                LocationBusinessHour::create(['location_id' => $location->id, 'weekday' => $day, 'opens_at' => '10:00', 'closes_at' => '22:00']);
            }
            $menu = Menu::create(['organization_id' => $organization->id, 'restaurant_id' => $restaurant->id, 'name' => 'Main menu']);
            $location->menus()->attach($menu->id, ['organization_id' => $organization->id]);
            StorefrontDomain::create(['location_id' => $location->id, 'hostname' => $data['slug'].'.'.config('zionfood.storefront_base_domain'), 'verification_status' => 'verified']);
            $plan = Plan::firstOrCreate(['name' => 'MVP'], ['features' => ['ordering' => true, 'kitchen' => true]]);
            OrganizationSubscription::create(['organization_id' => $organization->id, 'plan_id' => $plan->id, 'trial_ends_at' => now()->addDays(14)]);

            return $location;
        });
    }
}
