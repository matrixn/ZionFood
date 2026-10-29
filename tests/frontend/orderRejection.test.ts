import { expect, it } from 'vitest';
import { orderRejectionReason } from '../../resources/js/lib/orderRejection';
import type { Order } from '../../resources/js/types';

const history: Order['history'] = [
    { id: 1, to_status: 'pending', created_at: '2026-10-29T10:00:00Z', reason: null },
    { id: 2, to_status: 'rejected', created_at: '2026-10-29T10:01:00Z', reason: ' Ingredient indisponibil ' },
];

it('reads the saved rejection reason without showing unrelated history reasons', () => {
    expect(orderRejectionReason({ status: 'rejected', history })).toBe('Ingredient indisponibil');
    expect(orderRejectionReason({ status: 'pending', history })).toBeNull();
});

it('does not fabricate or substitute a reason when the current rejection has none', () => {
    expect(orderRejectionReason({ status: 'rejected', history: [] })).toBeNull();
    expect(
        orderRejectionReason({ status: 'rejected', history: [...history, { ...history[1], id: 3, reason: null }] }),
    ).toBeNull();
});
