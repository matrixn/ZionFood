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
    const versions = new Map<string, number>();
    async function sync(locationId: string, active = true) {
        const current = ++revision;
        loading.value = true;
        try {
            let url: string | null = `/api/v1/locations/${locationId}/kitchen-orders?active=${active ? 1 : 0}`;
            const result: Order[] = [];
            while (url) {
                const page: { data: Order[]; links: { next: string | null } } = await api(url);
                result.push(...page.data);
                url = active ? page.links.next : null;
            }
            if (current === revision) {
                orders.value = result.filter((order) => order.version >= (versions.get(order.public_id) ?? 0));
                orders.value.forEach((order) => versions.set(order.public_id, order.version));
                error.value = '';
                syncedAt.value = new Date();
            }
        } catch (e) {
            if (current === revision) {
                if (e instanceof Error && 'status' in e && (e.status === 401 || e.status === 403)) orders.value = [];
                error.value = (e as Error).message;
            }
        } finally {
            if (current === revision) loading.value = false;
        }
    }
    function apply(order: Order) {
        if (order.version < (versions.get(order.public_id) ?? 0)) return;
        versions.set(order.public_id, order.version);
        loading.value = false;
        // A confirmed transition response is authoritative; discard older in-flight list responses.
        revision++;
        orders.value = orders.value.filter((current) => current.public_id !== order.public_id);
        if (!['completed', 'rejected', 'cancelled'].includes(order.status)) orders.value.unshift(order);
    }
    function reset() {
        versions.clear();
        revision++;
        orders.value = [];
        error.value = '';
        syncedAt.value = null;
        loading.value = false;
    }
    return { orders, error, loading, syncedAt, sync, apply, reset };
});
