<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import AdminLayout from '@/layouts/AdminLayout.vue';
import ZFPageHeader from '@/components/ui/ZFPageHeader.vue';
import { adminDate, type Subscription } from '@/lib/platform';
defineProps<{
    organization: {
        public_id: string;
        name: string;
        created_at: string;
        subscription: Subscription | null;
        restaurants: {
            public_id: string;
            name: string;
            locations: {
                public_id: string;
                name: string;
                address: string;
                timezone: string;
                accepting_orders: boolean;
            }[];
        }[];
        members: { name: string | null; email: string | null; role: string; verified: boolean }[];
    };
}>();
</script>
<template>
    <AdminLayout
        ><Head :title="organization.name" /><Link
            href="/admin/organizations"
            class="mb-5 inline-flex items-center gap-2 text-sm text-brand"
            ><i class="pi pi-arrow-left" aria-hidden="true" />{{ $t('pa_organizations') }}</Link
        ><ZFPageHeader :title="organization.name" :description="$t('pa_client_detail_help')" />
        <section class="panel mb-6 flex flex-wrap justify-between gap-5">
            <div>
                <p class="admin-eyebrow">{{ $t('pa_client_record') }}</p>
                <p class="mt-2 font-mono text-sm">{{ organization.public_id }}</p>
                <p class="muted mt-2">{{ $t('pa_registered') }} · {{ adminDate(organization.created_at) }}</p>
            </div>
            <div>
                <p class="admin-eyebrow">{{ $t('pa_subscription') }}</p>
                <p class="mt-2 font-semibold">{{ organization.subscription?.plan ?? $t('pa_no_subscription') }}</p>
                <p class="muted mt-2">
                    {{
                        organization.subscription
                            ? organization.subscription.expired
                                ? $t('pa_subscription_expired')
                                : $t('subscription_' + organization.subscription.status)
                            : '—'
                    }}
                </p>
                <p v-if="organization.subscription?.trial_ends_at" class="muted mt-1">
                    {{ $t('trial_ends') }} · {{ adminDate(organization.subscription.trial_ends_at) }} UTC
                </p>
            </div>
        </section>
        <div class="grid gap-6 xl:grid-cols-[1.2fr_1fr]">
            <section class="panel">
                <h2 class="admin-section-title">{{ $t('pa_restaurants_locations') }}</h2>
                <div v-for="restaurant in organization.restaurants" :key="restaurant.public_id" class="mt-5">
                    <h3 class="font-semibold">
                        <i class="pi pi-building mr-2 text-brand" aria-hidden="true" />{{ restaurant.name }}
                    </h3>
                    <div
                        v-for="location in restaurant.locations"
                        :key="location.public_id"
                        class="mt-3 rounded-xl border border-slate-200 p-4 dark:border-slate-700"
                    >
                        <div class="flex flex-wrap justify-between gap-2">
                            <strong class="text-sm">{{ location.name }}</strong
                            ><span class="admin-badge">{{ location.timezone }}</span>
                        </div>
                        <p class="muted mt-2">{{ location.address || '—' }}</p>
                        <p class="muted mt-2 text-xs">
                            {{ $t('pa_location_gate') }}:
                            {{ $t(location.accepting_orders ? 'pa_gate_enabled' : 'pa_gate_disabled') }}
                            <FieldHelp :label="$t('pa_location_gate')" :text="$t('pa_location_gate_help')" />
                        </p>
                    </div>
                </div>
                <p v-if="!organization.restaurants.length" class="admin-empty">{{ $t('pa_empty') }}</p>
            </section>
            <section class="panel min-w-0">
                <h2 class="admin-section-title">{{ $t('pa_members_access') }}</h2>
                <p class="muted mt-2 mb-4">{{ $t('pa_members_help') }}</p>
                <div v-for="(member, index) in organization.members" :key="index" class="admin-member">
                    <div class="min-w-0">
                        <strong class="block truncate text-sm">{{ member.name ?? '—' }}</strong
                        ><span class="muted block break-all">{{ member.email }}</span>
                    </div>
                    <div class="text-right">
                        <span class="admin-badge">{{ $t('role_' + member.role) }}</span
                        ><small class="mt-1 block text-xs text-slate-500">{{
                            $t(member.verified ? 'pa_verified' : 'pa_unverified')
                        }}</small>
                    </div>
                </div>
            </section>
        </div>
    </AdminLayout>
</template>
