<script setup lang="ts">
import ZFDrawer from '@/components/ui/ZFDrawer.vue';
import ZFStatusBadge from '@/components/ui/ZFStatusBadge.vue';
import OrderTrackingTimeline from './OrderTrackingTimeline.vue';
import { money } from '@/lib/http';
import type { Order } from '@/types';
defineProps<{ order: Order | null }>();
const visible = defineModel<boolean>('visible', { required: true });
</script>
<template>
    <ZFDrawer v-model:visible="visible" :title="$t('order_details')"
        ><div v-if="order" class="grid gap-6">
            <div class="flex justify-between">
                <strong>#{{ order.public_id.slice(-8) }}</strong
                ><ZFStatusBadge :status="order.status" />
            </div>
            <div>
                <h3 class="font-semibold">{{ order.customer_name }}</h3>
                <p v-if="order.table_code" class="my-2 font-semibold text-brand">{{ $t('dining_table') }} {{ order.table_code }} · {{ order.table_area }}</p>
                <p class="muted">{{ order.customer_phone }}</p>
                <p class="muted mt-2">{{ order.delivery_address }}</p>
                <a v-if="order.delivery_latitude != null && order.delivery_longitude != null" :href="`https://www.openstreetmap.org/?mlat=${order.delivery_latitude}&mlon=${order.delivery_longitude}#map=17/${order.delivery_latitude}/${order.delivery_longitude}`" target="_blank" rel="noopener" class="mt-3 inline-block text-brand">{{ $t('map_open_destination') }}</a>
                <p v-if="order.notes" class="my-3 rounded-xl bg-amber-50 p-3 text-amber-900">{{ order.notes }}</p>
            </div>
            <div v-for="item in order.items" :key="item.id" class="border-b border-slate-200 pb-3">
                <div class="flex justify-between">
                    <strong>{{ item.quantity }} × {{ item.name }}</strong
                    ><span>{{ money(item.total) }}</span>
                </div>
                <p class="muted mt-1">{{ item.variant_name }}</p>
                <p v-for="mod in item.modifiers" :key="mod.id" class="muted">+ {{ mod.name }}</p>
            </div>
            <div class="flex justify-between font-semibold">
                <span>{{ $t('total') }}</span
                ><span>{{ money(order.total, order.currency) }}</span>
            </div>
            <OrderTrackingTimeline :history="order.history" /></div
    ></ZFDrawer>
</template>
