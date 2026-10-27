<?php

namespace App\Domain\Menus;

use App\Domain\Tenancy\TenantAccess;
use App\Jobs\GenerateMenuContent;
use App\Models\MenuAiSetting;
use App\Models\MenuContentDraft;
use App\Models\MenuItem;
use App\Models\Organization;
use App\Models\RestaurantLocation;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ManageMenuContent
{
    public function authorize(User $actor, RestaurantLocation $location, MenuItem $item, bool $write = true): void
    {
        app(TenantAccess::class)->requireFeature($actor, $location, 'menu', $write);
        abort_unless($item->organization_id === $location->organization_id && $location->menus()->where('menus.id', $item->category->menu_id)->exists(), 404);
        app(TenantAccess::class)->requireMenu($actor, $location->organization_id, $item->category->menu_id, $write);
    }

    public function save(User $actor, RestaurantLocation $location, MenuItem $context, array $data, string $hash): MenuItem
    {
        $this->authorize($actor, $location, $context);

        return DB::transaction(function () use ($actor, $location, $context, $data, $hash) {
            $item = MenuItem::whereKey($context->id)->lockForUpdate()->firstOrFail();
            $this->authorize($actor, $location, $item);
            $schema = app(MenuContent::class);
            abort_unless(hash_equals($schema->hash($item), $hash), 409, __('The menu changed. Refresh and review the latest content.'));
            $content = $schema->validate($data);
            $old = $item->content ?? $schema->empty();
            if (($content['nutrition_inputs'] ?? null) !== ($old['nutrition_inputs'] ?? null)) {
                $content['nutrition'] = null;
                if (isset($content['nutrition_inputs']['portion_grams'])) {
                    $item->update(['portion_size' => rtrim(rtrim(number_format((float) $content['nutrition_inputs']['portion_grams'], 3, '.', ''), '0'), '.').' g']);
                }
            }
            if ([$content['recipe'], $content['yield_grams'] ?? null, $content['servings'] ?? null] !== [$old['recipe'] ?? [], $old['yield_grams'] ?? null, $old['servings'] ?? null] && ($content['nutrition'] ?? null) === ($old['nutrition'] ?? null)) {
                $content['nutrition'] = null;
            }
            foreach ($content['translations'] as $language => &$row) {
                $previous = $item->content['translations'][$language] ?? [];
                $clean = fn ($value) => array_diff_key($value, ['source_hash' => true]);
                $row['source_hash'] = $clean($row) === $clean($previous) ? ($previous['source_hash'] ?? $schema->translationHash($item)) : $schema->translationHash($item);
            }
            unset($row);
            $item->update(['content' => $content, 'content_revision' => $item->content_revision + 1]);
            app(RecordMenuChange::class)->execute($item->organization_id, $item->category->menu_id, ['item_id' => $item->public_id]);

            return $item;
        }, 3);
    }

    public function draft(User $actor, RestaurantLocation $location, MenuItem $item, string $kind, array $languages = [], array $options = []): MenuContentDraft
    {
        Validator::make(['kind' => $kind, 'languages' => $languages, 'options' => $options], ['kind' => ['required', Rule::in(['translate', 'suggest', 'rewrite', 'nutrition', 'analyze'])], 'languages' => ['array', 'max:3'], 'languages.*' => ['string', 'distinct', Rule::in(array_keys(config('menu-content.languages')))], 'options' => ['array', 'max:4'], 'options.*' => ['string', 'distinct', Rule::in(['nutrition', 'food', 'rewrite', 'translate'])]])->validate();
        abort_if($kind === 'translate' && ! $languages, 422);
        abort_if($kind === 'analyze' && ! $options, 422);
        abort_if(in_array('translate', $options, true) && ! $languages, 422);
        $this->authorize($actor, $location, $item);
        $schema = app(MenuContent::class);

        return DB::transaction(function () use ($actor, $location, $item, $kind, $languages, $options, $schema) {
            // Serialize the platform-wide budget, including batches from different tenants.
            MenuAiSetting::whereKey(1)->lockForUpdate()->first();
            Organization::whereKey($location->organization_id)->lockForUpdate()->firstOrFail();
            abort_unless(app(MenuAi::class)->ready(), 422, __('Menu AI is not configured by the platform administrator.'));
            $limit = MenuAiSetting::findOrFail(1)->daily_limit;
            // Global provider budget shared by every restaurant; no per-tenant bypass.
            if (MenuContentDraft::whereIn('kind', ['translate', 'suggest', 'rewrite', 'nutrition', 'analyze'])->where('created_at', '>=', now()->subDay())->count() >= $limit) {
                throw ValidationException::withMessages(['ai' => __('The platform AI daily limit has been reached.')]);
            }
            $hash = $schema->hash($item);
            $nutrition = app(NutritionEstimate::class)->inputs($item);
            if ($kind === 'nutrition' || in_array('nutrition', $options, true)) {
                $nutrition = app(NutritionEstimate::class)->requireInputs($nutrition);
            }
            $input = ['source' => [...$schema->source($item), 'nutrition_context' => $nutrition], 'content' => $item->content ?? $schema->empty(), 'languages' => $languages, 'options' => $options];
            abort_if(strlen(json_encode($input['source'], JSON_THROW_ON_ERROR)) > 16000, 422, __('This product is too large for the configured AI request budget.'));
            $draft = MenuContentDraft::create(['organization_id' => $location->organization_id, 'location_id' => $location->id, 'item_id' => $item->id, 'requested_by' => $actor->id, 'kind' => $kind, 'source_hash' => $hash, 'input' => $input, 'status' => 'queued']);
            GenerateMenuContent::dispatch($draft->id)->afterCommit();

            return $draft;
        }, 3);
    }

    public function approve(User $actor, RestaurantLocation $location, MenuContentDraft $context, ?array $sections = null, array $changes = []): void
    {
        DB::transaction(function () use ($actor, $location, $context, $sections, $changes) {
            $draft = MenuContentDraft::whereKey($context->id)->lockForUpdate()->firstOrFail();
            abort_unless($draft->location_id === $location->id && $draft->organization_id === $location->organization_id && $draft->status === 'ready', 409);
            $item = MenuItem::whereKey($draft->item_id)->lockForUpdate()->firstOrFail();
            $this->authorize($actor, $location, $item);
            $schema = app(MenuContent::class);
            abort_unless(hash_equals($draft->source_hash, $schema->hash($item)), 409, __('The menu changed. Refresh and review the latest content.'));
            $proposal = $draft->proposal;
            abort_if(array_diff(array_keys($changes), array_intersect(array_keys($proposal), ['allergens', 'may_contain', 'labels', 'description', 'translations'])), 422);
            $proposal = array_replace($proposal, $changes);
            Validator::make($proposal, ['description' => ['sometimes', 'required', 'string', 'max:2000']])->validate();
            if ($sections !== null) {
                Validator::make(['sections' => $sections], ['sections' => ['required', 'array', 'min:1'], 'sections.*' => ['string', 'distinct', Rule::in(array_keys($proposal))]])->validate();
                $proposal = array_intersect_key($proposal, array_flip($sections));
            }
            if (isset($proposal['description'])) {
                $item->update(['description' => $proposal['description']]);
                unset($proposal['description']);
            }
            $content = array_replace($item->content ?? $schema->empty(), $proposal);
            if (isset($proposal['translations'])) {
                $content['translations'] = array_replace($item->content['translations'] ?? [], $proposal['translations']);
                foreach (array_keys($proposal['translations']) as $language) {
                    $content['translations'][$language]['source_hash'] = $schema->translationHash($item);
                }
            }
            $item->update(['content' => $schema->validate($content), 'content_revision' => $item->content_revision + 1]);
            $draft->update(['status' => 'approved', 'reviewed_by' => $actor->id, 'reviewed_at' => now(),
                'proposal' => array_replace($draft->proposal, $changes),
                'report' => [...($draft->report ?? []), 'approved_sections' => $sections ?? array_keys($draft->proposal), 'review_changes' => $changes],
            ]);
            app(RecordMenuChange::class)->execute($item->organization_id, $item->category->menu_id, ['item_id' => $item->public_id]);
        }, 3);
    }
}
