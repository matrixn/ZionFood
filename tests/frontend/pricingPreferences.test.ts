import { expect, it } from 'vitest';
import { defaultPricing, visitorPreferences } from '../../resources/js/lib/pricing';

it('selects supported country preferences from timezone before browser region with English EUR fallback', () => {
    expect(visitorPreferences(['en-US'], 'Europe/Bucharest')).toEqual({ locale: 'ro', currency: 'RON' });
    expect(visitorPreferences(['en-GB'], 'Europe/Rome')).toEqual({ locale: 'it', currency: 'EUR' });
    expect(visitorPreferences(['es-ES'], 'UTC')).toEqual({ locale: 'es', currency: 'EUR' });
    expect(visitorPreferences(['nl-NL'], 'UTC')).toEqual({ locale: 'nl', currency: 'EUR' });
    expect(visitorPreferences(['en'], 'Europe/London')).toEqual({ locale: 'en', currency: 'GBP' });
    for (const languages of [[], ['en-US'], ['fr-FR'], ['xx']])
        expect(visitorPreferences(languages, 'UTC')).toEqual({ locale: 'en', currency: 'EUR' });
});

it('uses integer RON defaults without inventing exchange rates before synchronization', () => {
    expect(defaultPricing.available).toEqual(['RON']);
    expect(defaultPricing.plans.map((p) => [p.price_minor, p.display_minor])).toEqual([
        [4900, 4900],
        [14900, 14900],
        [24900, 24900],
    ]);
});
