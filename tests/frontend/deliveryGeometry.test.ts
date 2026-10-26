import { describe, expect, it } from 'vitest';
import { contains, matchingZone, type DeliveryPolygon } from '../../resources/js/lib/deliveryGeometry';

const geometry: DeliveryPolygon = {
    type: 'Polygon',
    coordinates: [
        [
            [24, 45],
            [25, 45],
            [25, 46],
            [24, 46],
            [24, 45],
        ],
    ],
};
describe('delivery geometry', () => {
    it('matches interior and boundary but excludes outside points', () => {
        expect(contains(geometry, { latitude: 45.5, longitude: 24.5 })).toBe(true);
        expect(contains(geometry, { latitude: 45, longitude: 24.5 })).toBe(true);
        expect(contains(geometry, { latitude: 45.5, longitude: 23 })).toBe(false);
    });
    it('previews the same deterministic priority that the server enforces', () => {
        const zones = [
            { id: 2, name: 'Cheap', postal_prefix: '', fee: 100, minimum_order: 0, geometry, priority: 2 },
            { id: 3, name: 'First', postal_prefix: '', fee: 900, minimum_order: 0, geometry, priority: 1 },
        ];
        expect(matchingZone(zones, { latitude: 45.5, longitude: 24.5 })?.name).toBe('First');
        expect(matchingZone(zones, null)).toBeUndefined();
    });
});
