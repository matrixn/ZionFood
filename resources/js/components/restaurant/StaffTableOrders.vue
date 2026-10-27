<script setup lang="ts">
import { ref, watch, onBeforeUnmount } from 'vue';
import Dialog from 'primevue/dialog';
import Button from 'primevue/button';
import { api, money } from '@/lib/http';
import { useLocationDates } from '@/lib/locationDates';
import { tr as t } from '@/i18n';
interface Receipt {
    public_id: string;
    status: string;
    version: number;
    total: number;
    currency: string;
    created_at: string;
    items: {
        id: number;
        name: string;
        variant_name: string | null;
        quantity: number;
        total: number;
        modifiers: { name: string; quantity: number }[];
    }[];
}
const props = defineProps<{ table: { public_id: string; code: string; area: string } | null }>();
const emit = defineEmits<{ close: []; changed: [] }>();
const orders = ref<Receipt[]>([]);
const next = ref<string | null>(null);
const canServe = ref(false);
const busy = ref(false);
const loading = ref(false);
const error = ref('');
const selected = ref<string | null>(null);
const dates = useLocationDates();
let generation = 0;
async function load(more = false) {
    if (!props.table || busy.value || loading.value) return;
    const id = props.table.public_id;
    const request = ++generation;
    loading.value = true;
    try {
        const data = await api<{ orders: Receipt[]; next_cursor: string | null; can_serve: boolean }>(
            `/app/tables/${id}/orders${more && next.value ? '?cursor=' + encodeURIComponent(next.value) : ''}`,
        );
        if (request !== generation || props.table?.public_id !== id) return;
        orders.value = more ? [...orders.value, ...data.orders] : data.orders;
        next.value = data.next_cursor;
        canServe.value = data.can_serve;
        if (!orders.value.some((o) => o.public_id === selected.value && o.status === 'ready')) selected.value = null;
    } catch (e) {
        if (request === generation) error.value = (e as Error).message;
    } finally {
        if (request === generation) loading.value = false;
    }
}
async function serve() {
    const order = orders.value.find((o) => o.public_id === selected.value && o.status === 'ready');
    if (!props.table || !order || busy.value || !canServe.value) return;
    busy.value = true;
    error.value = '';
    try {
        await api(`/app/tables/${props.table.public_id}/orders/${order.public_id}/served`, 'POST', {
            version: order.version,
        });
        emit('changed');
    } catch (e) {
        error.value = (e as Error).message;
    } finally {
        busy.value = false;
        await load();
    }
}
let timer: ReturnType<typeof setInterval> | undefined;
watch(
    () => props.table?.public_id,
    () => {
        generation++;
        loading.value = false;
        error.value = '';
        orders.value = [];
        selected.value = null;
        next.value = null;
        clearInterval(timer);
        if (props.table) {
            void load();
            timer = setInterval(() => {
                if (!next.value) void load();
            }, 15000);
        }
    },
    { immediate: true },
);
onBeforeUnmount(() => {
    generation++;
    clearInterval(timer);
});
</script>
<template>
    <Dialog
        :visible="!!table"
        modal
        :header="`${table?.code} · ${table?.area}`"
        class="w-full max-w-2xl"
        :closable="!busy"
        @update:visible="!$event && emit('close')"
    >
        <p class="muted mb-4 text-sm">{{ $t('table_orders_staff_help') }}</p>
        <p v-if="error" role="alert" class="mb-4 text-red-700">{{ error }}</p>
        <p v-if="!orders.length && !loading" class="muted">{{ $t('table_orders_staff_empty') }}</p>
        <article
            v-for="order in orders"
            :key="order.public_id"
            class="mb-4 rounded-2xl border border-slate-200 p-4 dark:border-slate-700"
            :data-order="order.public_id"
        >
            <div class="flex flex-wrap items-center justify-between gap-2">
                <strong>#{{ order.public_id.slice(-8) }}</strong
                ><span class="text-sm">{{ $t('status_' + order.status) }}</span>
            </div>
            <p class="muted my-2 text-xs">{{ dates.dateTime(order.created_at) }}</p>
            <ul class="space-y-2 text-sm">
                <li v-for="item in order.items" :key="item.id">
                    <div class="flex justify-between gap-3">
                        <span
                            >{{ item.quantity }} × {{ item.name
                            }}<span v-if="item.variant_name"> · {{ item.variant_name }}</span></span
                        ><span>{{ money(item.total, order.currency) }}</span>
                    </div>
                    <p v-for="(modifier, index) in item.modifiers" :key="index" class="muted ml-4 text-xs">
                        + {{ modifier.quantity }} × {{ modifier.name }}
                    </p>
                </li>
            </ul>
            <div class="mt-3 flex items-center justify-between border-t pt-3">
                <strong>{{ money(order.total, order.currency) }}</strong
                ><Button
                    v-if="canServe && order.status === 'ready'"
                    :label="t(selected === order.public_id ? 'table_orders_selected' : 'table_orders_select')"
                    size="small"
                    :outlined="selected !== order.public_id"
                    :disabled="busy"
                    @click="selected = order.public_id"
                />
            </div>
        </article>
        <Button v-if="next" :label="$t('table_orders_more')" outlined :loading="loading" @click="load(true)" />
        <template #footer
            ><div class="flex w-full flex-wrap justify-between gap-3">
                <Button :label="$t('close')" outlined :disabled="busy" @click="emit('close')" /><Button
                    v-if="canServe"
                    :label="$t('table_orders_mark_served')"
                    icon="pi pi-check"
                    :disabled="!selected || busy || loading"
                    :loading="busy"
                    @click="serve"
                /></div
        ></template>
    </Dialog>
</template>
