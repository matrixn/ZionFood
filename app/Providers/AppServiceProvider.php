<?php

namespace App\Providers;

use App\Domain\Mail\EmailErrorAlert;
use App\Domain\Mail\QueueOutgoingEmail;
use App\Domain\Menus\MenuCache;
use App\Models\Menu;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\ModifierGroup;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Mail\Events\MessageSending;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(EmailErrorAlert::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::define('platform-admin', fn ($user) => (bool) $user->is_platform_admin);
        RateLimiter::for('admin-mail-test', fn () => Limit::perHour(3)->by('admin-mail-test'));
        Event::listen(MessageSending::class, [QueueOutgoingEmail::class, 'handle']);
        Event::listen(MessageLogged::class, [EmailErrorAlert::class, 'handle']);
        RateLimiter::for('staff-invitations', fn (Request $r) => Limit::perHour(30)->by((string) $r->user()?->id));
        RateLimiter::for('webhook-retry', fn (Request $r) => Limit::perMinute(10)->by((string) $r->user()?->id));
        foreach ([Menu::class, MenuCategory::class, MenuItem::class, ModifierGroup::class] as $model) {
            $invalidate = fn ($record) => DB::afterCommit(fn () => MenuCache::forgetOrganization($record->organization_id));
            $model::saved($invalidate);
            $model::deleted($invalidate);
        }
        RateLimiter::for('checkout', fn (Request $r) => [Limit::perMinute(config('zionfood.checkout_ip_limit'))->by($r->ip()), Limit::perMinute(120)->by((string) $r->route('location'))]);
        RateLimiter::for('tracking', fn (Request $r) => Limit::perMinute(60)->by($r->ip()));
    }
}
