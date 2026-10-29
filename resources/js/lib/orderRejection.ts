import type { Order } from '@/types';

export function orderRejectionReason(order: Pick<Order, 'status' | 'history'>): string | null {
    if (order.status !== 'rejected') return null;
    const rejection = [...order.history].reverse().find((entry) => entry.to_status === 'rejected');
    return rejection?.reason?.trim() || null;
}
