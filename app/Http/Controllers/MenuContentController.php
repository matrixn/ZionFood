<?php

namespace App\Http\Controllers;

use App\Domain\Menus\ManageMenuContent;
use App\Domain\Menus\MenuAi;
use App\Domain\Menus\MenuContent;
use App\Domain\Menus\NutritionEstimate;
use App\Domain\Tenancy\TenantAccess;
use App\Models\MenuAiSetting;
use App\Models\MenuContentDraft;
use App\Models\MenuItem;
use App\Models\RestaurantLocation;
use App\Models\WorkspaceNotification;
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

    private function item(Request $request, RestaurantLocation $location, string $item, bool $write = true): MenuItem
    {
        $model = MenuItem::where('public_id', $item)->firstOrFail();
        app(ManageMenuContent::class)->authorize($request->user(), $location, $model, $write);

        return $model;
    }

    private function current(Request $request, RestaurantLocation $location, MenuItem $item): array
    {
        $schema = app(MenuContent::class);
        $item->loadMissing('variants', 'modifierGroups.options', 'ingredients', 'category.menu');

        return [...$item->only('public_id', 'name', 'description', 'image_path', 'portion_size'),
            'product' => $item, 'content' => $item->content ?? $schema->empty(), 'hash' => $schema->hash($item),
            'nutrition_inputs' => app(NutritionEstimate::class)->inputs($item),
            'writable' => app(TenantAccess::class)->canMenu($request->user(), $location->organization_id, $item->category->menu_id),
            'drafts' => MenuContentDraft::where('location_id', $location->id)->where('item_id', $item->id)->latest()->limit(30)->get(['public_id', 'kind', 'status', 'created_at'])];
    }

    public function index(Request $request, string $location)
    {
        $context = $this->location($request, $location);
        $items = MenuItem::where('organization_id', $context->organization_id)->whereHas('category', fn ($q) => $q->whereIn('menu_id', $context->menus()->pluck('menus.id')))->with('category')->orderBy('id')->get();
        $current = $request->query('item') ? $items->firstWhere('public_id', $request->query('item')) : $items->first();
        abort_if($request->query('item') && ! $current, 404);

        return Inertia::render('restaurant/MenuContent', [
            ...app(WorkspaceController::class)->workspaceProps($request, $location, 'menu'),
            'items' => $items->map(fn ($item) => [...$item->only('public_id', 'name', 'image_path', 'portion_size'), 'category' => $item->category->name, 'has_nutrition' => isset($item->content['nutrition']), 'translation_count' => count($item->content['translations'] ?? [])]),
            'current' => $current ? $this->current($request, $context, $current) : null,
            'languages' => config('menu-content.languages'), 'aiReady' => app(MenuAi::class)->ready(),
        ]);
    }

    public function itemData(Request $request, string $location, string $item)
    {
        $context = $this->location($request, $location);

        return response()->json($this->current($request, $context, $this->item($request, $context, $item, false)));
    }

    public function detail(Request $request, string $location, string $draft)
    {
        $context = $this->location($request, $location);
        $model = MenuContentDraft::where('public_id', $draft)->where('location_id', $context->id)->where('organization_id', $context->organization_id)->firstOrFail();
        $item = MenuItem::findOrFail($model->item_id);
        app(ManageMenuContent::class)->authorize($request->user(), $context, $item, false);

        return response()->json([...$model->only('public_id', 'kind', 'status', 'proposal', 'report', 'error_code', 'created_at'),
            'item_name' => $item->name, 'location' => $context->public_id, 'item' => $item->public_id,
            'source_current' => hash_equals($model->source_hash, app(MenuContent::class)->hash($item)),
            'can_write' => app(TenantAccess::class)->canMenu($request->user(), $context->organization_id, $item->category->menu_id)]);
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
        $data = $request->validate(['item' => ['nullable', 'string'], 'kind' => ['required', Rule::in(['translate', 'suggest', 'rewrite', 'nutrition', 'analyze'])], 'options' => ['sometimes', 'array', 'max:4'], 'options.*' => ['string', 'distinct', Rule::in(['nutrition', 'food', 'rewrite', 'translate'])], 'languages' => ['present', 'array', 'max:3'], 'languages.*' => ['string', 'distinct', Rule::in(array_keys(config('menu-content.languages')))]]);
        abort_if($data['kind'] === 'translate' && ! $data['languages'], 422, __('Select at least one language.'));
        abort_if(! ($data['item'] ?? null) && $data['kind'] !== 'translate', 422);
        $items = $data['item'] ?? null ? collect([$this->item($request, $context, $data['item'])]) : MenuItem::where('organization_id', $context->organization_id)->whereHas('category', fn ($q) => $q->whereIn('menu_id', $context->menus()->pluck('menus.id')))->get();
        abort_if($items->isEmpty() || $items->count() > 200, 422, __('Select a menu with between 1 and 200 products.'));
        $drafts = DB::transaction(function () use ($items, $request, $context, $data) {
            return $items->map(fn ($item) => app(ManageMenuContent::class)->draft($request->user(), $context, $item, $data['kind'], $data['languages'], $data['options'] ?? []));
        });

        return response()->json(['queued' => $drafts->count()], 202);
    }

    public function review(Request $request, string $location, string $draft)
    {
        $context = $this->location($request, $location, true);
        $model = MenuContentDraft::where('public_id', $draft)->where('location_id', $context->id)->where('organization_id', $context->organization_id)->firstOrFail();
        $data = $request->validate(['action' => ['required', Rule::in(['approve', 'reject'])], 'changes' => ['sometimes', 'array:allergens,may_contain,labels,description,translations'], 'sections' => ['sometimes', 'array', 'min:1'], 'sections.*' => ['string', 'distinct']]);
        if ($data['action'] === 'approve') {
            app(ManageMenuContent::class)->approve($request->user(), $context, $model, $data['sections'] ?? null, $data['changes'] ?? []);
        } else {
            app(ManageMenuContent::class)->authorize($request->user(), $context, MenuItem::findOrFail($model->item_id));
            abort_unless(MenuContentDraft::whereKey($model->id)->where('status', 'ready')->update(['status' => 'rejected', 'reviewed_by' => $request->user()->id, 'reviewed_at' => now()]), 409);
        }

        WorkspaceNotification::where('user_id', $request->user()->id)->where('draft_id', $model->id)->update(['read_at' => now()]);

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
