<?php

use App\Http\Middleware\AccountRequestAdmission;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\SearchIndexing;
use App\Http\Middleware\SetLocale;
use App\Http\Middleware\TrustedHost;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        channels: __DIR__.'/../routes/channels.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->append(TrustedHost::class);
        $middleware->web(append: [SearchIndexing::class, SetLocale::class, HandleInertiaRequests::class, AccountRequestAdmission::class]);
        $middleware->api(append: [SearchIndexing::class, SetLocale::class]);
        $middleware->statefulApi();
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->dontFlash(['secret', 'token', 'qr_token', 'table_session_token', 'api_key', 'key', 'captcha_proof', 'captcha_answer']);
    })->create();
