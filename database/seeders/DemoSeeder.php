<?php

namespace Database\Seeders;

use App\Domain\Orders\Actions\PlaceOrderAction;
use App\Domain\Orders\Actions\TransitionOrderStatus;
use App\Domain\Orders\OrderStatus;
use App\Domain\Tenancy\Actions\OnboardOrganization;
use App\Models\DeliveryZone;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\ModifierGroup;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class DemoSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new \RuntimeException('Demo data may only be seeded locally or in tests.');
        }
        $accounts = json_decode(Storage::disk('local')->get('demo-accounts.json') ?? '[]', true, flags: JSON_THROW_ON_ERROR);
        foreach (['Atelier Bistro', 'Verde Kitchen', 'Cuptor & Co'] as $index => $name) {
            $email = 'owner'.($index + 1).'@example.test';
            if (User::where('email', $email)->exists()) {
                continue;
            }
            DB::transaction(function () use ($email, $index, $name, &$accounts) {
                $password = Str::password(24);
                $user = User::create(['name' => $name.' Owner', 'email' => $email, 'password' => $password]);
                $user->forceFill(['email_verified_at' => now()])->save();
                $location = app(OnboardOrganization::class)->execute($user, ['organization_name' => $name.' SRL', 'restaurant_name' => $name, 'slug' => Str::slug($name), 'location_name' => 'Central', 'address' => 'Strada Exemplu '.($index + 1).', București']);
                $location->update(['accepting_orders' => true]);
                $location->hours()->update(['opens_at' => '00:00', 'closes_at' => '23:59']);
                $location->fulfillment()->update(['delivery_enabled' => true]);
                DeliveryZone::create(['location_id' => $location->id, 'name' => 'Central area', 'postal_prefix' => '01', 'fee' => 1000, 'minimum_order' => 3000]);
                $groups = [];
                foreach (['Extra toppings', 'Sauces'] as $groupName) {
                    $group = ModifierGroup::create(['organization_id' => $location->organization_id, 'name' => $groupName, 'max_choices' => 2]);
                    foreach (['House special', 'Classic', 'Spicy'] as $n => $option) {
                        $group->options()->create(['name' => $option, 'price' => ($n + 1) * 200]);
                    }
                    $groups[] = $group;
                }
                $menu = $location->menus()->firstOrFail();
                $first = null;
                $catalog = ['Starters' => ['Bruschetta', 'Roasted aubergine', 'Hummus & pita', 'Soup of the day'], 'Mains' => ['Grilled chicken', 'Mushroom risotto', 'Slow cooked beef', 'Seasonal pasta'], 'Pizza & flatbreads' => ['Margherita', 'Garden pizza', 'Spicy salami', 'Four cheese'], 'Desserts' => ['Tiramisu', 'Chocolate cake', 'Cheesecake', 'Apple crumble'], 'Drinks' => ['Lemonade', 'Iced tea', 'Sparkling water', 'Fresh orange juice']];
                foreach ($catalog as $categoryName => $products) {
                    $category = MenuCategory::create(['organization_id' => $location->organization_id, 'menu_id' => $menu->id, 'name' => $categoryName]);
                    foreach ($products as $n => $productName) {
                        $item = MenuItem::create(['organization_id' => $location->organization_id, 'category_id' => $category->id, 'name' => $productName, 'description' => 'Prepared fresh with carefully selected ingredients.', 'price' => 1800 + $n * 700, 'tax_basis_points' => 1100]);
                        $item->modifierGroups()->attach($groups[0]->id, ['organization_id' => $location->organization_id]);
                        if ($n === 1) {
                            $item->variants()->createMany([['name' => 'Regular', 'price' => $item->price], ['name' => 'Large', 'price' => $item->price + 1000]]);
                        }
                        $first ??= $item;
                    }
                }
                foreach (['pending', 'accepted', 'preparing', 'ready', 'completed', 'rejected'] as $target) {
                    $result = app(PlaceOrderAction::class)->execute($location, ['customer_name' => 'Demo Guest', 'customer_phone' => '0700000000', 'fulfillment' => 'pickup', 'items' => [['product_id' => $first->public_id, 'quantity' => 2]]], (string) Str::uuid());
                    $order = $result['order'];
                    $steps = $target === 'rejected' ? ['rejected'] : array_slice(['accepted', 'preparing', 'ready', 'completed'], 0, array_search($target, ['pending', 'accepted', 'preparing', 'ready', 'completed'], true));
                    foreach ($steps as $status) {
                        $order = app(TransitionOrderStatus::class)->execute($user, $order, OrderStatus::from($status), $order->version, $status === 'rejected' ? 'Demo rejection' : null);
                    }
                }
                $accounts[] = ['email' => $email, 'password' => $password, 'location' => $location->public_id];
            });
            Storage::disk('local')->put('demo-accounts.json', json_encode($accounts, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
        }
        if ($accounts) {
            Storage::disk('local')->put('demo-accounts.json', json_encode($accounts, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
        }
        $this->command?->info('Demo credentials are in storage/app/private/demo-accounts.json. They are local only and must not be committed.');
    }
}
