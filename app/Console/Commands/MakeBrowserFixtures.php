<?php

namespace App\Console\Commands;

use App\Domain\Restaurants\SyncDiningTables;
use App\Domain\Tenancy\Actions\OnboardOrganization;
use App\Models\Ingredient;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\ModifierGroup;
use App\Models\StaffInvitation;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class MakeBrowserFixtures extends Command
{
    protected $signature = 'zionfood:browser-fixtures {--admin : Create a separate local platform-admin browser fixture} {--services : Prepare a separate restaurant for reservation and QR browser tests} {--storefront : Prepare a photo menu and polygon for storefront UX tests} {--invitation : Prepare a separate unaccepted staff invitation without sending email}';

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
            if ($this->option('services')) {
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

            return $account;
        });
        Storage::disk('local')->put($this->option('invitation') ? 'browser-invitation.json' : ($this->option('storefront') ? 'browser-storefront.json' : ($this->option('services') ? 'browser-services.json' : ($this->option('admin') ? 'browser-email-admin.json' : 'browser-account.json'))), json_encode($account, JSON_THROW_ON_ERROR));
        $this->info('Private browser fixture saved. Existing accounts are unchanged.');

        return self::SUCCESS;
    }
}
