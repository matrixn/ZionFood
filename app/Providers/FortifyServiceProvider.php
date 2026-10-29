<?php

namespace App\Providers;

use App\Actions\Fortify\CreateNewUser;
use App\Actions\Fortify\ResetUserPassword;
use App\Domain\Auth\RegistrationCaptcha;
use App\Domain\Tenancy\PendingStaffInvitation;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Inertia\Inertia;
use Laravel\Fortify\Fortify;

class FortifyServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Fortify::createUsersUsing(CreateNewUser::class);
        Fortify::resetUserPasswordsUsing(ResetUserPassword::class);
        Fortify::loginView(fn () => Inertia::render('auth/Login'));
        Fortify::registerView(function (Request $request) {
            $pending = app(PendingStaffInvitation::class)->current($request);
            app(RegistrationCaptcha::class)->prepare($request);

            return Inertia::render('auth/Register', ['invitedEmail' => $pending['record']->email ?? null]);
        });
        Fortify::requestPasswordResetLinkView(fn () => Inertia::render('auth/ForgotPassword', ['status' => session('status')]));
        Fortify::resetPasswordView(fn (Request $r) => Inertia::render('auth/ResetPassword', ['token' => $r->route('token'), 'email' => $r->email]));
        Fortify::verifyEmailView(fn () => Inertia::render('auth/VerifyEmail', ['status' => session('status')]));
        RateLimiter::for('login', fn (Request $r) => Limit::perMinute(5)->by(strtolower((string) $r->input('email')).'|'.$r->ip()));
        RateLimiter::for('registration-captcha', fn (Request $r) => [Limit::perMinute(10)->by('captcha-minute:'.$r->ip()), Limit::perHour(40)->by('captcha-hour:'.$r->ip())]);
        RateLimiter::for('registration-submit', fn (Request $r) => [Limit::perMinute(10)->by('registration-minute:'.$r->ip()), Limit::perHour(60)->by('registration-hour:'.$r->ip())]);
    }
}
