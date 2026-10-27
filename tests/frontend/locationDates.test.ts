import { describe, it, expect } from 'vitest';
import { locationDate } from '../../resources/js/lib/locationDates';
const context = {
    timezone: 'Europe/Bucharest',
    display_settings: { date_format: 'd/m/Y', datetime_format: 'd.m.Y H:i' },
};
describe('location date formatting', () => {
    it('formats UTC instants in the venue timezone without seconds', () => {
        expect(locationDate('2026-10-27T23:05:09Z', context)).toBe('28.10.2026 01:05');
        expect(locationDate('2026-10-27T23:05:09Z', context, 'date')).toBe('28/10/2026');
    });
    it('keeps date-only and local booking slot calendar values', () => {
        expect(locationDate('2026-10-27', context, 'date')).toBe('27/10/2026');
        expect(locationDate('2026-10-27T23:05', context)).toBe('27.10.2026 23:05');
    });
    it('supports seconds, 12-hour formats and DST fallback', () => {
        const us = { ...context, display_settings: { date_format: 'Y-m-d', datetime_format: 'm/d/Y h:i:s A' } };
        expect(locationDate('2026-10-27T13:05:09Z', us)).toBe('10/27/2026 03:05:09 PM');
        expect(locationDate('2026-10-25T01:05:09Z', context, 'time')).toBe('03:05');
        expect(locationDate('bad date', context)).toBe('—');
    });
});
