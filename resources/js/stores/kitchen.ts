import { defineStore } from 'pinia';
import { ref } from 'vue';
import { api } from '@/lib/http';
import type { Order } from '@/types';
export const useKitchen = defineStore('kitchen', () => {
    const orders = ref<Order[]>([]);
    const error = ref('');
    const loading = ref(false);
    const syncedAt = ref<Date | null>(null);
    let revision = 0;
    async function sync(locationId: string, active = true) {
        const current = ++revision;
        loading.value = true;
        try {
            let url: string | null = `/api/v1/locations/${locationId}/orders?active=${active ? 1 : 0}`;
            const result: Order[] = [];
            while (url) {
                const page: { data: Order[]; links: { next: string | null } } = await api(url);
                result.push(...page.data);
                url = active ? page.links.next : null;
            }
            if (current === revision) {
                orders.value = result;
                error.value = '';
                syncedAt.value = new Date();
            }
        } catch (e) {
            if (current === revision) error.value = (e as Error).message;
        } finally {
            if (current === revision) loading.value = false;
        }
    }
    function reset() {
        revision++;
        orders.value = [];
        error.value = '';
        syncedAt.value = null;
        loading.value = false;
    }
    return { orders, error, loading, syncedAt, sync, reset };
});
