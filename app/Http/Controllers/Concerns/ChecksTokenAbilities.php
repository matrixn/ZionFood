<?php

namespace App\Http\Controllers\Concerns;

use Illuminate\Http\Request;
use Laravel\Sanctum\TransientToken;

trait ChecksTokenAbilities
{
    private function ability(Request $request, string $ability): void
    {
        $token = $request->user()->currentAccessToken();
        if ($token && ! ($token instanceof TransientToken)) {
            abort_unless($request->user()->tokenCan($ability), 403);
        }
    }
}
