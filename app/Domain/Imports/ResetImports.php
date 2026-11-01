<?php

namespace App\Domain\Imports;

use App\Domain\Menus\MenuCache;
use App\Domain\Menus\MenuWriteLock;
use App\Domain\Tenancy\TenantAccess;
use App\Models\ImportBatch;
use App\Models\Menu;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\OutboxMessage;
use App\Models\Restaurant;
use App\Models\RestaurantLocation;
use App\Models\User;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ResetImports
{
    private function authorize(User $actor, RestaurantLocation $location): void
    {
        app(TenantAccess::class)->requireManage($actor, $location->organization_id);
        app(TenantAccess::class)->requireFeature($actor, $location, 'menu', true);
    }

    private function plan(RestaurantLocation $location): array
    {
        $batches = ImportBatch::where('organization_id', $location->organization_id)->where('location_id', $location->id)->whereNull('reset_id')->orderBy('id')->get();
        $publications = DB::table('import_publications')->whereIn('batch_id', $batches->pluck('id'))->orderByDesc('id')->get();
        $menuIds = $publications->pluck('menu_id')->all();
        abort_if(Menu::whereIn('id', $menuIds)->where(fn ($q) => $q->where('organization_id', '!=', $location->organization_id)->orWhere('restaurant_id', '!=', $location->restaurant_id))->exists(), 409);
        $mapped = DB::table('import_entity_mappings')->whereIn('batch_id', $batches->pluck('id'))->where('entity_type', 'item')->pluck('local_id');
        $items = MenuItem::withTrashed()->where('organization_id', $location->organization_id)->whereNotNull('source_key')->where(fn ($q) => $q->whereIn('id', $mapped)->orWhereHas('category', fn ($c) => $c->whereIn('menu_id', $menuIds)))->orderBy('id')->get();
        $affectedMenus = array_unique([...$menuIds, ...DB::table('menu_categories')->whereIn('id', $items->pluck('category_id'))->pluck('menu_id')->all()]);
        $shared = DB::table('location_menus as lm')->join('restaurant_locations as l', 'l.id', '=', 'lm.location_id')->whereIn('lm.menu_id', $affectedMenus)->where('lm.location_id', '!=', $location->id)->distinct()->orderBy('l.id')->get(['l.id', 'l.name']);
        $active = $location->menus()->orderBy('menus.id')->pluck('menus.id')->all();
        $restored = [];
        $seen = [];
        $walk = function (int $id) use (&$walk, &$restored, &$seen, $publications, $menuIds) {
            if (isset($seen[$id])) {
                return;
            }
            $seen[$id] = true;
            if (! in_array($id, $menuIds, true)) {
                $restored[] = $id;

                return;
            }
            foreach (json_decode($publications->firstWhere('menu_id', $id)->previous_menu_ids, true) as $previous) {
                $walk((int) $previous);
            }
        };
        foreach ($active as $id) {
            $walk($id);
        }
        $restored = Menu::where('organization_id', $location->organization_id)->where('restaurant_id', $location->restaurant_id)->whereIn('id', $restored)->orderBy('id')->pluck('id')->all();
        $localItems = MenuItem::withTrashed()->where('organization_id', $location->organization_id)->whereNull('source_key')->whereHas('category', fn ($q) => $q->whereIn('menu_id', array_intersect($active, $menuIds)))->orderBy('id')->get();
        $settings = app(ImportRestaurantSettings::class)->resetPlan($location, $publications->whereNull('reverted_at')->all());
        // Hash the current graph without loading per-product relations. Concurrent edits invalidate confirmation.
        $state = [$batches->map->only(['id', 'status', 'updated_at'])->all(), $publications, $active, $settings];
        foreach (['menus', 'menu_categories', 'menu_items', 'modifier_groups', 'menu_item_modifier_groups', 'menu_category_modifier_groups', 'menu_variant_modifier_groups', 'menu_item_ingredients'] as $table) {
            $query = DB::table($table)->where('organization_id', $location->organization_id);
            $state[] = $table === 'menu_item_ingredients' ? $query->orderBy('item_id')->orderBy('ingredient_id')->get() : $query->orderBy('id')->get();
        }
        $state[] = DB::table('menu_item_variants')->whereIn('item_id', DB::table('menu_items')->where('organization_id', $location->organization_id)->select('id'))->orderBy('id')->get();
        $state[] = DB::table('modifier_options')->whereIn('group_id', DB::table('modifier_groups')->where('organization_id', $location->organization_id)->select('id'))->orderBy('id')->get();
        $state[] = $shared;

        return compact('batches', 'publications', 'menuIds', 'items', 'shared', 'restored', 'localItems', 'settings') + ['review_hash' => hash('sha256', json_encode($state, JSON_THROW_ON_ERROR))];
    }

    public function preview(User $actor, RestaurantLocation $location): array
    {
        $this->authorize($actor, $location);

        $plan = $this->plan($location);

        return $this->summary($plan) + ['can_restore_settings' => ! array_intersect($plan['settings']['fields'], ['hours', 'zones']) || app(TenantAccess::class)->canFeature($actor, $location, 'orders', true)];
    }

    private function summary(array $plan): array
    {
        return ['review_hash' => $plan['review_hash'], 'batches' => $plan['batches']->count(), 'products' => $plan['items']->count(), 'menus' => count($plan['menuIds']), 'local_products' => $plan['localItems']->count(), 'shared_locations' => $plan['shared']->pluck('name')->all(), 'settings_fields' => $plan['settings']['fields'], 'preserved_settings' => $plan['settings']['preserved']];
    }

    public function execute(User $actor, RestaurantLocation $context, string $reviewHash, bool $restoreSettings): array
    {
        $this->authorize($actor, $context);

        return DB::transaction(function () use ($actor, $context, $reviewHash, $restoreSettings) {
            MenuWriteLock::organization($context->organization_id);
            $location = RestaurantLocation::whereKey($context->id)->lockForUpdate()->firstOrFail();
            Restaurant::whereKey($location->restaurant_id)->lockForUpdate()->firstOrFail();
            ImportBatch::where('organization_id', $location->organization_id)->where('location_id', $location->id)->orderBy('id')->lockForUpdate()->get();
            $plan = $this->plan($location);
            abort_unless(hash_equals($plan['review_hash'], $reviewHash), 409, __('The restaurant or menu changed. Refresh the migration preview.'));
            abort_unless($plan['batches']->isNotEmpty(), 409, __('There are no imports to reset.'));
            abort_unless($plan['shared']->isEmpty(), 409, __('Imported menus are still used by another location. Remove the shared assignment before resetting.'));
            if ($restoreSettings) {
                foreach ($plan['settings']['steps'] as $step) {
                    app(ImportRestaurantSettings::class)->revert($actor, $location, Crypt::encryptString(json_encode($step, JSON_THROW_ON_ERROR)));
                }
            }
            $summary = $this->summary($plan) + ['restore_settings' => $restoreSettings, 'batch_statuses' => $plan['batches']->map->only(['public_id', 'status'])->all()];
            $resetId = DB::table('import_resets')->insertGetId(['public_id' => (string) Str::ulid(), 'organization_id' => $location->organization_id, 'location_id' => $location->id, 'user_id' => $actor->id, 'summary' => json_encode($summary, JSON_THROW_ON_ERROR), 'created_at' => now(), 'updated_at' => now()]);
            $restored = $plan['restored'];
            if (! $restored) {
                $restored[] = Menu::create(['organization_id' => $location->organization_id, 'restaurant_id' => $location->restaurant_id, 'name' => __('Restaurant menu'), 'is_active' => true])->id;
            }
            $mappedCategories = DB::table('import_entity_mappings')->whereIn('batch_id', $plan['batches']->pluck('id'))->where('entity_type', 'category')->pluck('local_id');
            MenuCategory::where('organization_id', $location->organization_id)->whereIn('menu_id', array_intersect($location->menus()->pluck('menus.id')->all(), $plan['menuIds']))->whereNotIn('id', $mappedCategories)->update(['menu_id' => $restored[0]]);
            // Move only visible, locally-created products; earlier archived copies stay archived.
            foreach ($plan['localItems'] as $item) {
                $oldCategory = $item->category;
                $category = MenuCategory::firstOrCreate(['menu_id' => $restored[0], 'name' => $oldCategory->name], ['organization_id' => $location->organization_id, 'sort_order' => $oldCategory->sort_order]);
                $item->modifierGroups()->syncWithoutDetaching($oldCategory->modifierGroups->mapWithKeys(fn ($g) => [$g->id => ['organization_id' => $location->organization_id]])->all());
                $item->update(['category_id' => $category->id]);
            }
            $ids = $plan['items']->pluck('id');
            $photos = $plan['items']->pluck('image_path')->filter()->unique()->all();
            foreach ($plan['settings']['steps'] as $step) {
                if ($path = $step['after']['logo']['value'] ?? null) {
                    $photos[] = $path;
                }
            }
            DB::table('menu_content_drafts')->whereIn('item_id', $ids)->delete();
            DB::table('menu_item_ingredients')->whereIn('item_id', $ids)->delete();
            DB::table('menu_item_modifier_groups')->whereIn('item_id', $ids)->delete();
            DB::table('menu_variant_modifier_groups')->whereIn('variant_id', DB::table('menu_item_variants')->whereIn('item_id', $ids)->select('id'))->delete();
            DB::table('menu_item_variants')->whereIn('item_id', $ids)->delete();
            DB::table('menu_items')->whereIn('id', $ids)->delete();
            $categories = MenuCategory::where('organization_id', $location->organization_id)->whereIn('menu_id', $plan['menuIds'])->whereIn('id', $mappedCategories)->whereDoesntHave('items', fn ($q) => $q->withTrashed())->pluck('id');
            DB::table('menu_category_modifier_groups')->whereIn('category_id', $categories)->delete();
            DB::table('menu_categories')->whereIn('id', $categories)->delete();
            $groups = DB::table('import_entity_mappings')->whereIn('batch_id', $plan['batches']->pluck('id'))->where('entity_type', 'group')->pluck('local_id');
            $unused = DB::table('modifier_groups')->where('organization_id', $location->organization_id)->whereIn('id', $groups);
            foreach (['menu_item_modifier_groups', 'menu_category_modifier_groups', 'menu_variant_modifier_groups'] as $pivot) {
                $unused->whereNotIn('id', DB::table($pivot)->select('group_id'));
            }
            $unusedIds = $unused->pluck('id');
            DB::table('modifier_options')->whereIn('group_id', $unusedIds)->delete();
            DB::table('modifier_groups')->whereIn('id', $unusedIds)->delete();
            Menu::where('organization_id', $location->organization_id)->where('restaurant_id', $location->restaurant_id)->whereIn('id', $plan['menuIds'])->update(['is_active' => false]);
            $location->menus()->sync(collect($restored)->mapWithKeys(fn ($id) => [$id => ['organization_id' => $location->organization_id]])->all());
            ImportBatch::whereIn('id', $plan['batches']->pluck('id'))->update(['status' => 'reset', 'reset_id' => $resetId, 'credential' => null]);
            OutboxMessage::create(['event_id' => (string) Str::ulid(), 'organization_id' => $location->organization_id, 'location_id' => $location->id, 'type' => 'menu.updated', 'payload' => ['location_id' => $location->public_id]]);
            DB::afterCommit(function () use ($location, $photos, $plan) {
                MenuCache::forgetOrganization($location->organization_id);
                foreach ($photos as $path) {
                    // Delete only unreferenced files in this location's imported product namespaces.
                    $owned = $plan['batches']->contains(fn ($b) => str_starts_with($path, 'menu/'.$location->organization_id.'/imports/'.$b->public_id.'/') || str_starts_with($path, 'branding/'.$location->organization_id.'/'.$location->restaurant->public_id.'/imports/'.$b->public_id.'/'));
                    if (! $owned || MenuItem::withTrashed()->where('image_path', $path)->exists() || Restaurant::where('logo_path', $path)->exists()) {
                        continue;
                    }
                    try {
                        if (! Storage::disk('public')->delete($path)) {
                            throw new \RuntimeException('import_reset_media_cleanup');
                        }
                    } catch (\Throwable $e) {
                        Log::warning('Import reset media cleanup deferred', ['location_id' => $location->public_id, 'exception_class' => $e::class]);
                    }
                }
            });

            return $summary;
        }, 3);
    }
}
