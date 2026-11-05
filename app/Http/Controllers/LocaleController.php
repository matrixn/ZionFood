<?php

namespace App\Http\Controllers;

use App\Domain\Localization\UiLocale;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class LocaleController extends Controller
{
    public function update(Request $request)
    {
        $scope = $request->validate(['scope' => ['sometimes', 'in:application,admin,storefront']])['scope'] ?? 'application';
        abort_if($scope === 'admin' && ! $request->user()?->is_platform_admin, 403);
        $allowed = $scope === 'application' ? UiLocale::CODES : UiLocale::ADMIN;
        $data = $request->validate(['locale' => ['required', Rule::in($allowed)]]);
        if ($request->user() && $scope !== 'admin') {
            $request->user()->forceFill(['locale' => $data['locale']])->save();
        }
        app()->setLocale($data['locale']);

        return response()->json(['locale' => $data['locale']])->cookie($scope === 'admin' ? 'zf_admin_locale' : 'zf_locale', $data['locale'], 525600, '/', null, $request->isSecure(), true, false, 'lax')->header('Cache-Control', 'no-store');
    }
}
