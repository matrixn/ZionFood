<script setup lang="ts">
import { computed, ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import Button from 'primevue/button';
import Select from 'primevue/select';
import AdminLayout from '@/layouts/AdminLayout.vue';
import AdminMetric from '@/components/ui/AdminMetric.vue';
import ZFFormField from '@/components/ui/ZFFormField.vue';
import { adminDate, adminDateTime, adminMoney, attentionLinks } from '@/lib/platform';
const props = defineProps<{
    overview: {
        days: number;
        from: string;
        to: string;
        updated_at: string;
        timezone: string;
        totals: Record<string, number>;
        values: { currency: string; value: number; count: number }[];
        attention: Record<string, number>;
        timeline: { day: string; orders: number; leads: number; visits: number }[];
        recent: { public_id: string; name: string; created_at: string }[];
        subscriptions: { public_id: string; name: string; trial_ends_at: string }[];
    };
}>();
const busy = ref(false);
const metric = ref<'orders' | 'leads' | 'visits'>('orders');
const seriesKeys = ['orders', 'leads', 'visits'] as const;
const attention = computed(() => Object.entries(props.overview.attention).filter(([, count]) => count > 0));
const maximum = computed(() => Math.max(1, ...props.overview.timeline.map((d) => d[metric.value])));
const points = computed(() =>
    props.overview.timeline
        .map(
            (row, i, all) =>
                `${(i / Math.max(1, all.length - 1)) * 900},${170 - (row[metric.value] / maximum.value) * 150}`,
        )
        .join(' '),
);
function refresh(days = props.overview.days) {
    router.get(
        '/admin',
        { days },
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
        ><Head :title="$t('pa_dashboard')" />
        <div class="mb-7 flex flex-wrap items-end justify-between gap-4">
            <div>
                <p class="admin-eyebrow">{{ $t('pa_platform_pulse') }}</p>
                <h1 class="mt-2 text-3xl font-semibold tracking-tight">{{ $t('pa_dashboard') }}</h1>
                <p class="muted mt-2 max-w-2xl">{{ $t('pa_dashboard_description') }}</p>
            </div>
            <div class="flex items-end gap-3">
                <ZFFormField for="admin-period" :label="$t('pa_period')" :help="$t('pa_period_help')"
                    ><Select
                        input-id="admin-period"
                        :aria-label="$t('pa_period')"
                        :model-value="overview.days"
                        :options="[7, 30, 90].map((value) => ({ value, label: $t('pa_days', { days: value }) }))"
                        option-label="label"
                        option-value="value"
                        :disabled="busy"
                        @update:model-value="refresh" /></ZFFormField
                ><Button icon="pi pi-refresh" outlined :loading="busy" :aria-label="$t('refresh')" @click="refresh()" />
            </div>
        </div>
        <section class="admin-hero mb-6">
            <div>
                <span class="text-xs font-semibold uppercase tracking-widest text-indigo-200">{{
                    $t('pa_completed_value')
                }}</span>
                <div class="mt-3 flex flex-wrap gap-x-8 gap-y-3">
                    <strong
                        v-for="row in overview.values"
                        :key="row.currency"
                        class="text-3xl font-semibold tracking-tight sm:text-4xl"
                        >{{ adminMoney(row.value, row.currency) }}</strong
                    ><strong v-if="!overview.values.length" class="text-3xl">—</strong>
                </div>
                <p class="mt-3 max-w-2xl text-sm text-indigo-100">{{ $t('pa_value_help') }}</p>
                <p class="mt-3 text-xs text-indigo-200">
                    {{ adminDate(overview.from) }} — {{ adminDate(overview.to) }} · UTC
                </p>
            </div>
            <div class="flex gap-8">
                <div>
                    <strong class="block text-3xl">{{ overview.totals.completed }}</strong
                    ><span class="text-sm text-indigo-100">{{ $t('pa_completed') }}</span>
                </div>
                <div>
                    <strong class="block text-3xl">{{ overview.totals.active_orders }}</strong
                    ><span class="text-sm text-indigo-100">{{ $t('pa_active_orders') }}</span>
                </div>
            </div>
        </section>
        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <AdminMetric
                :value="overview.totals.organizations"
                :label="$t('pa_organizations')"
                :help="$t('pa_org_help')"
                icon="pi-building"
                href="/admin/organizations"
                :detail="
                    $t('pa_estate', { restaurants: overview.totals.restaurants, locations: overview.totals.locations })
                "
            />
            <AdminMetric
                :value="overview.totals.users"
                :label="$t('pa_users')"
                :help="$t('pa_users_help')"
                icon="pi-users"
                href="/admin/users"
                :detail="$t('pa_all_time')"
            />
            <AdminMetric
                :value="overview.totals.leads"
                :label="$t('pa_leads')"
                :help="$t('pa_leads_help')"
                icon="pi-inbox"
                href="/admin/leads"
                :detail="$t('pa_awaiting', { count: overview.totals.new_leads })"
            />
            <AdminMetric
                :value="overview.totals.visits"
                :label="$t('pa_visits')"
                :help="$t('pa_visits_help')"
                icon="pi-chart-line"
                href="/admin/campaigns"
                :detail="$t('pa_selected_period')"
            />
        </div>
        <div class="mt-6 grid gap-6 xl:grid-cols-[minmax(0,1.7fr)_minmax(280px,1fr)]">
            <section class="panel min-w-0">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h2 class="admin-section-title">{{ $t('pa_activity') }}</h2>
                        <p class="muted mt-1">{{ $t('pa_activity_help') }}</p>
                    </div>
                    <div class="flex gap-1" :aria-label="$t('pa_activity')">
                        <button
                            v-for="key in seriesKeys"
                            :key="key"
                            type="button"
                            class="admin-segment"
                            :aria-pressed="metric === key"
                            @click="metric = key"
                        >
                            {{ $t('pa_series_' + key) }}
                        </button>
                    </div>
                </div>
                <div class="mt-7 flex justify-between text-xs text-slate-500">
                    <span>0 — {{ maximum }}</span
                    ><span>{{ $t('pa_series_' + metric) }}</span>
                </div>
                <svg
                    viewBox="0 0 900 185"
                    class="mt-3 h-44 w-full overflow-visible"
                    preserveAspectRatio="none"
                    role="img"
                    :aria-label="$t('pa_series_' + metric)"
                >
                    <line
                        v-for="y in [20, 70, 120, 170]"
                        :key="y"
                        x1="0"
                        :y1="y"
                        x2="900"
                        :y2="y"
                        stroke="currentColor"
                        class="text-slate-200 dark:text-slate-800"
                        stroke-dasharray="4 5"
                    />
                    <polygon :points="'0,180 ' + points + ' 900,180'" fill="currentColor" class="text-indigo-500/10" />
                    <polyline
                        :points="points"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="3"
                        vector-effect="non-scaling-stroke"
                        class="text-indigo-500"
                    />
                </svg>
                <div class="mt-3 flex justify-between text-xs text-slate-500">
                    <span>{{ adminDate(overview.from) }}</span
                    ><span>{{ adminDate(overview.to) }}</span>
                </div>
                <details class="mt-5 text-sm">
                    <summary class="cursor-pointer text-brand">{{ $t('pa_view_chart_data') }}</summary>
                    <div class="admin-table-scroll mt-3 max-h-64">
                        <table class="admin-table">
                            <thead>
                                <tr>
                                    <th>{{ $t('pa_date') }}</th>
                                    <th v-for="key in seriesKeys" :key="key">{{ $t('pa_series_' + key) }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="row in overview.timeline" :key="row.day">
                                    <td>{{ adminDate(row.day) }}</td>
                                    <td v-for="key in seriesKeys" :key="key">{{ row[key] }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </details>
            </section>
            <section class="panel">
                <div class="flex items-center justify-between">
                    <h2 class="admin-section-title">{{ $t('pa_attention') }}</h2>
                    <FieldHelp :label="$t('pa_attention')" :text="$t('pa_attention_help')" />
                </div>
                <p class="muted mt-2 mb-4">{{ $t('pa_attention_help') }}</p>
                <div v-if="!attention.length" class="admin-empty">
                    <i class="pi pi-check-circle text-2xl text-emerald-600" aria-hidden="true" />
                    <p class="mt-2">{{ $t('pa_no_failures') }}</p>
                </div>
                <Link v-for="[key, count] in attention" :key="key" :href="attentionLinks[key]" class="admin-alert-row"
                    ><span
                        ><i class="pi pi-exclamation-circle mr-2 text-amber-600" aria-hidden="true" />{{
                            $t('pa_problem_' + key)
                        }}</span
                    ><strong>{{ count }}</strong></Link
                ><Link
                    href="/admin/operations"
                    class="mt-5 inline-flex items-center gap-2 text-sm font-semibold text-brand"
                    >{{ $t('pa_open_monitoring') }} <i class="pi pi-arrow-right" aria-hidden="true"
                /></Link>
            </section>
        </div>
        <div class="mt-6 grid gap-6 xl:grid-cols-3">
            <section class="panel">
                <h2 class="admin-section-title">{{ $t('pa_recent_clients') }}</h2>
                <p v-if="!overview.recent.length" class="admin-empty">{{ $t('pa_empty') }}</p>
                <Link
                    v-for="row in overview.recent"
                    :key="row.public_id"
                    :href="'/admin/organizations/' + row.public_id"
                    class="admin-list-row"
                    ><span class="min-w-0 truncate font-medium">{{ row.name }}</span
                    ><span class="text-xs text-slate-500">{{ adminDate(row.created_at) }}</span></Link
                >
            </section>
            <section class="panel">
                <div class="flex items-center justify-between">
                    <h2 class="admin-section-title">{{ $t('pa_expiring') }}</h2>
                    <FieldHelp :label="$t('pa_expiring')" :text="$t('pa_trial_help')" />
                </div>
                <p class="muted mt-2">
                    {{ $t('pa_trial_counts', { active: overview.totals.trials, expiring: overview.totals.expiring }) }}
                </p>
                <p v-if="!overview.subscriptions.length" class="admin-empty">{{ $t('pa_empty') }}</p>
                <Link
                    v-for="row in overview.subscriptions"
                    :key="row.public_id"
                    :href="'/admin/organizations/' + row.public_id"
                    class="admin-list-row"
                    ><span class="min-w-0 truncate font-medium">{{ row.name }}</span
                    ><span class="text-xs text-amber-700 dark:text-amber-300">{{
                        adminDate(row.trial_ends_at)
                    }}</span></Link
                >
            </section>
            <section class="panel">
                <h2 class="admin-section-title">{{ $t('pa_shortcuts') }}</h2>
                <Link href="/admin/leads?status=new" class="admin-shortcut"
                    ><i class="pi pi-inbox" aria-hidden="true" /><span>{{ $t('pa_handle_leads') }}</span
                    ><i class="pi pi-arrow-up-right" aria-hidden="true" /></Link
                ><Link href="/admin/emails" class="admin-shortcut"
                    ><i class="pi pi-send" aria-hidden="true" /><span>{{ $t('pa_emails') }}</span
                    ><i class="pi pi-arrow-up-right" aria-hidden="true" /></Link
                ><Link href="/admin/menu-ai" class="admin-shortcut"
                    ><i class="pi pi-sparkles" aria-hidden="true" /><span>{{ $t('pa_ai') }}</span
                    ><i class="pi pi-arrow-up-right" aria-hidden="true"
                /></Link>
                <p class="muted mt-3">{{ $t('pa_reservations', { count: overview.totals.reservations }) }}</p>
            </section>
        </div>
        <p class="muted mt-6 text-xs">{{ $t('pa_snapshot') }} {{ adminDateTime(overview.updated_at) }} · UTC</p>
    </AdminLayout>
</template>
