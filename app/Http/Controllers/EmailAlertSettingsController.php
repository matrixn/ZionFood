<?php

namespace App\Http\Controllers;

use App\Domain\Mail\ErrorAlertCircuit;
use Illuminate\Http\Request;
use Inertia\Inertia;

class EmailAlertSettingsController extends Controller
{
    public function show(Request $request, ErrorAlertCircuit $circuit)
    {
        abort_unless($request->user()->is_platform_admin, 403);

        return Inertia::render('admin/EmailAlerts', ['settings' => $circuit->status()]);
    }

    public function update(Request $request, ErrorAlertCircuit $circuit)
    {
        abort_unless($request->user()->is_platform_admin, 403);
        $data = $request->validate(['enabled' => 'required|boolean', 'resume' => 'required|boolean', 'version' => 'required|integer|min:1']);
        $circuit->configure($data['enabled'], $data['resume'], $data['version']);

        return response()->json(['settings' => $circuit->status()]);
    }
}
