<?php

namespace App\Domain\Marketing;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class PricingCatalog
{
    public const CURRENCIES = ['RON', 'EUR', 'GBP'];

    public const SCALE = 100000000;

    public function defaults(): array
    {
        return json_decode(file_get_contents(resource_path('data/pricing.json')), true, flags: JSON_THROW_ON_ERROR);
    }

    public function publicData(string $requested = 'RON'): array
    {
        $ready = Schema::hasTable('marketing_plans');
        $records = $ready ? DB::table('marketing_plans')->get()->keyBy('key') : collect();
        $rates = $ready ? DB::table('marketing_exchange_rates')->get()->keyBy('currency') : collect();
        $available = ['RON', ...$rates->keys()->filter(fn ($code) => in_array($code, ['EUR', 'GBP'], true))->all()];
        $currency = in_array($requested, $available, true) ? $requested : 'RON';
        $rate = $currency === 'RON' ? self::SCALE : (int) $rates[$currency]->rate_scaled;
        $plans = array_map(function ($plan) use ($records, $rate) {
            $row = $records->get($plan['key']);
            $base = (int) ($row->price_minor ?? $plan['price_minor']);

            return [...$plan, 'name' => $row->name ?? $plan['name'], 'price_minor' => $base,
                // Prices and invoice authority stay in RON minor units; never round-trip displayed quotes.
                'display_minor' => intdiv($base * $rate + intdiv(self::SCALE, 2), self::SCALE)];
        }, $this->defaults());

        return ['base_currency' => 'RON', 'currency' => $currency, 'requested_currency' => $requested,
            'available' => $available, 'plans' => $plans, 'rate_date' => $currency === 'RON' ? null : $rates[$currency]->rate_date,
            'stale' => $currency !== 'RON' && $rates[$currency]->rate_date < now()->subDays(14)->toDateString(),
            'rate_scaled' => $rate];
    }

    public function adminData(): array
    {
        return ['plans' => $this->publicData()['plans'], 'settings' => DB::table('marketing_pricing_settings')->find(1),
            'rates' => DB::table('marketing_exchange_rates')->orderBy('currency')->get()];
    }

    public function update(User $actor, array $data): void
    {
        abort_unless($actor->is_platform_admin, 403);
        DB::transaction(function () use ($actor, $data) {
            $settings = DB::table('marketing_pricing_settings')->where('id', 1)->lockForUpdate()->first();
            if ($settings->version !== $data['version']) {
                throw ValidationException::withMessages(['version' => __('Pricing settings changed. Reload before saving.')]);
            }
            foreach ($data['plans'] as $plan) {
                [$whole, $fraction] = array_pad(explode('.', $plan['price_ron']), 2, '');
                $minor = (int) $whole * 100 + (int) str_pad($fraction, 2, '0');
                DB::table('marketing_plans')->where('key', $plan['key'])->update(['name' => trim($plan['name']), 'price_minor' => $minor, 'updated_at' => now()]);
            }
            DB::table('marketing_pricing_settings')->where('id', 1)->update(['weekly_enabled' => $data['weekly_enabled'], 'version' => $settings->version + 1, 'updated_by' => $actor->id, 'updated_at' => now()]);
        });
    }
}
