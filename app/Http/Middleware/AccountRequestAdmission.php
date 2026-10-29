<?php

namespace App\Http\Middleware;

use App\Domain\Mail\AccountMailBudget;
use Closure;
use Illuminate\Http\Request;

class AccountRequestAdmission
{
    public function handle(Request $request, Closure $next)
    {
        if ($request->isMethod('POST') && $request->routeIs('register.store', 'password.email', 'verification.send')) {
            $sending = $request->routeIs('verification.send') ? $request->user() && ! $request->user()->hasVerifiedEmail() : ! $request->user();
            if ($sending && ($wait = app(AccountMailBudget::class)->admitRequest((string) $request->ip())) > 0) {
                abort(429, __('Too many account email requests. Please try again later.'), ['Retry-After' => $wait]);
            }
        }

        return $next($request);
    }
}
