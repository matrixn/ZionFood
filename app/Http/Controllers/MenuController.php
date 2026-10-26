<?php

namespace App\Http\Controllers;

use App\Domain\Menus\Actions\UpdateMenuItem;
use App\Domain\Menus\RecordMenuChange;
use App\Domain\Tenancy\TenantAccess;
use App\Http\Requests\MenuItemRequest;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\RestaurantLocation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class MenuController extends Controller
{
    use Concerns\ChecksTokenAbilities;

    public function show(Request $request, string $location)
    {
        $this->ability($request, 'menu:read');
        $context = RestaurantLocation::where('public_id', $location)->firstOrFail();
        app(TenantAccess::class)->requireOperate($request->user(), $context);

        return response()->json(['menus' => $context->menus()->with('categories.items.ingredients', 'categories.items.variants', 'categories.items.modifierGroups.options')->get()]);
    }

    public function store(MenuItemRequest $request, string $location, UpdateMenuItem $action)
    {
        $this->ability($request, 'menu:write');
        $context = RestaurantLocation::where('public_id', $location)->firstOrFail();
        app(TenantAccess::class)->requireManage($request->user(), $context->organization_id);
        abort_unless(MenuCategory::whereKey($request->integer('category_id'))->whereIn('menu_id', $context->menus()->pluck('menus.id'))->exists(), 422);

        return response()->json($this->save($request, $action, $context->organization_id), 201);
    }

    public function update(MenuItemRequest $request, string $item, UpdateMenuItem $action)
    {
        $this->ability($request, 'menu:write');
        $model = MenuItem::where('public_id', $item)->firstOrFail();
        app(TenantAccess::class)->requireManage($request->user(), $model->organization_id);

        return response()->json($this->save($request, $action, $model->organization_id, $model));
    }

    private function save(MenuItemRequest $request, UpdateMenuItem $action, int $organizationId, ?MenuItem $item = null): MenuItem
    {
        $data = $request->validated();
        unset($data['image']);
        $path = null;
        if ($request->hasFile('image')) {
            $path = $request->file('image')->store('menu/'.$organizationId, 'public');
            $data['image_path'] = $path;
        }
        try {
            return $action->execute($request->user(), $organizationId, $data, $item);
        } catch (\Throwable $e) {
            if ($path) {
                Storage::disk('public')->delete($path);
            } throw $e;
        }
    }

    public function destroy(Request $request, string $item)
    {
        $model = MenuItem::where('public_id', $item)->firstOrFail();
        app(TenantAccess::class)->requireManage($request->user(), $model->organization_id);
        DB::transaction(function () use ($model) {
            $model->delete();
            app(RecordMenuChange::class)->execute($model->organization_id, $model->category->menu_id, ['item_id' => $model->public_id, 'deleted' => true]);
        });

        return response()->noContent();
    }
}
