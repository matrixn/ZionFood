<?php

namespace App\Http\Controllers;

use App\Domain\Restaurants\Actions\ManageCuisine;
use App\Http\Requests\CuisineRequest;
use App\Models\Cuisine;
use Illuminate\Http\Request;
use Inertia\Inertia;

class CuisineController extends Controller
{
    public function index(Request $request)
    {
        abort_unless($request->user()->is_platform_admin, 403);

        return Inertia::render('admin/Cuisines', ['cuisines' => Cuisine::orderBy('sort_order')->orderBy('id')->get()]);
    }

    public function save(CuisineRequest $request, ManageCuisine $action, ?string $cuisine = null)
    {
        $action->execute($request->user(), $cuisine ? Cuisine::findOrFail($cuisine) : null, $request->validated());

        return back()->with('success', __('Cuisine saved.'));
    }
}
