<script setup lang="ts">
import KitchenOrderCard from './KitchenOrderCard.vue';
import type { Order } from '@/types';
defineProps<{ orders: Order[]; now: number }>();
const emit = defineEmits<{ changed: []; details: [order: Order] }>();
const columns = [
    { title: 'Incoming', statuses: ['pending'] },
    { title: 'In preparation', statuses: ['accepted', 'preparing'] },
    { title: 'Ready to serve', statuses: ['ready', 'out_for_delivery'] },
];
</script>
<template>
    <div class="grid gap-5 xl:grid-cols-3">
        <section v-for="column in columns" :key="column.title" class="rounded-2xl bg-slate-100 p-4 dark:bg-slate-800">
            <div class="mb-4 flex items-center justify-between">
                <h2 class="font-semibold">{{ column.title }}</h2>
                <span class="muted rounded-full bg-white px-3 py-1 dark:bg-slate-900">{{
                    orders.filter((o) => column.statuses.includes(o.status)).length
                }}</span>
            </div>
            <div class="grid gap-4">
                <KitchenOrderCard
                    v-for="order in orders.filter((o) => column.statuses.includes(o.status))"
                    :key="order.public_id"
                    :order="order"
                    :now="now"
                    @changed="emit('changed')"
                    @details="emit('details', $event)"
                />
                <p v-if="!orders.some((o) => column.statuses.includes(o.status))" class="muted py-10 text-center">
                    All clear. Ready for what’s next.
                </p>
            </div>
        </section>
    </div>
</template>
