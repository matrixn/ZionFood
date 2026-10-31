<?php

namespace App\Domain\Marketing\Actions;

use App\Models\CampaignVisit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

class RecordCampaignVisit
{
    public function execute(Request $request, string $locale): void
    {
        if ($request->header('DNT') === '1' || $request->header('Sec-GPC') === '1') {
            $request->session()->forget('marketing.campaign_visit');

            return;
        }
        if (! $request->isMethod('GET') || $request->attributes->has('storefront_location') || ! in_array($request->getHost(), config('zionfood.app_hosts'), true)
            || ! $request->is('/', 'en') || $request->user()?->is_platform_admin
            || preg_match('/bot|crawler|spider|preview|facebookexternalhit|WhatsApp/i', $request->userAgent() ?? '')
            || str_contains(strtolower($request->header('Sec-Purpose', '').' '.$request->header('Purpose', '')), 'prefetch')) {
            return;
        }
        if (! array_intersect(array_keys($request->query()), CampaignVisit::FIELDS)) {
            return;
        }
        // A new tagged entry must never fall back to an older campaign when recording fails.
        $request->session()->forget('marketing.campaign_visit');
        $tags = [];
        foreach (CampaignVisit::FIELDS as $field) {
            $value = $request->query($field);
            if ($value !== null && (! is_string($value) || ! mb_check_encoding($value, 'UTF-8') || mb_strlen($value) > 200 || preg_match('/[\x00-\x1F\x7F<>]/u', $value))) {
                return;
            }
            $tags[$field] = filled($value) ? trim($value) : null;
        }
        if (! array_filter($tags, fn ($value) => $value !== null)) {
            return;
        }
        foreach (['utm_source', 'utm_medium'] as $field) {
            $tags[$field] = $tags[$field] === null ? null : mb_strtolower($tags[$field]);
        }
        $entryKey = hash('sha256', json_encode($tags, JSON_THROW_ON_ERROR));
        $campaignKey = hash('sha256', json_encode(array_intersect_key($tags, array_flip(CampaignVisit::GROUP_FIELDS)), JSON_THROW_ON_ERROR));
        $sessionKey = hash_hmac('sha256', $request->session()->getId(), config('app.key'));
        $key = 'campaign-visit:'.$sessionKey.':'.$entryKey;
        try {
            $visit = Cache::lock('campaign-lock:'.$sessionKey, 5)->get(function () use ($key, $tags, $campaignKey, $request, $locale) {
                if ($id = Cache::get($key)) {
                    $existing = CampaignVisit::where('public_id', $id)->where('campaign_key', $campaignKey)->where('created_at', '>', now()->subMinutes(30))->first();
                    if ($existing) {
                        return $existing;
                    }
                }
                $ipKey = 'campaign-admission:'.hash_hmac('sha256', (string) $request->ip(), config('app.key'));
                if (RateLimiter::tooManyAttempts($ipKey, 60)) {
                    return null;
                }
                RateLimiter::hit($ipKey, 60);
                $visit = CampaignVisit::create([...$tags, 'campaign_key' => $campaignKey, 'landing_path' => $request->is('en') ? '/en' : '/', 'locale' => $locale]);
                Cache::put($key, $visit->public_id, now()->addMinutes(30));

                return $visit;
            });
            if ($visit instanceof CampaignVisit) {
                $request->session()->put('marketing.campaign_visit', ['public_id' => $visit->public_id, 'expires_at' => $visit->created_at->copy()->addMinutes(30)->timestamp]);
            }
        } catch (\Throwable $error) {
            // Analytics availability must not break the public landing or contact form.
            Log::warning('Campaign visit unavailable', ['exception_class' => $error::class]);
        }
    }

    public function attribution(Request $request): ?CampaignVisit
    {
        if ($request->header('DNT') === '1' || $request->header('Sec-GPC') === '1') {
            $request->session()->forget('marketing.campaign_visit');

            return null;
        }
        $context = $request->session()->get('marketing.campaign_visit');
        if (! is_array($context) || ! is_string($context['public_id'] ?? null) || ! Str::isUlid($context['public_id']) || ! is_int($context['expires_at'] ?? null) || $context['expires_at'] <= now()->timestamp) {
            return null;
        }

        try {
            return CampaignVisit::where('public_id', $context['public_id'] ?? '')->where('created_at', '>', now()->subMinutes(30))->first();
        } catch (\Throwable $error) {
            $request->session()->forget('marketing.campaign_visit');
            Log::warning('Campaign attribution unavailable', ['exception_class' => $error::class]);

            return null;
        }
    }
}
