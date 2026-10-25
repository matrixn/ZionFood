<?php

namespace App\Console\Commands;

use App\Domain\Tenancy\Actions\OnboardOrganization;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\RestaurantLocation;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class MakeLoadFixtures extends Command
{
    protected $signature = 'zionfood:load-fixtures {--restaurants=100}';

    protected $description = 'Create isolated local benchmark restaurants and private k6 fixtures';

    public function handle(): int
    {
        if (! app()->environment('local') || ! str_ends_with((string) config('database.connections.mysql.database'), '_load_test')) {
            $this->error('Use a local MariaDB database ending in _load_test.');

            return self::FAILURE;
        }
        $count = (int) $this->option('restaurants');
        if ($count < 1 || $count > 1000) {
            $this->error('Choose 1 to 1000 restaurants.');

            return self::FAILURE;
        }
        $fixtures = [];
        foreach (range(1, $count) as $i) {
            $email = "load-{$i}@example.test";
            $user = User::where('email', $email)->first();
            if (! $user) {
                $user = User::create(['name' => "Load {$i}", 'email' => $email, 'password' => Str::password(64)]);
                $user->forceFill(['email_verified_at' => now()])->save();
                $location = app(OnboardOrganization::class)->execute($user, ['organization_name' => "Load {$i}", 'restaurant_name' => "Load {$i}", 'slug' => "load-{$i}", 'location_name' => 'Benchmark', 'address' => 'Test street']);
                $location->update(['accepting_orders' => true]);
                $location->hours()->update(['opens_at' => '00:00', 'closes_at' => '23:59']);
                $category = MenuCategory::create(['organization_id' => $location->organization_id, 'menu_id' => $location->menus()->first()->id, 'name' => 'Benchmark']);
                $item = MenuItem::create(['organization_id' => $location->organization_id, 'category_id' => $category->id, 'name' => 'Test product', 'price' => 2500]);
            } else {
                $location = RestaurantLocation::whereHas('restaurant', fn ($q) => $q->where('slug', "load-{$i}"))->firstOrFail();
                $item = MenuItem::where('organization_id', $location->organization_id)->firstOrFail();
                $user->tokens()->delete();
            }
            $token = $user->createToken('k6', ['orders:read'], now()->addHours(4));
            $token->accessToken->forceFill(['organization_id' => $location->organization_id])->save();
            $fixtures[] = ['location_id' => $location->public_id, 'product_id' => $item->public_id, 'kitchen_token' => $token->plainTextToken];
        }
        Storage::disk('local')->put('load-fixtures.json', json_encode($fixtures, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
        $this->info("Prepared {$count} restaurant fixtures in storage/app/private/load-fixtures.json.");

        return self::SUCCESS;
    }
}
