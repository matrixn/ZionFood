import type { Zone } from '@/types';
export interface DeliveryPolygon {
    type: 'Polygon';
    coordinates: number[][][];
}
export interface DeliveryPoint {
    latitude: number;
    longitude: number;
}
export interface MapConfig {
    tile_url: string;
    attribution: string;
}
export function contains(geometry: DeliveryPolygon, point: DeliveryPoint): boolean {
    const ring = geometry.coordinates[0];
    const x = point.longitude,
        y = point.latitude;
    let inside = false;
    for (let i = 0, j = ring.length - 1; i < ring.length; j = i++) {
        const a = ring[j],
            b = ring[i];
        const cross = (b[0] - a[0]) * (y - a[1]) - (b[1] - a[1]) * (x - a[0]);
        if (
            Math.abs(cross) < 1e-10 &&
            x >= Math.min(a[0], b[0]) - 1e-10 &&
            x <= Math.max(a[0], b[0]) + 1e-10 &&
            y >= Math.min(a[1], b[1]) - 1e-10 &&
            y <= Math.max(a[1], b[1]) + 1e-10
        )
            return true;
        if (a[1] > y !== b[1] > y && x < ((b[0] - a[0]) * (y - a[1])) / (b[1] - a[1]) + a[0]) inside = !inside;
    }
    return inside;
}
export function matchingZone(zones: Zone[], point: DeliveryPoint | null): Zone | undefined {
    if (!point) return undefined;
    return [...zones]
        .sort((a, b) => (a.priority ?? 0) - (b.priority ?? 0) || (a.id ?? 0) - (b.id ?? 0))
        .find((zone) => zone.geometry && contains(zone.geometry, point));
}
