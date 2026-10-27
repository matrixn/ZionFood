<?php

namespace App\Console\Commands;

use App\Domain\Orders\Actions\PlaceOrderAction;
use App\Domain\Restaurants\SyncDiningTables;
use App\Domain\Tenancy\Actions\OnboardOrganization;
use App\Models\Ingredient;
use App\Models\LocationStaff;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\ModifierGroup;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrganizationMembership;
use App\Models\StaffInvitation;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class MakeBrowserFixtures extends Command
{
    protected $signature = 'zionfood:browser-fixtures {--admin : Create a separate local platform-admin browser fixture} {--services : Prepare a separate restaurant for reservation and QR browser tests} {--storefront : Prepare a photo menu and polygon for storefront UX tests} {--invitation : Prepare a separate unaccepted staff invitation without sending email} {--permissions : Prepare staff permissions and table-room browser fixtures} {--overview : Prepare isolated analytics samples without notifications} {--closed-window : Prepare an empty ordering schedule for availability tests}';

    protected $description = 'Create a private local-only browser test account without changing existing users';

    public function handle(): int
    {
        if (! app()->environment('local')) {
            $this->error('Browser fixtures are local only.');

            return self::FAILURE;
        }
        $account = DB::transaction(function () {
            $suffix = strtolower(Str::random(10));
            $password = Str::password(32);
            $user = User::create(['name' => 'Browser Test Owner', 'email' => 'browser-'.$suffix.'@example.test', 'password' => $password]);
            $user->forceFill(['email_verified_at' => now(), 'locale' => 'ro', 'is_platform_admin' => (bool) $this->option('admin')])->save();
            $location = app(OnboardOrganization::class)->execute($user, ['organization_name' => 'Browser Test '.$suffix, 'restaurant_name' => 'Restaurant Test', 'slug' => 'browser-'.$suffix, 'location_name' => 'Locație test', 'address' => 'Adresă de test']);
            $location->update(['accepting_orders' => true]);
            $location->hours()->update(['opens_at' => '00:00', 'closes_at' => '23:59']);
            $category = MenuCategory::create(['organization_id' => $location->organization_id, 'menu_id' => $location->menus()->first()->id, 'name' => 'Preparate']);
            MenuItem::create(['organization_id' => $location->organization_id, 'category_id' => $category->id, 'name' => 'Preparat de test', 'price' => 2500]);
            if ($this->option('storefront')) {
                $image = 'browser-fixtures/'.$suffix.'/pizza.jpg';
                Storage::disk('public')->put($image, file_get_contents(public_path('images/cuisines/pizza.jpg')));
                $location->restaurant->update(['logo_path' => $image]);
                $location->update(['setup_profile' => ['basics' => ['latitude' => 45.1, 'longitude' => 24.36]]]);
                $location->fulfillment()->update(['delivery_enabled' => true]);
                $location->zones()->create(['name' => 'Centru', 'postal_prefix' => '', 'fee' => 700, 'minimum_order' => 0, 'geometry' => ['type' => 'Polygon', 'coordinates' => [[[24.30, 45.04], [24.42, 45.04], [24.42, 45.16], [24.30, 45.16], [24.30, 45.04]]]]]);
                $item = $category->items()->first();
                $item->update(['image_path' => $image, 'portion_size' => '350 g', 'description' => 'Preparat cu ingrediente atent alese, pregătit la comandă.']);
                $ids = Ingredient::whereIn('normalized_name', ['branza feta', 'bagheta', 'sos rosii'])->pluck('id');
                $item->ingredients()->syncWithPivotValues($ids, ['organization_id' => $location->organization_id]);
                $group = ModifierGroup::create(['organization_id' => $location->organization_id, 'name' => 'Alege sosul', 'min_choices' => 1, 'max_choices' => 1]);
                for ($i = 1; $i <= 12; $i++) {
                    $group->options()->create(['name' => 'Sos '.$i, 'price' => 200]);
                }
                $item->modifierGroups()->attach($group->id, ['organization_id' => $location->organization_id]);
                $secondImage = 'browser-fixtures/'.$suffix.'/burger.jpg';
                Storage::disk('public')->put($secondImage, file_get_contents(public_path('images/cuisines/burger.jpg')));
                MenuItem::create(['organization_id' => $location->organization_id, 'category_id' => $category->id, 'name' => 'Burger de test', 'price' => 3200, 'portion_size' => '400 g', 'image_path' => $secondImage]);
            }
            if ($this->option('services') || $this->option('permissions') || $this->option('overview')) {
                $profile = ['basics' => ['city' => 'Râmnicu Vâlcea', 'phone' => '0712345678', 'latitude' => 45.1, 'longitude' => 24.36], 'seating' => ['mode' => 'tables', 'capacity' => 8, 'tables' => [['code' => 'T1', 'area' => 'Salon', 'seats' => 4], ['code' => 'T2', 'area' => 'Terasă', 'seats' => 4]]], 'services' => ['qr_requires_staff' => false], 'reservations' => ['planned' => true, 'confirmation' => 'automatic', 'duration_minutes' => 60, 'interval_minutes' => 30, 'minimum_notice_minutes' => 0, 'advance_days' => 30, 'min_party_size' => 1, 'max_party_size' => 4, 'cancellation_hours' => 0, 'hold_minutes' => 15]];
                $location->update(['setup_profile' => $profile]);
                $location->fulfillment()->update(['reservations_enabled' => true, 'dine_in_enabled' => true, 'dine_in_cutoff_minutes' => 0]);
                app(SyncDiningTables::class)->execute($location, $profile['seating']);
            }

            $account = ['email' => $user->email, 'password' => $password, 'location' => $location->public_id];
            if ($this->option('invitation')) {
                $token = Str::random(64);
                $email = 'new-staff-'.$suffix.'@example.test';
                $invitation = StaffInvitation::create(['organization_id' => $location->organization_id, 'public_id' => (string) Str::ulid(), 'email' => $email, 'invited_by' => $user->id, 'role' => 'staff', 'location_ids' => [$location->id], 'token_hash' => hash('sha256', $token), 'expires_at' => now()->addDays(7)]);
                $account += ['invitation' => $invitation->public_id, 'token' => $token, 'invited_email' => $email];
            }

            if ($this->option('permissions')) {
                $staffPassword = Str::password(32);
                $staff = User::create(['name' => 'Staff Permission Browser', 'email' => 'permission-staff-'.$suffix.'@example.test', 'password' => $staffPassword]);
                $staff->forceFill(['email_verified_at' => now(), 'locale' => 'ro'])->save();
                OrganizationMembership::create(['organization_id' => $location->organization_id, 'user_id' => $staff->id, 'role' => 'staff', 'permissions' => ['orders' => 'view', 'reservations' => 'none', 'tables' => 'manage', 'kitchen' => 'view', 'menu' => 'none']]);
                LocationStaff::create(['organization_id' => $location->organization_id, 'location_id' => $location->id, 'user_id' => $staff->id]);
                $item = $category->items()->first();
                $order = app(PlaceOrderAction::class)->execute($location->fresh(), ['customer_name' => 'Permission Browser Guest', 'customer_phone' => '0712345678', 'fulfillment' => 'pickup', 'items' => [['product_id' => $item->public_id, 'quantity' => 1]]], (string) Str::uuid())['order'];
                $account += ['staff_email' => $staff->email, 'staff_password' => $staffPassword, 'order' => $order->public_id];
            }
            if ($this->option('overview')) {
                $items = collect([$category->items()->first()]);
                for ($index = 2; $index <= 5; $index++) {
                    $items->push(MenuItem::create(['organization_id' => $location->organization_id, 'category_id' => $category->id, 'name' => 'Preparat statistic '.$index, 'price' => 2500]));
                }
                for ($index = 0; $index < 21; $index++) {
                    $item = $items[$index % 5];
                    $quantity = $index % 3 + 1;
                    $value = $quantity * 2500;
                    $order = Order::create(['organization_id' => $location->organization_id, 'location_id' => $location->id, 'tracking_token_hash' => hash('sha256', Str::random(64)), 'tracking_token_encrypted' => encrypt('local-overview-only'), 'fulfillment' => $index % 2 ? 'dine_in' : 'pickup', 'table_code' => $index % 2 ? 'T1' : null, 'table_area' => $index % 2 ? 'Salon' : null, 'customer_name' => 'Analytics test guest', 'customer_phone' => '0712345678', 'currency' => 'RON', 'subtotal' => $value, 'delivery_fee' => 0, 'total' => $value, 'status' => 'completed', 'created_at' => now()->subDays($index % 14)]);
                    OrderItem::create(['order_id' => $order->id, 'product_public_id' => $item->public_id, 'name' => $item->name, 'quantity' => $quantity, 'unit_price' => 2500, 'tax_basis_points' => 0, 'total' => $value]);
                }
            }
            if ($this->option('closed-window')) {
                $location->hours()->delete();
            }

            return $account;
        });
        $file = match (true) {
            (bool) $this->option('overview') => 'browser-overview.json',
            (bool) $this->option('closed-window') => 'browser-closed-window.json',
            (bool) $this->option('permissions') => 'browser-permissions.json',
            (bool) $this->option('invitation') => 'browser-invitation.json',
            (bool) $this->option('storefront') => 'browser-storefront.json',
            (bool) $this->option('services') => 'browser-services.json',
            (bool) $this->option('admin') => 'browser-email-admin.json',
            default => 'browser-account.json',
        };
        Storage::disk('local')->put($file, json_encode($account, JSON_THROW_ON_ERROR));
        $this->info('Private browser fixture saved. Existing accounts are unchanged.');

        return self::SUCCESS;
    }
}
