<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class LocaleController extends Controller
{
    public function update(Request $request)
    {
        $data = $request->validate(['locale' => ['required', 'in:ro,en']]);
        if ($request->user()) {
            $request->user()->forceFill(['locale' => $data['locale']])->save();
        }
        app()->setLocale($data['locale']);

        return response()->json(['locale' => $data['locale']])->cookie('zf_locale', $data['locale'], 525600, '/', null, $request->isSecure(), true, false, 'lax')->header('Cache-Control', 'no-store');
    }
}
