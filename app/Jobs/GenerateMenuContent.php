<?php

namespace App\Jobs;

use App\Domain\Menus\CompleteMenuContent;
use App\Domain\Menus\ManageMenuContent;
use App\Domain\Menus\MenuAi;
use App\Domain\Menus\MenuContent;
use App\Domain\Menus\NutritionEstimate;
use App\Models\MenuAiSetting;
use App\Models\MenuContentDraft;
use App\Models\MenuItem;
use App\Models\RestaurantLocation;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\RateLimited;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Throwable;

class GenerateMenuContent implements ShouldQueue
{
    use Queueable;

    public int $tries = 0;

    public int $timeout = 50;

    public function __construct(public int $draftId) {}

    public function middleware(): array
    {
        return [new RateLimited('menu-ai-provider')];
    }

    public function retryUntil(): \DateTimeInterface
    {
        return now()->addDay();
    }

    public function handle(): void
    {
        $draft = MenuContentDraft::find($this->draftId);
        if (! $draft) {
            return;
        }
        if (! MenuContentDraft::whereKey($draft->id)->where('status', 'queued')->update(['status' => 'processing'])) {
            // Recover an interrupted attempt without silently issuing a second billed request.
            if ($draft->status === 'processing' && $draft->updated_at->lt(now()->subMinute())) {
                $this->failed(new \RuntimeException('ai_interrupted'));
            }

            return;
        }
        try {
            $item = MenuItem::findOrFail($draft->item_id);
            app(ManageMenuContent::class)->authorize(User::findOrFail($draft->requested_by), RestaurantLocation::findOrFail($draft->location_id), $item);
            if (! hash_equals($draft->source_hash, app(MenuContent::class)->hash($item))) {
                app(CompleteMenuContent::class)->finish($draft->id, 'stale');

                return;
            }
            $result = app(MenuAi::class)->generate($draft->input['source'], $draft->input['languages'], $draft->kind, $draft->input['options'] ?? []);
            $selection = match ($draft->kind) {
                'analyze' => $draft->input['options'], 'translate' => ['translate'], 'rewrite' => ['rewrite'], 'nutrition' => ['nutrition'], default => ['food']
            };
            $required = [];
            foreach (['translate' => ['translations'], 'rewrite' => ['description'], 'food' => ['allergens', 'may_contain', 'labels'], 'nutrition' => ['nutrition']] as $operation => $keys) {
                if (in_array($operation, $selection, true)) {
                    $required = array_merge($required, $keys);
                }
            }
            foreach ($required as $key) {
                if (! array_key_exists($key, $result)) {
                    throw new \RuntimeException('ai_invalid_output');
                }
            }
            $patch = match ($draft->kind) {
                'translate' => ['translations' => $result['translations'] ?? []],
                'rewrite' => ['description' => $result['description'] ?? ''],
                'nutrition' => [],
                'analyze' => array_intersect_key($result, array_flip(array_merge(
                    in_array('food', $draft->input['options'], true) ? ['allergens', 'may_contain', 'labels'] : [],
                    in_array('rewrite', $draft->input['options'], true) ? ['description'] : [],
                    in_array('translate', $draft->input['options'], true) ? ['translations'] : []
                ))),
                default => array_intersect_key($result, array_flip(['allergens', 'may_contain', 'labels'])),
            };
            if ($draft->kind === 'nutrition' || in_array('nutrition', $draft->input['options'] ?? [], true)) {
                $patch['nutrition'] = app(NutritionEstimate::class)->fromOutput($result['nutrition'] ?? [], $draft->input['source']['nutrition_context']);
            }
            if ($draft->kind === 'suggest' || in_array('food', $draft->input['options'] ?? [], true)) {
                foreach (['allergens', 'may_contain', 'labels'] as $field) {
                    $patch[$field] = array_values(array_unique(array_merge($draft->input['content'][$field] ?? [], $patch[$field] ?? [])));
                }
            }
            Validator::make($patch, ['description' => ['sometimes', 'required', 'string', 'max:2000']])->validate();
            if (isset($patch['translations']) && (array_diff(array_keys($patch['translations']), $draft->input['languages']) || array_diff($draft->input['languages'], array_keys($patch['translations'])))) {
                throw new \RuntimeException('ai_invalid_languages');
            }
            if (isset($patch['translations'])) {
                // Sparse wire schemas omit empty maps; keep the stored/editor contract stable.
                $emptyGroups = collect($draft->input['source']['groups'])->filter(fn ($group) => ! $group['options'])->pluck('id')->map(fn ($id) => (string) $id);
                foreach ($patch['translations'] as &$translation) {
                    $translation += ['variants' => [], 'groups' => []];
                    foreach ($translation['groups'] as $id => &$group) {
                        if ($emptyGroups->contains((string) $id)) {
                            $group += ['options' => []];
                        }
                    }
                    unset($group);
                }
                unset($translation);
            }
            app(MenuContent::class)->validate(array_replace(app(MenuContent::class)->empty(), array_diff_key($patch, ['description' => true])));
            app(CompleteMenuContent::class)->finish($draft->id, 'ready', ['proposal' => $patch, 'report' => ['notes' => mb_substr((string) ($result['notes'] ?? ''), 0, 3000), 'provider' => MenuAiSetting::find(1)?->provider, 'nutrition' => isset($patch['nutrition']) ? 'ai_estimate' : null]]);
        } catch (Throwable $e) {
            $this->failed($e);
        }
    }

    public function failed(?Throwable $exception): void
    {
        $code = $exception instanceof \RuntimeException && in_array($exception->getMessage(), ['ai_not_configured', 'ai_rate_limited', 'ai_incomplete', 'ai_provider_failed'], true) ? $exception->getMessage() : 'generation_failed';
        app(CompleteMenuContent::class)->finish($this->draftId, 'failed', ['error_code' => $code]);
        Log::warning('menu_content.generation_failed', ['draft_id' => $this->draftId, 'error_code' => $code, 'exception_class' => $exception ? get_class($exception) : null]);
    }
}
