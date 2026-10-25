<?php

namespace App\Console\Commands;

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
    protected $signature = 'zionfood:browser-fixtures';

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
            $user->forceFill(['email_verified_at' => now(), 'locale' => 'ro'])->save();
            $location = app(OnboardOrganization::class)->execute($user, ['organization_name' => 'Browser Test '.$suffix, 'restaurant_name' => 'Restaurant Test', 'slug' => 'browser-'.$suffix, 'location_name' => 'Locație test', 'address' => 'Adresă de test']);
            $location->update(['accepting_orders' => true]);
            $location->hours()->update(['opens_at' => '00:00', 'closes_at' => '23:59']);
            $category = MenuCategory::create(['organization_id' => $location->organization_id, 'menu_id' => $location->menus()->first()->id, 'name' => 'Preparate']);
            MenuItem::create(['organization_id' => $location->organization_id, 'category_id' => $category->id, 'name' => 'Preparat de test', 'price' => 2500]);

            return ['email' => $user->email, 'password' => $password, 'location' => $location->public_id];
        });
        Storage::disk('local')->put('browser-account.json', json_encode($account, JSON_THROW_ON_ERROR));
        $this->info('Private browser fixture saved. Existing accounts are unchanged.');

        return self::SUCCESS;
    }
}
