import defaults from '../../data/pricing.json';
import type { Locale } from '@/i18n/locales';

export type Currency = 'RON' | 'EUR' | 'GBP';
export interface PricingPlan {
    key: string;
    name: string;
    price_minor: number;
    display_minor: number;
    icon: string;
    features: string[];
}
export interface PricingData {
    base_currency: 'RON';
    currency: Currency;
    requested_currency: string;
    available: string[];
    plans: PricingPlan[];
    rate_date: string | null;
    stale: boolean;
    rate_scaled: number;
}
export const defaultPricing: PricingData = {
    base_currency: 'RON',
    currency: 'RON',
    requested_currency: 'RON',
    available: ['RON'],
    plans: defaults.map((plan) => ({ ...plan, display_minor: plan.price_minor })),
    rate_date: null,
    stale: false,
    rate_scaled: 100000000,
};
// Approximate country preferences, never physical/GPS location. Explicit choices always win.
export function visitorPreferences(
    languages: readonly string[],
    timezone: string,
): { locale: Locale; currency: Currency } {
    const zones: Record<string, string> = {
        'Europe/Bucharest': 'RO',
        'Europe/Rome': 'IT',
        'Europe/Madrid': 'ES',
        'Atlantic/Canary': 'ES',
        'Europe/Amsterdam': 'NL',
        'Europe/London': 'GB',
        'Europe/Belfast': 'GB',
    };
    const regional = languages
        .map((language) => language.match(/^[a-z]{2,3}[-_]([a-z]{2})(?:$|[-_])/i)?.[1]?.toUpperCase())
        .find(Boolean);
    const country = zones[timezone] ?? regional;
    const countries: Record<string, { locale: Locale; currency: Currency }> = {
        RO: { locale: 'ro', currency: 'RON' },
        IT: { locale: 'it', currency: 'EUR' },
        ES: { locale: 'es', currency: 'EUR' },
        NL: { locale: 'nl', currency: 'EUR' },
        GB: { locale: 'en', currency: 'GBP' },
    };
    return (country && countries[country]) || { locale: 'en', currency: 'EUR' };
}
