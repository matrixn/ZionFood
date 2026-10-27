<?php

namespace App\Http\Controllers;

use App\Domain\Menus\GloriaFoodContent;
use App\Domain\Menus\ManageMenuContent;
use App\Domain\Menus\MenuAi;
use App\Domain\Menus\MenuContent;
use App\Domain\Tenancy\TenantAccess;
use App\Models\MenuAiSetting;
use App\Models\MenuContentDraft;
use App\Models\MenuItem;
use App\Models\RestaurantLocation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class MenuContentController extends Controller
{
    private function location(Request $request, string $location, bool $write = false): RestaurantLocation
    {
        $context = RestaurantLocation::where('public_id', $location)->firstOrFail();
        app(TenantAccess::class)->requireFeature($request->user(), $context, 'menu', $write);

        return $context;
    }

    private function item(Request $request, RestaurantLocation $location, string $item): MenuItem
    {
        $model = MenuItem::where('public_id', $item)->firstOrFail();
        app(ManageMenuContent::class)->authorize($request->user(), $location, $model);

        return $model;
    }

    public function index(Request $request, string $location)
    {
        $context = $this->location($request, $location);
        $items = MenuItem::where('organization_id', $context->organization_id)->whereHas('category', fn ($q) => $q->whereIn('menu_id', $context->menus()->pluck('menus.id')))->orderBy('id')->get();
        $current = $request->query('item') ? $items->firstWhere('public_id', $request->query('item')) : $items->first();
        abort_if($request->query('item') && ! $current, 404);
        $schema = app(MenuContent::class);
        $canWrite = $current && app(TenantAccess::class)->canMenu($request->user(), $context->organization_id, $current->category->menu_id);

        return Inertia::render('restaurant/MenuContent', [
            ...app(WorkspaceController::class)->workspaceProps($request, $location, 'menu'),
            'items' => $items->map(fn ($item) => $item->only('public_id', 'name')),
            'current' => $current ? [...$current->only('public_id', 'name', 'description'), 'content' => $current->content ?? $schema->empty(), 'hash' => $schema->hash($current), 'translationHash' => $schema->translationHash($current), 'source' => $schema->source($current)] : null,
            'drafts' => $current ? MenuContentDraft::where('location_id', $context->id)->where('organization_id', $context->organization_id)->where('item_id', $current->id)->latest()->limit(30)->get() : [],
            'contentWritable' => (bool) $canWrite, 'languages' => config('menu-content.languages'), 'allergenCodes' => MenuContent::ALLERGENS, 'labelCodes' => MenuContent::LABELS, 'nutrientCodes' => MenuContent::NUTRIENTS, 'aiReady' => app(MenuAi::class)->ready(),
        ]);
    }

    public function save(Request $request, string $location, string $item)
    {
        $context = $this->location($request, $location, true);
        $model = $this->item($request, $context, $item);
        $data = $request->validate(['content' => ['required', 'array'], 'hash' => ['required', 'string', 'size:64']]);
        app(ManageMenuContent::class)->save($request->user(), $context, $model, $data['content'], $data['hash']);

        return response()->json(['saved' => true]);
    }

    public function generate(Request $request, string $location)
    {
        $context = $this->location($request, $location, true);
        $data = $request->validate(['item' => ['nullable', 'string'], 'kind' => ['required', Rule::in(['translate', 'suggest', 'rewrite', 'calculation'])], 'languages' => ['present', 'array', 'max:3'], 'languages.*' => ['string', 'distinct', Rule::in(array_keys(config('menu-content.languages')))]]);
        abort_if($data['kind'] === 'translate' && ! $data['languages'], 422, __('Select at least one language.'));
        abort_if(! ($data['item'] ?? null) && $data['kind'] !== 'translate', 422);
        $items = $data['item'] ?? null ? collect([$this->item($request, $context, $data['item'])]) : MenuItem::where('organization_id', $context->organization_id)->whereHas('category', fn ($q) => $q->whereIn('menu_id', $context->menus()->pluck('menus.id')))->get();
        abort_if($items->isEmpty() || $items->count() > 200, 422, __('Select a menu with between 1 and 200 products.'));
        $drafts = DB::transaction(function () use ($items, $request, $context, $data) {
            return $items->map(fn ($item) => app(ManageMenuContent::class)->draft($request->user(), $context, $item, $data['kind'], $data['kind'] === 'translate' ? $data['languages'] : []));
        });

        return response()->json(['queued' => $drafts->count()], 202);
    }

    public function import(Request $request, string $location, string $item)
    {
        $context = $this->location($request, $location, true);
        $model = $this->item($request, $context, $item);
        $data = $request->validate(['source_item' => ['required', 'array']]);
        abort_if(strlen(json_encode($data['source_item'], JSON_THROW_ON_ERROR)) > 131072, 422);
        try {
            $import = app(GloriaFoodContent::class)->read($data['source_item']);
        } catch (\TypeError|\ValueError $e) {
            abort(422, __('Invalid GloriaFood content.'));
        }
        app(ManageMenuContent::class)->draft($request->user(), $context, $model, 'gloriafood', [], $import);

        return response()->json(['staged' => true], 201);
    }

    public function review(Request $request, string $location, string $draft)
    {
        $context = $this->location($request, $location, true);
        $model = MenuContentDraft::where('public_id', $draft)->where('location_id', $context->id)->where('organization_id', $context->organization_id)->firstOrFail();
        $data = $request->validate(['action' => ['required', Rule::in(['approve', 'reject'])]]);
        if ($data['action'] === 'approve') {
            app(ManageMenuContent::class)->approve($request->user(), $context, $model);
        } else {
            app(ManageMenuContent::class)->authorize($request->user(), $context, MenuItem::findOrFail($model->item_id));
            abort_unless(MenuContentDraft::whereKey($model->id)->where('status', 'ready')->update(['status' => 'rejected', 'reviewed_by' => $request->user()->id, 'reviewed_at' => now()]), 409);
        }

        return response()->json(['reviewed' => true]);
    }

    public function aiSettings(Request $request)
    {
        abort_unless($request->user()->is_platform_admin, 403);
        $settings = MenuAiSetting::find(1);
        if ($request->isMethod('get')) {
            return Inertia::render('admin/MenuAi', ['settings' => $settings ? [...$settings->only('enabled', 'provider', 'model', 'daily_limit'), 'has_key' => filled($settings->api_key)] : ['enabled' => false, 'provider' => 'groq', 'model' => 'openai/gpt-oss-20b', 'daily_limit' => 100, 'has_key' => false]]);
        }
        $data = $request->validate(['enabled' => ['required', 'boolean'], 'provider' => ['required', Rule::in(['groq', 'openai'])], 'model' => ['required', 'string', 'max:100', 'regex:/^[a-zA-Z0-9._\/-]+$/'], 'api_key' => ['nullable', 'string', 'max:500', 'regex:/^[a-zA-Z0-9_\-]+$/'], 'clear_key' => ['required', 'boolean'], 'daily_limit' => ['required', 'integer', 'min:1', 'max:1000']]);
        abort_if($data['provider'] === 'groq' && ! in_array($data['model'], ['openai/gpt-oss-20b', 'openai/gpt-oss-120b'], true), 422, __('Choose a supported structured-output model.'));
        DB::transaction(function () use ($data) {
            $settings = MenuAiSetting::firstOrCreate(['id' => 1]);
            $settings = MenuAiSetting::whereKey(1)->lockForUpdate()->firstOrFail();
            if ($settings->provider !== $data['provider'] || $data['clear_key']) {
                $settings->api_key = null;
            }
            if (filled($data['api_key'] ?? null)) {
                $settings->api_key = $data['api_key'];
            }
            $settings->fill(array_intersect_key($data, array_flip(['enabled', 'provider', 'model', 'daily_limit'])));
            abort_if($settings->enabled && ! filled($settings->api_key), 422, __('Configure an API key before enabling menu AI.'));
            $settings->save();
        });

        return response()->json(['saved' => true]);
    }
}
