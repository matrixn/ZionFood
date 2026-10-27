<script setup lang="ts">
import { useLocationDates } from '@/lib/locationDates';
const dates = useLocationDates();
import { computed, onMounted, onBeforeUnmount, ref, watch } from 'vue';
import Button from 'primevue/button';
import ZFStatusBadge from '@/components/ui/ZFStatusBadge.vue';
import { api, ApiError, money } from '@/lib/http';
import { readTableReceipts, saveTableReceipts } from '@/lib/tableReceipts';
import type { OrderStatus } from '@/types';
interface TableOrder {
    public_id: string;
    status: OrderStatus;
    version: number;
    currency: string;
    total: number;
    created_at: string;
    items: {
        id: number;
        name: string;
        variant_name: string | null;
        quantity: number;
        total: number;
        modifiers: { name: string; quantity: number; price: number }[];
    }[];
}
const props = defineProps<{ tableId: string; sessionToken?: string | null; refreshKey?: number }>();
const tokens = ref<string[]>([]);
const orders = ref<TableOrder[]>([]);
const error = ref(false);
const loading = ref(false);
const total = computed(() => orders.value.reduce((sum, o) => sum + o.total, 0));
let timer: ReturnType<typeof setTimeout> | undefined;
let generation = 0;
let mounted = false;
let stopped = false;
function remember() {
    if (props.sessionToken && !tokens.value.includes(props.sessionToken)) {
        tokens.value = [...tokens.value, props.sessionToken].slice(-16);
        saveTableReceipts(props.tableId, tokens.value);
    }
}
async function sync() {
    clearTimeout(timer);
    const version = ++generation;
    const table = props.tableId;
    const active = [...tokens.value];
    if (!active.length) {
        orders.value = [];
        return;
    }
    loading.value = true;
    try {
        const collected: TableOrder[] = [];
        const retained: string[] = [];
        for (const token of active) {
            let cursor: string | null = null;
            let found = false;
            const visited = new Set<string>();
            do {
                let result: { orders: TableOrder[]; next_cursor: string | null };
                try {
                    result = await api(
                        `/api/v1/public/tables/${table}/orders${cursor ? '?cursor=' + encodeURIComponent(cursor) : ''}`,
                        'GET',
                        undefined,
                        { Authorization: 'Bearer ' + token },
                    );
                } catch (e) {
                    if (e instanceof ApiError && e.status === 404) break;
                    throw e;
                }
                if (stopped || version !== generation) return;
                collected.push(...result.orders);
                found = found || result.orders.length > 0;
                cursor = result.next_cursor;
                if (cursor) {
                    if (visited.has(cursor)) throw new Error('pagination');
                    visited.add(cursor);
                }
            } while (cursor);
            if (found || token === props.sessionToken) retained.push(token);
        }
        if (stopped || version !== generation) return;
        tokens.value = retained;
        saveTableReceipts(table, retained);
        orders.value = [...new Map(collected.map((o) => [o.public_id, o])).values()].sort((a, b) =>
            a.created_at.localeCompare(b.created_at),
        );
        error.value = false;
    } catch {
        if (version === generation && !stopped) error.value = true;
    } finally {
        if (version === generation && !stopped) {
            loading.value = false;
            timer = setTimeout(() => {
                if (document.visibilityState === 'visible') void sync();
            }, 10000);
        }
    }
}
function wake() {
    if (document.visibilityState === 'visible') void sync();
}
watch(
    () => [props.sessionToken, props.refreshKey],
    () => {
        if (mounted) {
            remember();
            void sync();
        }
    },
);
onMounted(() => {
    mounted = true;
    tokens.value = readTableReceipts(props.tableId);
    remember();
    void sync();
    window.addEventListener('focus', wake);
    window.addEventListener('online', wake);
    document.addEventListener('visibilitychange', wake);
});
onBeforeUnmount(() => {
    stopped = true;
    generation++;
    clearTimeout(timer);
    window.removeEventListener('focus', wake);
    window.removeEventListener('online', wake);
    document.removeEventListener('visibilitychange', wake);
});
</script>
<template>
    <section
        v-if="orders.length || error"
        class="panel mb-6 space-y-4"
        data-testid="table-active-orders"
        :aria-label="$t('table_orders_title')"
        :aria-busy="loading"
    >
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <h2 class="text-xl font-semibold">{{ $t('table_orders_title') }}</h2>
                <p class="muted mt-1">{{ $t('table_orders_help') }}</p>
            </div>
            <Button
                icon="pi pi-refresh"
                text
                rounded
                :aria-label="$t('table_orders_refresh')"
                :loading="loading"
                @click="sync"
            />
        </div>
        <p v-if="error" role="alert" class="rounded-xl bg-amber-50 p-3 text-sm text-amber-900">
            {{ $t('table_orders_error') }}
        </p>
        <article
            v-for="order in orders"
            :key="order.public_id"
            class="rounded-2xl border border-slate-200 p-4 dark:border-slate-700"
            :data-order="order.public_id"
        >
            <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
                <div>
                    <span class="text-sm font-semibold">{{
                        $t('table_order_number', { number: order.public_id.slice(-6) })
                    }}</span
                    ><span class="ml-2 text-xs text-slate-500">{{ dates.time(order.created_at) }}</span>
                </div>
                <ZFStatusBadge :status="order.status" />
            </div>
            <ul class="space-y-3">
                <li v-for="item in order.items" :key="item.id" class="flex justify-between gap-3 text-sm">
                    <div class="min-w-0">
                        <strong>{{ item.quantity }} × {{ item.name }}</strong>
                        <p v-if="item.variant_name" class="muted">{{ item.variant_name }}</p>
                        <p v-for="(m, i) in item.modifiers" :key="i" class="text-xs text-slate-500">
                            + {{ m.quantity }} × {{ m.name }}
                        </p>
                    </div>
                    <span class="shrink-0">{{ money(item.total, order.currency) }}</span>
                </li>
            </ul>
            <div class="mt-4 flex justify-between border-t border-slate-100 pt-3 text-sm font-semibold">
                <span>{{ $t('total') }}</span
                ><span>{{ money(order.total, order.currency) }}</span>
            </div>
        </article>
        <div v-if="orders.length > 1" class="flex justify-between text-sm font-semibold">
            <span>{{ $t('table_orders_total') }}</span
            ><span>{{ money(total, orders[0]?.currency) }}</span>
        </div>
    </section>
</template>
