<script setup lang="ts">
import { tr as t } from '@/i18n';

import { onMounted, onUnmounted, ref } from 'vue';
import { Head } from '@inertiajs/vue3';
import StorefrontLayout from '@/layouts/StorefrontLayout.vue';
import OrderTrackingTimeline from '@/components/order/OrderTrackingTimeline.vue';
import ZFStatusBadge from '@/components/ui/ZFStatusBadge.vue';
import { api, money } from '@/lib/http';
import type { OrderStatus, RestaurantBrand } from '@/types';
const props = defineProps<{ orderId: string }>();
const data = ref<{
    restaurant: RestaurantBrand;
    status: OrderStatus;
    total: number;
    currency: string;
    history: { to_status: string; created_at: string }[];
}>();
const error = ref('');
let timer: ReturnType<typeof setTimeout> | undefined;
let stopped = false;
async function sync() {
    const token = sessionStorage.getItem(`zf-order-${props.orderId}`);
    if (!token) {
        error.value = t('open_this_order_in_the_browser_where_you_placed_it_the_secure_tracking_token_is_required');
        return;
    }
    try {
        data.value = await api(`/api/v1/public/orders/${props.orderId}`, 'GET', undefined, {
            Authorization: `Bearer ${token}`,
        });
        error.value = '';
    } catch (e) {
        error.value = (e as Error).message;
    }
    if (!stopped && !['completed', 'rejected', 'cancelled'].includes(data.value?.status ?? ''))
        timer = setTimeout(sync, 5000);
}
onMounted(sync);
onUnmounted(() => {
    stopped = true;
    clearTimeout(timer);
});
</script>
<template>
    <StorefrontLayout :brand="data?.restaurant" :name="data?.restaurant.name"
        ><Head :title="$t('track_your_order')" />
        <div class="mx-auto max-w-xl">
            <div class="panel">
                <i aria-hidden="true" class="pi pi-check-circle mb-5 text-5xl text-success" />
                <h1 class="text-3xl font-semibold">{{ $t('youre_on_the_menu') }}</h1>
                <p class="muted my-4">
                    {{ $t('order') }} {{ orderId.slice(-8) }} {{ $t('this_page_updates_automatically') }}
                </p>
                <p v-if="error" role="alert" class="my-4 text-red-600">{{ error }}</p>
                <template v-if="data"
                    ><div class="my-6 flex items-center justify-between">
                        <ZFStatusBadge :status="data.status" /><strong>{{ money(data.total, data.currency) }}</strong>
                    </div>
                    <OrderTrackingTimeline :history="data.history"
                /></template>
            </div></div
    ></StorefrontLayout>
</template>
