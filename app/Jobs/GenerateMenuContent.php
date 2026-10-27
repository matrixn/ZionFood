<?php

namespace App\Jobs;

use App\Domain\Menus\ManageMenuContent;
use App\Domain\Menus\MenuAi;
use App\Domain\Menus\MenuContent;
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
                $draft->update(['status' => 'stale']);

                return;
            }
            $result = app(MenuAi::class)->generate($draft->input['source'], $draft->input['languages'], $draft->kind);
            $patch = match ($draft->kind) {
                'translate' => ['translations' => $result['translations'] ?? []],
                'rewrite' => ['description' => $result['description'] ?? ''],
                default => array_intersect_key($result, array_flip(['allergens', 'may_contain', 'labels'])),
            };
            if ($draft->kind === 'suggest') {
                foreach (['allergens', 'may_contain', 'labels'] as $field) {
                    $patch[$field] = array_values(array_unique(array_merge($draft->input['content'][$field] ?? [], $patch[$field] ?? [])));
                }
            }
            Validator::make($patch, ['description' => ['sometimes', 'required', 'string', 'max:2000']])->validate();
            if ($draft->kind === 'translate' && (array_diff(array_keys($patch['translations']), $draft->input['languages']) || array_diff($draft->input['languages'], array_keys($patch['translations'])))) {
                throw new \RuntimeException('ai_invalid_languages');
            }
            app(MenuContent::class)->validate(array_replace(app(MenuContent::class)->empty(), array_diff_key($patch, ['description' => true])));
            $draft->update(['status' => 'ready', 'proposal' => $patch, 'report' => ['notes' => mb_substr((string) ($result['notes'] ?? ''), 0, 3000), 'provider' => MenuAiSetting::find(1)?->provider, 'nutrition' => 'recipe_calculation_only']]);
        } catch (Throwable $e) {
            $this->failed($e);
        }
    }

    public function failed(?Throwable $exception): void
    {
        $code = $exception instanceof \RuntimeException && in_array($exception->getMessage(), ['ai_not_configured', 'ai_rate_limited', 'ai_incomplete', 'ai_provider_failed'], true) ? $exception->getMessage() : 'generation_failed';
        MenuContentDraft::whereKey($this->draftId)->whereIn('status', ['queued', 'processing'])->update(['status' => 'failed', 'error_code' => $code]);
        Log::warning('menu_content.generation_failed', ['draft_id' => $this->draftId, 'error_code' => $code, 'exception_class' => $exception ? get_class($exception) : null]);
    }
}
