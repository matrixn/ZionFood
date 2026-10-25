<script setup lang="ts">
import { onMounted, ref } from 'vue';
import { Head } from '@inertiajs/vue3';
import RestaurantLayout from '@/layouts/RestaurantLayout.vue';
import ZFPageHeader from '@/components/ui/ZFPageHeader.vue';
import OrderTable from '@/components/order/OrderTable.vue';
import OrderDetailsDrawer from '@/components/order/OrderDetailsDrawer.vue';
import Button from 'primevue/button';
import { api } from '@/lib/http';
import type { Order, WorkspaceProps } from '@/types';
const props = defineProps<WorkspaceProps>();
const orders = ref<Order[]>([]);
const selected = ref<Order | null>(null);
const visible = ref(false);
const busy = ref(false);
const error = ref('');
const next = ref<string | null>(null);
async function load(url = `/api/v1/locations/${props.location.public_id}/orders`, append = false) {
    busy.value = true;
    try {
        const result = await api<{ data: Order[]; links: { next: string | null } }>(url);
        orders.value = append ? [...orders.value, ...result.data] : result.data;
        next.value = result.links.next;
        error.value = '';
    } catch (e) {
        error.value = (e as Error).message;
    } finally {
        busy.value = false;
    }
}
onMounted(() => load());
</script>
<template>
    <RestaurantLayout v-bind="$props"
        ><Head title="Orders" /><ZFPageHeader
            title="Every order has a story."
            description="Browse current orders and your complete order history."
            ><Button label="Refresh" icon="pi pi-refresh" outlined :loading="busy" @click="load()"
        /></ZFPageHeader>
        <p v-if="error" role="alert" class="mb-5 text-red-600">{{ error }}</p>
        <OrderTable
            :orders="orders"
            :loading="busy"
            @select="
                selected = $event;
                visible = true;
            " /><Button
            v-if="next"
            class="mt-5"
            label="Load more orders"
            :loading="busy"
            @click="load(next!, true)" /><OrderDetailsDrawer v-model:visible="visible" :order="selected"
    /></RestaurantLayout>
</template>
