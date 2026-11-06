import defaults from '../../data/pricing.json';

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
