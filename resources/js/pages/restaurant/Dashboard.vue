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
        ><Head title="Overview" /><ZFPageHeader
            title="A fresh day. A clear view."
            :description="location.restaurant.name + ' · ' + location.name"
        />
        <div class="grid gap-5 lg:grid-cols-3">
            <ZFStatCard label="Orders today" :value="metrics.orders" icon="pi-shopping-bag" /><ZFStatCard
                label="Completed revenue today"
                :value="money(metrics.revenue)"
                icon="pi-wallet"
            /><ZFStatCard label="In the kitchen" :value="metrics.active" icon="pi-clock" />
        </div>
        <div class="mt-7 grid gap-6 lg:grid-cols-2">
            <div class="panel">
                <span class="text-sm font-medium text-brand">OPERATIONS</span>
                <h2 class="my-3 text-2xl font-semibold">Every order, in the right hands.</h2>
                <p class="muted">Open the live kitchen board to accept and prepare incoming orders.</p>
                <Link
                    :href="'/app/locations/' + location.public_id + '/kitchen'"
                    class="mt-6 inline-block rounded-xl bg-brand px-5 py-3 text-white"
                    >Open kitchen <i class="pi pi-arrow-right ml-2"
                /></Link>
            </div>
            <div class="panel">
                <h2 class="text-lg font-semibold">Storefront status</h2>
                <p class="my-4 flex items-center gap-2">
                    <span
                        :class="['h-2 w-2 rounded-full', location.accepting_orders ? 'bg-success' : 'bg-slate-400']"
                    />{{ location.accepting_orders ? 'Accepting orders' : 'Orders paused' }}
                </p>
                <p class="muted">{{ location.address }}</p>
                <p class="muted mt-4">
                    Plan: {{ subscription?.status ?? 'Unavailable'
                    }}<span v-if="subscription?.status === 'trial'">
                        · Trial ends {{ new Date(subscription.trial_ends_at).toLocaleDateString() }}</span
                    >
                </p>
                <Link
                    v-if="canManage"
                    :href="'/app/locations/' + location.public_id + '/settings'"
                    class="mt-5 inline-block text-sm text-brand"
                    >Manage opening hours and fulfillment</Link
                >
            </div>
        </div></RestaurantLayout
    >
</template>
