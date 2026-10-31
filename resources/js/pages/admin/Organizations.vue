<script setup lang="ts">
import { reactive, ref, watch } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import InputText from 'primevue/inputtext';
import Select from 'primevue/select';
import Button from 'primevue/button';
import AdminLayout from '@/layouts/AdminLayout.vue';
import ZFPageHeader from '@/components/ui/ZFPageHeader.vue';
import ZFFormField from '@/components/ui/ZFFormField.vue';
import AdminPagination from '@/components/ui/AdminPagination.vue';
import { adminDate, type Pagination, type Subscription } from '@/lib/platform';
const props = defineProps<{
    organizations: Pagination<{
        public_id: string;
        name: string;
        created_at: string;
        restaurants_count: number;
        memberships_count: number;
        subscription: Subscription | null;
    }>;
    filters: { search?: string; subscription?: string };
}>();
const filter = reactive({
    search: props.filters.search ?? '',
    subscription: props.filters.subscription ?? (null as string | null),
});
watch(
    () => props.filters,
    (value) => Object.assign(filter, { search: value.search ?? '', subscription: value.subscription ?? null }),
);
const busy = ref(false);
function apply() {
    router.get(
        '/admin/organizations',
        { search: filter.search || undefined, subscription: filter.subscription || undefined },
        { preserveState: true, onStart: () => (busy.value = true), onFinish: () => (busy.value = false) },
    );
}
</script>
<template>
    <AdminLayout
        ><Head :title="$t('pa_organizations')" /><ZFPageHeader
            :title="$t('pa_organizations')"
            :description="$t('pa_directory_help')" />
        <form class="panel mb-6 grid items-end gap-4 sm:grid-cols-[1fr_1fr_auto]" @submit.prevent="apply">
            <ZFFormField for="org-search" :label="$t('pa_search')" :help="$t('pa_org_search_help')"
                ><InputText
                    id="org-search"
                    v-model="filter.search"
                    :aria-label="$t('pa_search')"
                    maxlength="120" /></ZFFormField
            ><ZFFormField for="org-status" :label="$t('pa_subscription')" :help="$t('pa_subscription_help')"
                ><Select
                    v-model="filter.subscription"
                    input-id="org-status"
                    :aria-label="$t('pa_subscription')"
                    :options="
                        ['trial', 'expired', 'other'].map((value) => ({ value, label: $t('pa_subscription_' + value) }))
                    "
                    option-label="label"
                    option-value="value"
                    show-clear
                    :placeholder="$t('pa_all')" /></ZFFormField
            ><Button type="submit" :label="$t('pa_filter')" icon="pi pi-filter" :loading="busy" />
        </form>
        <section class="panel">
            <div class="admin-table-scroll">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>{{ $t('organization') }}</th>
                            <th>{{ $t('restaurants') }}</th>
                            <th>{{ $t('members') }}</th>
                            <th>{{ $t('pa_subscription') }}</th>
                            <th>{{ $t('pa_registered') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="row in organizations.data" :key="row.public_id">
                            <td>
                                <Link
                                    :href="'/admin/organizations/' + row.public_id"
                                    class="font-semibold text-brand"
                                    >{{ row.name }}</Link
                                ><small class="mt-1 block text-xs text-slate-500">{{ row.public_id }}</small>
                            </td>
                            <td>{{ row.restaurants_count }}</td>
                            <td>{{ row.memberships_count }}</td>
                            <td>
                                <span class="admin-badge" :class="row.subscription?.expired ? 'warning' : ''">{{
                                    row.subscription?.plan ?? '—'
                                }}</span>
                                <p class="muted mt-1 text-xs">
                                    {{
                                        row.subscription
                                            ? row.subscription.expired
                                                ? $t('pa_subscription_expired')
                                                : $t('subscription_' + row.subscription.status)
                                            : $t('pa_no_subscription')
                                    }}
                                </p>
                            </td>
                            <td>{{ adminDate(row.created_at) }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <p v-if="!organizations.data.length" class="admin-empty">{{ $t('pa_empty') }}</p>
            <AdminPagination :pagination="organizations" /></section
    ></AdminLayout>
</template>
