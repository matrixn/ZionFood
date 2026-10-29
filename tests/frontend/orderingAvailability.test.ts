import { describe, expect, it } from 'vitest';
import { orderingState, type OrderingAvailability } from '../../resources/js/lib/orderingAvailability';

const snapshot: OrderingAvailability = {
    schedule: { is_open: true, opens_at: '2026-10-29T10:00:00+02:00', closes_at: '2026-10-29T22:00:00+02:00', next_opens_at: null, checked_at: '2026-10-29T21:00:00+02:00', timezone: 'Europe/Bucharest' },
    can_order: true, can_add_to_cart: true, reason: 'open', guard_enabled: true, mode: 'online', deadline_at: '2026-10-29T22:00:00+02:00',
};

describe('ordering deadline derived from server time', () => {
    it('warns only in the final hour and blocks exactly at the deadline without clearing the cart', () => {
        expect(orderingState(snapshot).lastHour).toBe(true);
        expect(orderingState({ ...snapshot, schedule: { ...snapshot.schedule, checked_at: '2026-10-29T20:59:59+02:00' } }).lastHour).toBe(false);
        expect(orderingState(snapshot, 3600_000).canOrder).toBe(false);
        expect(orderingState(snapshot, 3600_000).reason).toBe('outside_hours');
    });
    it('honors the QR cutoff while retaining the physical opening state', () => {
        const table = { ...snapshot, mode: 'table' as const, deadline_at: '2026-10-29T21:30:00+02:00' };
        expect(orderingState(table, 1800_000)).toMatchObject({ isOpen: true, canOrder: false, reason: 'cutoff', remaining: 0 });
    });
    it('permits only cart preparation after closing with protection off and never enables submission', () => {
        expect(orderingState({ ...snapshot, guard_enabled: false }, 3600_000)).toMatchObject({ isOpen: false, canOrder: false, canAdd: true });
        expect(orderingState({ ...snapshot, can_order: false, reason: 'no_open_tables' })).toMatchObject({ canOrder: false, reason: 'no_open_tables' });
    });
});
