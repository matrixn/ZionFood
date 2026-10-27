import { beforeEach, expect, it, vi } from 'vitest';
import { createPinia, setActivePinia } from 'pinia';
import { useKitchen } from '../../resources/js/stores/kitchen';
import { api } from '../../resources/js/lib/http';
import type { Order } from '../../resources/js/types';
vi.mock('../../resources/js/lib/http', () => ({ api: vi.fn() }));
beforeEach(() => { setActivePinia(createPinia()); vi.clearAllMocks(); });
const order: Order = { public_id: 'order-id', status: 'pending', version: 1, fulfillment: 'pickup', customer_name: 'Guest', customer_phone: '', delivery_address: null, notes: null, subtotal: 1000, delivery_fee: 0, total: 1000, currency: 'RON', created_at: '2026-10-26T00:00:00Z', transitions: ['accepted'], items: [], history: [] };
it('applies confirmed API transitions immediately and rejects a stale in-flight queue response', async () => {
    const kitchen = useKitchen(); let resolve!: (page: { data: Order[]; links: { next: null } }) => void;
    vi.mocked(api).mockImplementationOnce(() => new Promise((done) => { resolve = done; }));
    const synchronization = kitchen.sync('location'); kitchen.apply({ ...order, status: 'completed', version: 5 });
    resolve({ data: [order], links: { next: null } }); await synchronization;
    expect(kitchen.orders).toEqual([]);
    kitchen.apply({ ...order, status: 'accepted', version: 2 }); expect(kitchen.orders).toEqual([]);
    kitchen.apply({ ...order, public_id: 'another-order', status: 'accepted', version: 2 }); expect(kitchen.orders[0].version).toBe(2);
});

it('clears previously visible queue data when feature access is revoked', async () => {
    const kitchen = useKitchen();
    kitchen.apply(order);
    vi.mocked(api).mockRejectedValueOnce(Object.assign(new Error('Forbidden'), { status: 403 }));
    await kitchen.sync('location');
    expect(kitchen.orders).toEqual([]);
    expect(kitchen.error).toBe('Forbidden');
});
