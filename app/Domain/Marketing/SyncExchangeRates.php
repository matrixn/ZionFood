<?php

namespace App\Domain\Marketing;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class SyncExchangeRates
{
    public const ENDPOINT = 'https://api.frankfurter.dev/v2/rates';

    public function execute(bool $scheduled = false): bool
    {
        return (bool) Cache::lock('marketing-exchange-rates', 45)->get(function () use ($scheduled) {
            if ($scheduled && ! DB::table('marketing_pricing_settings')->where('id', 1)->value('weekly_enabled')) {
                return true;
            }
            DB::table('marketing_pricing_settings')->where('id', 1)->update(['last_attempt_at' => now()]);
            try {
                $response = Http::acceptJson()->connectTimeout(5)->timeout(15)->withOptions([
                    'verify' => true, 'allow_redirects' => false, 'proxy' => '',
                    'progress' => function ($total, $received) {
                        if ($received > 65536 || $total > 65536) {
                            throw new RuntimeException('rate_response_size');
                        }
                    },
                ])->get(self::ENDPOINT, ['base' => 'RON', 'quotes' => 'EUR,GBP']);
                if (! $response->successful() || strlen($response->body()) > 65536) {
                    throw new RuntimeException('rate_provider_response');
                }
                $rows = $response->json();
                if (! is_array($rows) || ! array_is_list($rows) || count($rows) !== 2) {
                    throw new RuntimeException('rate_schema');
                }
                $rates = [];
                foreach ($rows as $row) {
                    if (! is_array($row) || ($row['base'] ?? null) !== 'RON' || ! in_array($row['quote'] ?? '', ['EUR', 'GBP'], true) || isset($rates[$row['quote']]) || ! is_numeric($row['rate'] ?? null) || ! is_finite((float) $row['rate']) || $row['rate'] < 0.001 || $row['rate'] > 10 || ! preg_match('/^\d{4}-\d{2}-\d{2}$/D', $row['date'] ?? '')) {
                        throw new RuntimeException('rate_schema');
                    }
                    $date = CarbonImmutable::createFromFormat('!Y-m-d', $row['date'], 'UTC');
                    if (! $date || $date->format('Y-m-d') !== $row['date'] || $date->gt(now('UTC')->addDay()) || $date->lt(now('UTC')->subDays(14))) {
                        throw new RuntimeException('rate_date');
                    }
                    $rates[$row['quote']] = ['currency' => $row['quote'], 'rate_scaled' => (int) round($row['rate'] * PricingCatalog::SCALE), 'rate_date' => $row['date'], 'fetched_at' => now()];
                }
                DB::transaction(function () use ($rates) {
                    DB::table('marketing_exchange_rates')->upsert(array_values($rates), ['currency'], ['rate_scaled', 'rate_date', 'fetched_at']);
                    DB::table('marketing_pricing_settings')->where('id', 1)->update(['last_success_at' => now(), 'last_error' => null]);
                });
                Log::info('Marketing exchange rates updated', ['provider' => 'frankfurter', 'base' => 'RON', 'quotes' => array_keys($rates)]);

                return true;
            } catch (Throwable $e) {
                DB::table('marketing_pricing_settings')->where('id', 1)->update(['last_error' => 'provider_unavailable']);
                Log::warning('Marketing exchange rates update failed; previous rates retained', ['provider' => 'frankfurter', 'exception_class' => get_class($e)]);

                return false;
            }
        });
    }
}
