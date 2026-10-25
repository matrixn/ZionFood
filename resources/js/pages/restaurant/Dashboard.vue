<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import RestaurantLayout from '@/layouts/RestaurantLayout.vue';
import ZFPageHeader from '@/components/ui/ZFPageHeader.vue';
import ZFStatCard from '@/components/ui/ZFStatCard.vue';
import { money } from '@/lib/http';
import type { WorkspaceProps } from '@/types';
defineProps<
    WorkspaceProps & {
        metrics: { orders: number; revenue: number; active: number };
        subscription: { status: string; trial_ends_at: string } | null;
    }
>();
</script>
<template>
    <RestaurantLayout v-bind="$props"
        ><Head :title="$t('overview')" /><ZFPageHeader
            :title="$t('a_fresh_day_a_clear_view')"
            :description="location.restaurant.name + ' · ' + location.name"
        />
        <div class="grid gap-5 lg:grid-cols-3">
            <ZFStatCard :label="$t('orders_today')" :value="metrics.orders" icon="pi-shopping-bag" /><ZFStatCard
                :label="$t('completed_revenue_today')"
                :value="money(metrics.revenue)"
                icon="pi-wallet"
            /><ZFStatCard :label="$t('in_the_kitchen')" :value="metrics.active" icon="pi-clock" />
        </div>
        <div class="mt-7 grid gap-6 lg:grid-cols-2">
            <div class="panel">
                <span class="text-sm font-medium text-brand">{{ $t('operations') }}</span>
                <h2 class="my-3 text-2xl font-semibold">{{ $t('every_order_in_the_right_hands') }}</h2>
                <p class="muted">{{ $t('open_the_live_kitchen_board_to_accept_and_prepare_incoming_orders') }}</p>
                <Link
                    :href="'/app/locations/' + location.public_id + '/kitchen'"
                    class="mt-6 inline-block rounded-xl bg-brand px-5 py-3 text-white"
                    >{{ $t('open_kitchen') }} <i aria-hidden="true" class="pi pi-arrow-right ml-2"
                /></Link>
            </div>
            <div class="panel">
                <h2 class="text-lg font-semibold">{{ $t('storefront_status') }}</h2>
                <p class="my-4 flex items-center gap-2">
                    <span
                        :class="['h-2 w-2 rounded-full', location.accepting_orders ? 'bg-success' : 'bg-slate-400']"
                    />{{ location.accepting_orders ? $t('accepting_orders') : $t('orders_paused') }}
                </p>
                <p class="muted">{{ location.address }}</p>
                <p class="muted mt-4">
                    {{ $t('plan') }} {{ subscription ? $t('subscription_' + subscription.status) : $t('unavailable')
                    }}<span v-if="subscription?.status === 'trial'">
                        {{ $t('trial_ends_250') }}
                        {{
                            new Date(subscription.trial_ends_at).toLocaleDateString(
                                $i18n.locale === 'ro' ? 'ro-RO' : 'en-GB',
                            )
                        }}</span
                    >
                </p>
                <Link
                    v-if="canManage"
                    :href="'/app/locations/' + location.public_id + '/settings'"
                    class="mt-5 inline-block text-sm text-brand"
                    >{{ $t('manage_opening_hours_and_fulfillment') }}</Link
                >
            </div>
        </div></RestaurantLayout
    >
</template>
