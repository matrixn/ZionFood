<?php

namespace App\Providers;

use App\Domain\Menus\MenuCache;
use App\Models\Menu;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\ModifierGroup;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        foreach ([Menu::class, MenuCategory::class, MenuItem::class, ModifierGroup::class] as $model) {
            $invalidate = fn ($record) => DB::afterCommit(fn () => MenuCache::forgetOrganization($record->organization_id));
            $model::saved($invalidate);
            $model::deleted($invalidate);
        }
        RateLimiter::for('checkout', fn (Request $r) => [Limit::perMinute(config('zionfood.checkout_ip_limit'))->by($r->ip()), Limit::perMinute(120)->by((string) $r->route('location'))]);
        RateLimiter::for('tracking', fn (Request $r) => Limit::perMinute(60)->by($r->ip()));
    }
}
