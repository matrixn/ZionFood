<?php

namespace App\Domain\Imports;

use App\Domain\Menus\MenuCache;
use App\Domain\Menus\MenuWriteLock;
use App\Domain\Tenancy\TenantAccess;
use App\Models\ImportBatch;
use App\Models\Menu;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\ModifierGroup;
use App\Models\OutboxMessage;
use App\Models\RestaurantLocation;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PublishImport
{
    public function previous(ImportBatch $batch): ?object
    {
        return DB::table('import_publications as p')->join('import_batches as b', 'b.id', '=', 'p.batch_id')->where('b.location_id', $batch->location_id)->where('b.source_restaurant', $batch->source_restaurant)->whereNull('p.reverted_at')->orderByDesc('p.id')->select('p.*')->first();
    }

    public function conflicts(ImportBatch $batch): array
    {
        $previous = $this->previous($batch);
        if (! $previous) {
            return [];
        }$current = app(MenuGraph::class)->hashes(Menu::findOrFail($previous->menu_id));
        $baseline = json_decode($previous->product_hashes, true);
        $conflicts = [];
        foreach (array_unique([...array_keys($current), ...array_keys($baseline)]) as $key) {
            if (($current[$key] ?? null) !== ($baseline[$key] ?? null)) {
                $conflicts[] = (string) $key;
            }
        }

        return $conflicts;
    }

    public function execute(User $actor, ImportBatch $context, array $resolutions, bool $acknowledged, string $reviewHash): Menu
    {
        $createdPhotos = [];
        try {
            return $this->publish($actor, $context, $resolutions, $acknowledged, $reviewHash, $createdPhotos);
        } catch (\Throwable $e) {
            try {
                // An after-commit callback can fail after the menu is already durable.
                $referenced = MenuItem::withTrashed()->where('organization_id', $context->organization_id)->whereIn('image_path', $createdPhotos)->pluck('image_path')->all();
                Storage::disk('public')->delete(array_values(array_diff($createdPhotos, $referenced)));
            } catch (\Throwable $cleanup) {
                Log::warning('GloriaFood photo cleanup deferred', ['batch_id' => $context->public_id, 'exception_class' => $cleanup::class]);
            }
            throw $e;
        }
    }

    private function publish(User $actor, ImportBatch $context, array $resolutions, bool $acknowledged, string $reviewHash, array &$createdPhotos): Menu
    {
        return DB::transaction(function () use ($actor, $context, $resolutions, $acknowledged, $reviewHash, &$createdPhotos) {
            MenuWriteLock::organization($context->organization_id);
            $location = RestaurantLocation::whereKey($context->location_id)->lockForUpdate()->firstOrFail();
            app(TenantAccess::class)->requireManage($actor, $location->organization_id);
            app(TenantAccess::class)->requireFeature($actor, $location, 'menu', true);
            $batch = ImportBatch::whereKey($context->id)->lockForUpdate()->firstOrFail();
            abort_unless($batch->organization_id === $location->organization_id && $batch->status === 'ready', 409);
            if (! $acknowledged || DB::table('import_issues')->where('batch_id', $batch->id)->where('severity', 'blocker')->exists()) {
                throw ValidationException::withMessages(['import' => __('Resolve blocking issues and confirm the verification report before publication.')]);
            }
            abort_unless(hash_equals($this->reviewHash($batch), $reviewHash), 409, __('The menu changed. Refresh the migration preview.'));
            $canonical = app(ProcessImport::class)->canonical($batch);
            if (array_key_exists('reported_restaurant_id', $canonical)) {
                $identity = app(ResolveImportSource::class)->execute($batch, $canonical);
                abort_if($identity['conflict'] || $identity['namespace'] !== $canonical['source_restaurant'], 409, __('The import source changed. Start a new import to verify it safely.'));
            }
            abort_unless($canonical['currency'] === $location->currency, 422);
            $conflicts = $this->conflicts($batch);
            foreach ($conflicts as $key) {
                if (! in_array($resolutions[$key] ?? null, ['keep_local', 'use_source'], true)) {
                    throw ValidationException::withMessages(['resolutions' => __('Choose how to resolve each local edit.')]);
                }
            }
            $previous = $this->previous($batch);
            $oldItems = $previous ? MenuItem::withTrashed()->whereHas('category', fn ($q) => $q->where('menu_id', $previous->menu_id))->get() : collect();
            $previousIds = $location->menus()->pluck('menus.id')->all();
            $menu = Menu::create(['organization_id' => $location->organization_id, 'restaurant_id' => $location->restaurant_id, 'name' => 'GloriaFood Â· '.now()->format('Y-m-d H:i'), 'is_active' => false]);
            $namespace = 'gloriafood:'.$canonical['source_restaurant'];
            $seen = [];
            foreach ($canonical['categories'] as $position => $c) {
                $category = MenuCategory::create(['organization_id' => $location->organization_id, 'menu_id' => $menu->id, 'name' => $c['name'], 'sort_order' => $position]);
                $this->mapping($batch, $namespace, 'category', $c['key'], $category->id);
                $this->attachGroups($batch, $namespace, $category, $c['groups'], 'category:'.$c['key']);
                foreach ($c['items'] as $i) {
                    $key = (string) $i['key'];
                    $seen[] = $key;
                    $old = $oldItems->first(fn ($x) => (string) $x->source_key === $key);
                    if (in_array($key, $conflicts, true) && ($resolutions[$key] ?? null) === 'keep_local') {
                        if ($old) {
                            $this->copyProduct($batch, $namespace, $category, $old);
                        }

                        continue;
                    }
                    $imagePath = $old?->image_path;
                    if (! $imagePath && isset($i['photo'])) {
                        $imagePath = app(ImportPhotos::class)->publish($batch, $key, $i['photo'], $createdPhotos);
                    }
                    $item = MenuItem::create(['organization_id' => $location->organization_id, 'category_id' => $category->id, 'source_key' => $key, 'name' => $i['name'], 'description' => $i['description'], 'price' => $i['price'], 'order_types' => $i['order_types'], 'kitchen_name' => $i['kitchen_name'], 'content' => $i['content'], 'image_path' => $imagePath, 'tax_basis_points' => $batch->settings['tax_basis_points']]);
                    if ($old) {
                        $item->ingredients()->sync($old->ingredients->mapWithKeys(fn ($ing) => [$ing->id => ['organization_id' => $location->organization_id, 'position' => $ing->pivot->position]])->all());
                    }
                    $this->mapping($batch, $namespace, 'item', $key, $item->id);
                    $this->attachGroups($batch, $namespace, $item, $i['groups'], 'item:'.$key);
                    foreach ($i['variants'] as $v) {
                        $variant = $item->variants()->create(array_intersect_key($v, array_flip(['name', 'price', 'is_default', 'nutrition'])));
                        $this->mapping($batch, $namespace, 'variant', $key.':'.$v['key'], $variant->id);
                        $this->attachGroups($batch, $namespace, $variant, $v['groups'], 'size:'.$key.':'.$v['key']);
                    }
                }
            }
            // Locally added or source-removed products require an explicit decision and retain their complete graph.
            foreach ($oldItems as $old) {
                $key = $old->source_key ?? ('local:'.$old->public_id);
                if (in_array((string) $key, $seen, true)) {
                    continue;
                }
                if (($resolutions[$key] ?? 'keep_local') === 'use_source') {
                    continue;
                }
                $category = $menu->categories()->firstOrCreate(['name' => $old->category->name], ['organization_id' => $location->organization_id]);
                $this->copyProduct($batch, $namespace, $category, $old);
            }
            $menu->update(['is_active' => true]);
            $location->menus()->sync([$menu->id => ['organization_id' => $location->organization_id]]);
            DB::table('import_publications')->insert(['batch_id' => $batch->id, 'menu_id' => $menu->id, 'previous_menu_ids' => json_encode($previousIds), 'product_hashes' => json_encode(app(MenuGraph::class)->hashes($menu)), 'published_hash' => app(MenuGraph::class)->fingerprint($menu), 'approved_by' => $actor->id, 'created_at' => now(), 'updated_at' => now()]);
            $batch->update(['status' => 'published']);
            $this->signal($location);
            DB::afterCommit(fn () => MenuCache::forgetOrganization($location->organization_id));

            return $menu;
        }, 3);
    }

    public function reviewHash(ImportBatch $batch): string
    {
        $previous = $this->previous($batch);
        $location = RestaurantLocation::findOrFail($batch->location_id);

        return hash('sha256', json_encode([$location->currency, $location->menus()->orderBy('menus.id')->get()->map(fn ($m) => [$m->id, app(MenuGraph::class)->fingerprint($m)])->all(), $batch->fresh()->settings, DB::table('import_snapshots')->where('batch_id', $batch->id)->value('canonical'), $previous?->id, $previous ? app(MenuGraph::class)->fingerprint(Menu::findOrFail($previous->menu_id)) : null], JSON_THROW_ON_ERROR));
    }

    public function revert(User $actor, ImportBatch $context): void
    {
        DB::transaction(function () use ($actor, $context) {
            MenuWriteLock::organization($context->organization_id);
            $location = RestaurantLocation::whereKey($context->location_id)->lockForUpdate()->firstOrFail();
            app(TenantAccess::class)->requireManage($actor, $location->organization_id);
            app(TenantAccess::class)->requireFeature($actor, $location, 'menu', true);
            $batch = ImportBatch::whereKey($context->id)->lockForUpdate()->firstOrFail();
            $p = DB::table('import_publications')->where('batch_id', $batch->id)->first();
            abort_unless($p && $batch->status === 'published' && ! $p->reverted_at, 409);
            abort_unless($location->menus()->pluck('menus.id')->all() === [$p->menu_id] && hash_equals($p->published_hash, app(MenuGraph::class)->fingerprint(Menu::findOrFail($p->menu_id))), 409, __('The published menu changed; automatic rollback would discard local edits.'));
            $location->menus()->sync(collect(json_decode($p->previous_menu_ids, true))->mapWithKeys(fn ($id) => [$id => ['organization_id' => $location->organization_id]])->all());
            DB::table('import_publications')->where('id', $p->id)->update(['reverted_at' => now(), 'updated_at' => now()]);
            $batch->update(['status' => 'reverted']);
            $this->signal($location);
            DB::afterCommit(fn () => MenuCache::forgetOrganization($location->organization_id));
        }, 3);
    }

    private function signal(RestaurantLocation $location): void
    {
        OutboxMessage::create(['event_id' => (string) Str::ulid(), 'organization_id' => $location->organization_id, 'location_id' => $location->id, 'type' => 'menu.updated', 'payload' => ['location_id' => $location->public_id]]);
    }

    private function mapping($batch, string $namespace, string $type, string $external, int $local): void
    {
        DB::table('import_entity_mappings')->insert(['batch_id' => $batch->id, 'location_id' => $batch->location_id, 'namespace' => $namespace, 'entity_type' => $type, 'external_id' => $external, 'local_id' => $local]);
    }

    private function attachGroups($batch, string $namespace, $parent, array $groups, string $path): void
    {
        foreach ($groups as $g) {
            $group = ModifierGroup::create(['organization_id' => $batch->organization_id, ...array_intersect_key($g, array_flip(['name', 'min_choices', 'max_choices', 'is_required', 'allow_quantity']))]);
            $parent->modifierGroups()->attach($group->id, ['organization_id' => $batch->organization_id]);
            $key = $path.':'.$g['key'];
            $this->mapping($batch, $namespace, 'group', $key, $group->id);
            foreach ($g['options'] as $o) {
                $option = $group->options()->create(array_intersect_key($o, array_flip(['name', 'price', 'is_default', 'kitchen_name'])));
                $this->mapping($batch, $namespace, 'option', $key.':'.$o['key'], $option->id);
            }
        }
    }

    private function copyProduct($batch, string $namespace, $category, MenuItem $old): void
    {
        $graph = app(MenuGraph::class)->product($old);
        $category = MenuCategory::where('menu_id', $category->menu_id)->where('name', $old->category->name)->whereDoesntHave('modifierGroups')->first() ?? MenuCategory::create(['organization_id' => $batch->organization_id, 'menu_id' => $category->menu_id, 'name' => $old->category->name]);
        $copy = $old->replicate(['public_id']);
        $copy->category_id = $category->id;
        $copy->save();
        $this->mapping($batch, $namespace, 'item', $copy->source_key ?? ('local:'.$old->public_id), $copy->id);
        $copy->ingredients()->sync($old->ingredients->mapWithKeys(fn ($i) => [$i->id => ['organization_id' => $batch->organization_id, 'position' => $i->pivot->position]])->all());
        $clonedGroups = [];
        $cloneGroups = function ($parent, $groups) use ($batch, &$clonedGroups, $namespace, $old) {
            foreach ($groups as $g) {
                if (isset($clonedGroups[$g['source_id']])) {
                    $parent->modifierGroups()->syncWithoutDetaching([$clonedGroups[$g['source_id']] => ['organization_id' => $batch->organization_id]]);

                    continue;
                }
                $group = ModifierGroup::create(['organization_id' => $batch->organization_id, ...$g['fields']]);
                $parent->modifierGroups()->attach($group->id, ['organization_id' => $batch->organization_id]);
                $path = 'preserved:'.($old->source_key ?? $old->public_id).':'.$g['source_id'];
                $this->mapping($batch, $namespace, 'group', $path, $group->id);
                foreach ($g['options'] as $index => $o) {
                    $option = $group->options()->create($o);
                    $this->mapping($batch, $namespace, 'option', $path.':'.$index, $option->id);
                }
                $clonedGroups[$g['source_id']] = $group->id;
            }
        };
        // Flatten inherited category groups onto a preserved product to retain local rules independently.
        $cloneGroups($copy, [...$graph['groups'], ...$graph['category_groups']]);
        foreach ($graph['variants'] as $v) {
            $variant = $copy->variants()->create($v['fields']);
            $cloneGroups($variant, $v['groups']);
        }
    }
}
