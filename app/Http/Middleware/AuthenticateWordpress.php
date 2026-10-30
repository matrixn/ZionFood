<?php

namespace App\Http\Middleware;

use App\Domain\Integrations\WordpressAccess;
use Closure;
use Illuminate\Http\Request;

class AuthenticateWordpress
{
    public function handle(Request $request, Closure $next)
    {
        abort_unless($request->isSecure() || (app()->environment(['local', 'testing']) && in_array($request->getHost(), ['localhost', '127.0.0.1'], true)), 400, __('HTTPS is required.'));
        app(WordpressAccess::class)->authenticate($request);

        return $next($request);
    }
}
