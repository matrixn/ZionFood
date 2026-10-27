import { defineStore } from 'pinia';
import { computed, ref } from 'vue';
export interface CartLine {
    key: string;
    product_id: string;
    order_types?: string[] | null;
    image_path?: string | null;
    name: string;
    quantity: number;
    variant_id: number | null;
    variant_name: string | null;
    modifier_ids: number[];
    modifiers?: { id: number; quantity: number }[];
    modifier_names: string[];
    unit_price: number;
}
export const useCart = defineStore('cart', () => {
    const locationId = ref('');
    const lines = ref<CartLine[]>([]);
    const checkoutKey = ref(crypto.randomUUID());
    const total = computed(() => lines.value.reduce((sum, line) => sum + line.unit_price * line.quantity, 0));
    function scope(id: string) {
        if (locationId.value !== id) {
            lines.value = [];
            locationId.value = id;
            checkoutKey.value = crypto.randomUUID();
        }
    }
    function add(line: CartLine) {
        lines.value.push(line);
        checkoutKey.value = crypto.randomUUID();
    }
    function remove(key: string) {
        lines.value = lines.value.filter((line) => line.key !== key);
        checkoutKey.value = crypto.randomUUID();
    }
    function clear() {
        lines.value = [];
        checkoutKey.value = crypto.randomUUID();
    }
    return { locationId, lines, checkoutKey, total, scope, add, remove, clear };
});
