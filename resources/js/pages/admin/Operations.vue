<script setup lang="ts">
import { ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import Button from 'primevue/button';
import Select from 'primevue/select';
import AdminLayout from '@/layouts/AdminLayout.vue';
import AdminMetric from '@/components/ui/AdminMetric.vue';
import AdminPagination from '@/components/ui/AdminPagination.vue';
import ZFPageHeader from '@/components/ui/ZFPageHeader.vue';
import ZFFormField from '@/components/ui/ZFFormField.vue';
import { adminDate, adminDateTime, attentionLinks, type Pagination } from '@/lib/platform';
const props = defineProps<{
    attention: Record<string, number>;
    connections: Record<string, number>;
    imports: Pagination<{
        public_id: string;
        organization: string;
        location: string;
        provider: string;
        status: string;
        created_at: string;
    }>;
    filters: { status?: string };
    updatedAt: string;
    mail: {
        daily_limit: number;
        paused_until: string | null;
        pause_reason: string | null;
        usage_checked_at: string | null;
    } | null;
    alerts: { enabled: boolean; suspended_at: string | null } | null;
}>();
const busy = ref(false);
function refresh(status = props.filters.status) {
    router.get(
        '/admin/operations',
        { status: status || undefined },
        {
            preserveState: true,
            preserveScroll: true,
            onStart: () => (busy.value = true),
            onFinish: () => (busy.value = false),
        },
    );
}
</script>
<template>
    <AdminLayout
        ><Head :title="$t('pa_operations')" /><ZFPageHeader
            :title="$t('pa_operations')"
            :description="$t('pa_operations_description')"
            ><Button :label="$t('refresh')" icon="pi pi-refresh" outlined :loading="busy" @click="refresh()"
        /></ZFPageHeader>
        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
            <AdminMetric
                v-for="(count, key) in attention"
                :key="key"
                :label="$t('pa_problem_' + key)"
                :value="count"
                :help="$t('pa_attention_help')"
                icon="pi-exclamation-circle"
                :href="attentionLinks[key]"
            />
        </div>
        <div class="mt-6 grid gap-6 xl:grid-cols-2">
            <section class="panel">
                <h2 class="admin-section-title">{{ $t('pa_integrations') }}</h2>
                <p class="muted mt-2">{{ $t('pa_connection_help') }}</p>
                <dl class="mt-4">
                    <div v-for="(count, key) in connections" :key="key" class="admin-list-row">
                        <dt>{{ $t('pa_connection_' + key) }}</dt>
                        <dd class="font-semibold">{{ count }}</dd>
                    </div>
                </dl>
                <div class="mt-5 flex flex-wrap gap-5 text-sm font-semibold text-brand">
                    <Link href="/admin/communication-logs">{{ $t('pa_logs') }}</Link
                    ><a href="/horizon" target="_blank" rel="noopener"
                        >Horizon <i class="pi pi-external-link text-xs" aria-hidden="true"
                    /></a>
                </div>
                <p class="muted mt-3 text-xs">{{ $t('pa_horizon_help') }}</p>
            </section>
            <section class="panel">
                <h2 class="admin-section-title">{{ $t('pa_mail_controls') }}</h2>
                <p class="muted mt-2">{{ $t('pa_mail_help') }}</p>
                <dl class="mt-3">
                    <div class="admin-list-row">
                        <dt>{{ $t('pa_provider_limit') }}</dt>
                        <dd>{{ mail?.daily_limit ?? '—' }}</dd>
                    </div>
                    <div class="admin-list-row">
                        <dt>{{ $t('pa_provider_pause') }}</dt>
                        <dd>{{ mail?.paused_until ? adminDateTime(mail.paused_until) : '—' }}</dd>
                    </div>
                    <div class="admin-list-row">
                        <dt>{{ $t('pa_last_usage') }}</dt>
                        <dd>{{ adminDateTime(mail?.usage_checked_at ?? null) }}</dd>
                    </div>
                    <div class="admin-list-row">
                        <dt>{{ $t('pa_alerts') }}</dt>
                        <dd>
                            <span class="admin-badge" :class="alerts?.suspended_at ? 'warning' : ''">{{
                                $t(
                                    alerts
                                        ? alerts.suspended_at
                                            ? 'pa_suspended'
                                            : alerts.enabled
                                              ? 'pa_enabled'
                                              : 'pa_disabled'
                                        : 'pa_not_recorded',
                                )
                            }}</span>
                        </dd>
                    </div>
                </dl>
                <div class="mt-5 flex flex-wrap gap-5 text-sm font-semibold text-brand">
                    <Link href="/admin/emails">{{ $t('pa_manage_mail') }}</Link
                    ><Link href="/admin/email-alerts">{{ $t('pa_manage_alerts') }}</Link
                    ><a href="/test-email" target="_blank" rel="noopener"
                        >{{ $t('email_test_title') }} <i class="pi pi-external-link text-xs" aria-hidden="true"
                    /></a>
                </div>
            </section>
        </div>
        <section class="panel mt-6">
            <div class="mb-5 flex flex-wrap items-end justify-between gap-4">
                <div>
                    <h2 class="admin-section-title">{{ $t('pa_import_registry') }}</h2>
                    <p class="muted mt-2">{{ $t('pa_import_help') }}</p>
                </div>
                <ZFFormField for="import-status" :label="$t('status')" :help="$t('pa_import_status_help')"
                    ><Select
                        input-id="import-status"
                        :aria-label="$t('status')"
                        :model-value="filters.status ?? null"
                        :options="
                            ['queued', 'enriching', 'ready', 'published', 'reverted', 'failed'].map((value) => ({
                                value,
                                label: $t('pa_import_' + value),
                            }))
                        "
                        option-label="label"
                        option-value="value"
                        show-clear
                        :placeholder="$t('pa_all')"
                        :disabled="busy"
                        @update:model-value="refresh"
                /></ZFFormField>
            </div>
            <div class="admin-table-scroll">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>{{ $t('organization') }}</th>
                            <th>{{ $t('location') }}</th>
                            <th>{{ $t('pa_import_id') }}</th>
                            <th>{{ $t('status') }}</th>
                            <th>{{ $t('pa_date') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="row in imports.data" :key="row.public_id">
                            <td>{{ row.organization }}</td>
                            <td>{{ row.location }}</td>
                            <td>
                                <span class="font-mono text-xs">{{ row.public_id }}</span
                                ><small class="muted block">{{ row.provider }}</small>
                            </td>
                            <td>
                                <span class="admin-badge" :class="row.status === 'failed' ? 'warning' : ''">{{
                                    $t('pa_import_' + row.status)
                                }}</span>
                            </td>
                            <td>{{ adminDate(row.created_at) }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <p v-if="!imports.data.length" class="admin-empty">{{ $t('pa_empty') }}</p>
            <AdminPagination :pagination="imports" />
        </section>
        <p class="muted mt-5 text-xs">{{ $t('pa_snapshot') }} {{ adminDateTime(updatedAt) }} · UTC</p></AdminLayout
    >
</template>
