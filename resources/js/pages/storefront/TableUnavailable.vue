<script setup lang="ts">
import { useLocationDates } from '@/lib/locationDates';
import Button from 'primevue/button';
import OrderingStatus from '@/components/storefront/OrderingStatus.vue';
import { useOrderingAvailability, type OrderingAvailability } from '@/lib/orderingAvailability';
import { Head, Link } from '@inertiajs/vue3';
import TableOrders from '@/components/storefront/TableOrders.vue';
import StorefrontLayout from '@/layouts/StorefrontLayout.vue';
import type { RestaurantBrand } from '@/types';
const props = defineProps<{
    orderingAvailability: OrderingAvailability;
    location?: import('@/lib/locationDates').DateContext;
    restaurant: RestaurantBrand;
    reason: 'service_disabled' | 'table_closed' | 'outside_hours' | 'cutoff';
    menuUrl: string;
    tableId?: string;
    orderingWindow?: { opens_at: string | null; closes_at: string | null; cutoff_at: string | null; timezone: string };
}>();
const { status, state, elapsed, stale } = useOrderingAvailability(
    () => props.orderingAvailability,
    () => `/api/v1/public/tables/${props.tableId}/availability`,
);
const dates = useLocationDates(() => ({ timezone: status.value.schedule.timezone }));
function reopen() {
    window.location.reload();
}
</script>
<template>
    <StorefrontLayout :name="restaurant.name" :brand="restaurant">
        <Head :title="$t('dining_unavailable_title')"><meta name="robots" content="noindex" /></Head>
        <OrderingStatus :availability="status" :elapsed="elapsed" :stale="stale" />
        <section class="panel mx-auto my-10 max-w-xl space-y-5 text-center">
            <i class="pi pi-info-circle text-4xl text-brand" aria-hidden="true" />
            <h1 class="text-2xl font-semibold">
                {{ $t(state.canOrder ? 'ordering_table_ready' : 'dining_unavailable_title') }}
            </h1>
            <p class="leading-relaxed text-slate-600">
                {{
                    $t(
                        stale
                            ? 'ordering_status_stale'
                            : state.canOrder
                              ? 'ordering_table_ready_help'
                              : 'ordering_reason_' + state.reason,
                    )
                }}
            </p>
            <p v-if="!state.canOrder" class="muted">{{ $t('dining_ask_team') }}</p>
            <p
                v-if="
                    !state.canOrder &&
                    status.schedule.opens_at &&
                    status.schedule.closes_at &&
                    status.deadline_at &&
                    (state.reason === 'outside_hours' || state.reason === 'cutoff')
                "
                class="rounded-xl bg-violet-50 p-4 text-sm text-slate-600"
            >
                {{
                    $t('dining_window_times', {
                        open: dates.time(status.schedule.opens_at!),
                        close: dates.time(status.schedule.closes_at!),
                        cutoff: dates.time(status.deadline_at!),
                        zone: status.schedule.timezone,
                    })
                }}
            </p>
            <Button v-if="state.canOrder" :label="$t('ordering_reopen_menu')" @click="reopen" />
            <Link :href="menuUrl" class="inline-block rounded-xl bg-brand px-5 py-3 font-semibold text-white">{{
                $t('store_view_menu')
            }}</Link>
        </section>
        <TableOrders v-if="tableId" :key="tableId" :table-id="tableId" />
    </StorefrontLayout>
</template>
