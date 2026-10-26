<?php

namespace App\Console\Commands;

use App\Domain\Restaurants\SyncDiningTables;
use App\Domain\Tenancy\Actions\OnboardOrganization;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class MakeBrowserFixtures extends Command
{
    protected $signature = 'zionfood:browser-fixtures {--admin : Create a separate local platform-admin browser fixture} {--services : Prepare a separate restaurant for reservation and QR browser tests}';

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
            if ($this->option('services')) {
                $profile = ['basics' => ['city' => 'Râmnicu Vâlcea', 'phone' => '0712345678', 'latitude' => 45.1, 'longitude' => 24.36], 'seating' => ['mode' => 'tables', 'capacity' => 8, 'tables' => [['code' => 'T1', 'area' => 'Salon', 'seats' => 4], ['code' => 'T2', 'area' => 'Terasă', 'seats' => 4]]], 'services' => ['qr_requires_staff' => false], 'reservations' => ['planned' => true, 'confirmation' => 'automatic', 'duration_minutes' => 60, 'interval_minutes' => 30, 'minimum_notice_minutes' => 0, 'advance_days' => 30, 'min_party_size' => 1, 'max_party_size' => 4, 'cancellation_hours' => 0, 'hold_minutes' => 15]];
                $location->update(['setup_profile' => $profile]);
                $location->fulfillment()->update(['reservations_enabled' => true, 'dine_in_enabled' => true]);
                app(SyncDiningTables::class)->execute($location, $profile['seating']);
            }

            return ['email' => $user->email, 'password' => $password, 'location' => $location->public_id];
        });
        Storage::disk('local')->put($this->option('services') ? 'browser-services.json' : ($this->option('admin') ? 'browser-email-admin.json' : 'browser-account.json'), json_encode($account, JSON_THROW_ON_ERROR));
        $this->info('Private browser fixture saved. Existing accounts are unchanged.');

        return self::SUCCESS;
    }
}
