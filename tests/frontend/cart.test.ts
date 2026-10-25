import { beforeEach, expect, it } from 'vitest';
import { createPinia, setActivePinia } from 'pinia';
import { useCart } from '../../resources/js/stores/cart';
beforeEach(() => setActivePinia(createPinia()));
it('scopes the cart to its location and calculates integer subtotals', () => {
    const cart = useCart(); cart.scope('a');
    cart.add({ key: 'line', product_id: 'product', name: 'Pizza', quantity: 3, variant_id: null, variant_name: null, modifier_ids: [], modifier_names: [], unit_price: 3501 });
    expect(cart.total).toBe(10503); cart.scope('a'); expect(cart.lines).toHaveLength(1);
    cart.scope('b'); expect(cart.total).toBe(0); expect(cart.lines).toHaveLength(0);
});
it('rotates idempotency keys only for changed carts and clears successful orders', () => {
    const cart = useCart(); cart.scope('a'); const key = cart.checkoutKey;
    cart.scope('a'); expect(cart.checkoutKey).toBe(key);
    cart.add({ key: 'one', product_id: 'p', name: 'Pizza', quantity: 1, variant_id: null, variant_name: null, modifier_ids: [], modifier_names: [], unit_price: 100 });
    expect(cart.checkoutKey).not.toBe(key); cart.clear(); expect(cart.lines).toEqual([]);
});
