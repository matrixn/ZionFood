<script setup lang="ts">
import { reactive, ref, watch } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import InputText from 'primevue/inputtext';
import Select from 'primevue/select';
import Button from 'primevue/button';
import AdminLayout from '@/layouts/AdminLayout.vue';
import ZFPageHeader from '@/components/ui/ZFPageHeader.vue';
import ZFFormField from '@/components/ui/ZFFormField.vue';
import AdminPagination from '@/components/ui/AdminPagination.vue';
import { adminDate, type Pagination } from '@/lib/platform';
const props = defineProps<{
    users: Pagination<{
        id: number;
        name: string;
        email: string;
        email_verified_at: string | null;
        is_platform_admin: boolean;
        memberships_count: number;
        created_at: string;
    }>;
    filters: { search?: string; access?: string };
}>();
const filter = reactive({
    search: props.filters.search ?? '',
    access: props.filters.access ?? (null as string | null),
});
watch(
    () => props.filters,
    (value) => Object.assign(filter, { search: value.search ?? '', access: value.access ?? null }),
);
const busy = ref(false);
function apply() {
    router.get(
        '/admin/users',
        { search: filter.search || undefined, access: filter.access || undefined },
        { preserveState: true, onStart: () => (busy.value = true), onFinish: () => (busy.value = false) },
    );
}
</script>
<template>
    <AdminLayout
        ><Head :title="$t('pa_users')" /><ZFPageHeader
            :title="$t('pa_users')"
            :description="$t('pa_users_description')" />
        <form class="panel mb-6 grid items-end gap-4 sm:grid-cols-[1fr_1fr_auto]" @submit.prevent="apply">
            <ZFFormField for="user-search" :label="$t('pa_search')" :help="$t('pa_user_search_help')"
                ><InputText id="user-search" v-model="filter.search" maxlength="120" /></ZFFormField
            ><ZFFormField for="user-access" :label="$t('pa_access')" :help="$t('pa_access_help')"
                ><Select
                    v-model="filter.access"
                    input-id="user-access"
                    :aria-label="$t('pa_access')"
                    :options="['admin', 'verified', 'unverified'].map((value) => ({ value, label: $t('pa_' + value) }))"
                    option-label="label"
                    option-value="value"
                    show-clear
                    :placeholder="$t('pa_all')" /></ZFFormField
            ><Button type="submit" :label="$t('pa_filter')" :loading="busy" icon="pi pi-filter" />
        </form>
        <section class="panel">
            <div class="admin-table-scroll">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>{{ $t('name') }}</th>
                            <th>{{ $t('pa_access') }}</th>
                            <th>{{ $t('pa_memberships') }}</th>
                            <th>{{ $t('pa_registered') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="user in users.data" :key="user.id">
                            <td>
                                <strong class="block">{{ user.name }}</strong
                                ><span class="muted">{{ user.email }}</span>
                            </td>
                            <td>
                                <span class="admin-badge" :class="user.is_platform_admin ? 'accent' : ''">{{
                                    $t(user.is_platform_admin ? 'pa_admin' : 'pa_tenant_account')
                                }}</span>
                                <p class="muted mt-1 text-xs">
                                    {{ $t(user.email_verified_at ? 'pa_verified' : 'pa_unverified') }}
                                </p>
                            </td>
                            <td>{{ user.memberships_count }}</td>
                            <td>{{ adminDate(user.created_at) }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <p v-if="!users.data.length" class="admin-empty">{{ $t('pa_empty') }}</p>
            <AdminPagination :pagination="users" /></section
    ></AdminLayout>
</template>
