import { expect, it } from 'vitest';
import { defaultPricing } from '../../resources/js/lib/pricing';

it('uses integer RON defaults without inventing exchange rates before synchronization', () => {
    expect(defaultPricing.available).toEqual(['RON']);
    expect(defaultPricing.plans.map((p) => [p.price_minor, p.display_minor])).toEqual([
        [4900, 4900],
        [14900, 14900],
        [24900, 24900],
    ]);
});
