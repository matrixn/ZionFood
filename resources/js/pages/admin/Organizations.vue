<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import AdminLayout from '@/layouts/AdminLayout.vue';
import ZFPageHeader from '@/components/ui/ZFPageHeader.vue';
import DataTable from 'primevue/datatable';
import Column from 'primevue/column';
import type { Location } from '@/types';
defineProps<{
    organizations: {
        data: { id: number; public_id: string; name: string; restaurants_count: number; memberships_count: number }[];
        prev_page_url: string | null;
        next_page_url: string | null;
    };
    selected: { name: string; id: number; restaurants: { id: number; name: string; locations: Location[] }[] } | null;
    subscriptions: { organization_id: number; status: string; trial_ends_at: string; plan: { name: string } }[];
}>();
</script>
<template>
    <AdminLayout
        ><Head :title="$t('platform_organizations')" /><ZFPageHeader
            :title="$t('platform_overview')"
            :description="$t('organizations_restaurant_locations_and_trial_subscriptions')"
        /><Link href="/admin/cuisines" class="mb-5 mr-6 inline-block font-semibold text-brand">{{
            $t('cuisine_admin_title')
        }}</Link
        ><Link href="/test-email" class="mb-5 inline-block font-semibold text-brand">{{ $t('email_test_title') }}</Link
        ><DataTable :value="organizations.data"
            ><Column :header="$t('organization')"
                ><template #body="{ data }"
                    ><Link :href="'/admin/' + data.public_id" class="font-semibold text-brand">{{
                        data.name
                    }}</Link></template
                ></Column
            ><Column field="restaurants_count" :header="$t('restaurants')" /><Column
                field="memberships_count"
                :header="$t('members')"
            /><Column :header="$t('plan_246')"
                ><template #body="{ data }">{{
                    subscriptions.find((s) => s.organization_id === data.id)?.plan.name ?? '—'
                }}</template></Column
            ><Column :header="$t('status')"
                ><template #body="{ data }">{{
                    $t(
                        'subscription_' +
                            (subscriptions.find((s) => s.organization_id === data.id)?.status ?? 'unknown'),
                    )
                }}</template></Column
            ></DataTable
        >
        <div class="mt-5 flex gap-5">
            <Link v-if="organizations.prev_page_url" :href="organizations.prev_page_url">{{ $t('previous') }}</Link
            ><Link v-if="organizations.next_page_url" :href="organizations.next_page_url">{{ $t('next') }}</Link>
        </div>
        <section v-if="selected" class="panel mt-8">
            <h2 class="mb-5 text-xl font-semibold">{{ selected.name }}</h2>
            <div v-for="restaurant in selected.restaurants" :key="restaurant.id" class="mb-5">
                <strong>{{ restaurant.name }}</strong>
                <p v-for="location in restaurant.locations" :key="location.id" class="muted mt-2">
                    {{ location.name }} · {{ location.address }}
                </p>
            </div>
            <p class="muted">
                {{ $t('trial_ends') }}
                {{ subscriptions.find((s) => s.organization_id === selected?.id)?.trial_ends_at ?? '—' }}
            </p>
        </section></AdminLayout
    >
</template>
