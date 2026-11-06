<?php

namespace App\Domain\Localization;

use App\Domain\Marketing\PricingCatalog;
use Illuminate\Http\Request;

class VisitorPreferences
{
    public function manual(Request $request, string $name, array $allowed): ?string
    {
        $value = $request->cookie('zf_'.$name);

        // Pre-0.0.55 cookies cannot distinguish automatic choices: conservatively preserve them.
        return in_array($value, $allowed, true) && $request->cookie('zf_'.$name.'_source') !== 'ip' ? $value : null;
    }

    public function resolve(Request $request): array
    {
        $manualLocale = $this->manual($request, 'locale', UiLocale::CODES);
        $accountLocale = $request->user()?->locale;
        $manualLocale ??= in_array($accountLocale, UiLocale::CODES, true) ? $accountLocale : null;
        $manualCurrency = $this->manual($request, 'currency', PricingCatalog::CURRENCIES);
        $country = $manualLocale && $manualCurrency ? null : app(GeoIpDatabase::class)->country($request->ip());
        $defaults = self::forCountry($country);

        return ['locale' => $manualLocale ?? $defaults['locale'], 'currency' => $manualCurrency ?? $defaults['currency'],
            'locale_source' => $manualLocale ? 'manual' : 'ip', 'currency_source' => $manualCurrency ? 'manual' : 'ip', 'country' => $country];
    }

    public static function forCountry(?string $country): array
    {
        return match ($country) {
            'RO' => ['locale' => 'ro', 'currency' => 'RON'],
            'IT' => ['locale' => 'it', 'currency' => 'EUR'],
            'ES' => ['locale' => 'es', 'currency' => 'EUR'],
            'NL' => ['locale' => 'nl', 'currency' => 'EUR'],
            'GB' => ['locale' => 'en', 'currency' => 'GBP'],
            default => ['locale' => 'en', 'currency' => 'EUR'],
        };
    }
}
