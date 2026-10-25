<?php

use App\Domain\Orders\Actions\PlaceOrderAction;
use App\Domain\Tenancy\Actions\OnboardOrganization;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\RestaurantLocation;
use App\Models\User;
use Illuminate\Support\Str;

function tenantFixture(string $slug = 'bistro'): array
{
    $user = User::factory()->create();
    $location = app(OnboardOrganization::class)->execute($user, ['organization_name' => $slug, 'restaurant_name' => $slug, 'slug' => $slug, 'location_name' => 'Central', 'address' => 'Test street']);
    $location->update(['accepting_orders' => true]);
    $location->hours()->update(['opens_at' => '00:00', 'closes_at' => '23:59']);
    $category = MenuCategory::create(['organization_id' => $location->organization_id, 'menu_id' => $location->menus()->first()->id, 'name' => 'Mains']);
    $item = MenuItem::create(['organization_id' => $location->organization_id, 'category_id' => $category->id, 'name' => 'Pizza', 'price' => 3500, 'tax_basis_points' => 1100]);

    return [$user, $location, $item];
}
function checkoutData(MenuItem $item): array
{
    return ['customer_name' => 'Guest', 'customer_phone' => '0712345678', 'fulfillment' => 'pickup', 'items' => [['product_id' => $item->public_id, 'quantity' => 2]]];
}
function placeFixture(RestaurantLocation $location, MenuItem $item): array
{
    return app(PlaceOrderAction::class)->execute($location, checkoutData($item), (string) Str::uuid());
}
