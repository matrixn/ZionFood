<script setup lang="ts">
import { computed, reactive, ref, watch } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import Select from 'primevue/select';
import Button from 'primevue/button';
import AdminLayout from '@/layouts/AdminLayout.vue';
import ZFPageHeader from '@/components/ui/ZFPageHeader.vue';
import ZFFormField from '@/components/ui/ZFFormField.vue';
import { localeTag } from '@/i18n';
type FilterKey = 'utm_source' | 'utm_medium' | 'utm_campaign';
type TagKey = FilterKey | 'utm_content' | 'utm_term' | 'utm_id';
type Tags = Record<TagKey, string | null>;
interface Visit extends Tags {
    public_id: string;
    created_at: string;
    landing_path: string;
    locale: string;
    leads_count: number;
}
interface RankedCampaign {
    utm_id: string | null;
    utm_source: string | null;
    utm_medium: string | null;
    utm_campaign: string | null;
    visits: number;
    leads: number;
}
const props = defineProps<{
    visits: { data: Visit[]; current_page: number; last_page: number; total: number };
    summary: Record<'visits' | 'campaigns' | 'leads' | 'qualified', number>;
    topCampaigns: RankedCampaign[];
    options: Record<FilterKey, string[]>;
    filters: { days: number; utm_source?: string; utm_medium?: string; utm_campaign?: string };
}>();
const filterKeys: FilterKey[] = ['utm_source', 'utm_medium', 'utm_campaign'];
const tagKeys: TagKey[] = [...filterKeys, 'utm_content', 'utm_term', 'utm_id'];
const filter = reactive({
    days: 30,
    utm_source: null as string | null,
    utm_medium: null as string | null,
    utm_campaign: null as string | null,
});
watch(
    () => props.filters,
    (value) =>
        Object.assign(filter, {
            days: value.days,
            utm_source: value.utm_source ?? null,
            utm_medium: value.utm_medium ?? null,
            utm_campaign: value.utm_campaign ?? null,
        }),
    { immediate: true },
);
const busy = ref(false);
const maxVisits = computed(() => Math.max(1, ...props.topCampaigns.map((row) => Number(row.visits))));
function query(page?: number) {
    const params: Record<string, string | number> = { days: filter.days };
    for (const key of filterKeys) if (filter[key]) params[key] = filter[key]!;
    if (page) params.page = page;
    return params;
}
function apply() {
    router.get('/admin/campaigns', query(), {
        preserveState: true,
        preserveScroll: true,
        onStart: () => (busy.value = true),
        onFinish: () => (busy.value = false),
    });
}
function clear() {
    Object.assign(filter, { days: 30, utm_source: null, utm_medium: null, utm_campaign: null });
    apply();
}
function pageUrl(page: number) {
    return (
        '/admin/campaigns?' +
        new URLSearchParams(Object.entries(query(page)).map(([key, value]) => [key, String(value)])).toString()
    );
}
function date(value: string) {
    return new Intl.DateTimeFormat(localeTag(), {
        dateStyle: 'medium',
        timeStyle: 'short',
        timeZone: 'Europe/Bucharest',
    }).format(new Date(value));
}
</script>
<template>
    <AdminLayout
        ><Head :title="$t('campaigns_title')" /><ZFPageHeader
            :title="$t('campaigns_title')"
            :description="$t('campaigns_description')"
        />
        <p
            class="mb-6 rounded-xl border border-violet-100 bg-violet-50 p-4 text-sm leading-relaxed text-violet-900 dark:border-violet-900 dark:bg-violet-950 dark:text-violet-100"
        >
            {{ $t('campaigns_scope') }}
        </p>
        <form class="panel mb-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4" @submit.prevent="apply">
            <ZFFormField
                :label="$t('campaigns_period')"
                :help="$t('campaigns_period_help')"
                for="campaign-period"
                class="min-w-0"
                ><Select
                    v-model="filter.days"
                    input-id="campaign-period"
                    :options="[7, 30, 90].map((value) => ({ value, label: $t('campaigns_days', { days: value }) }))"
                    option-label="label"
                    option-value="value"
                    :disabled="busy"
                    :aria-label="$t('campaigns_period')"
            /></ZFFormField>
            <ZFFormField
                v-for="key in filterKeys"
                :key="key"
                :label="$t('campaigns_' + key)"
                :help="$t('campaigns_' + key + '_help')"
                :for="'campaign-filter-' + key"
                class="min-w-0"
                ><Select
                    v-model="filter[key]"
                    :input-id="'campaign-filter-' + key"
                    :options="options[key]"
                    show-clear
                    :placeholder="$t('campaigns_all')"
                    :disabled="busy"
                    :aria-label="$t('campaigns_' + key)"
            /></ZFFormField>
            <div class="flex items-end justify-end gap-2 sm:col-span-2 xl:col-span-4">
                <Button type="submit" :label="$t('campaigns_apply')" :loading="busy" /><Button
                    icon="pi pi-filter-slash"
                    text
                    :aria-label="$t('campaigns_clear')"
                    :disabled="busy"
                    @click="clear"
                />
            </div>
        </form>
        <div class="mb-7 grid grid-cols-2 gap-3 lg:grid-cols-4">
            <div
                v-for="metric in ['visits', 'leads', 'qualified', 'campaigns'] as const"
                :key="metric"
                :data-testid="'campaign-metric-' + metric"
                class="panel !p-4"
            >
                <div class="flex items-start gap-2">
                    <p class="text-xs font-medium text-slate-500">{{ $t('campaigns_metric_' + metric) }}</p>
                    <FieldHelp
                        :label="$t('campaigns_metric_' + metric)"
                        :text="$t('campaigns_metric_' + metric + '_help')"
                    />
                </div>
                <strong class="mt-3 block text-3xl">{{ summary[metric].toLocaleString(localeTag()) }}</strong>
            </div>
        </div>
        <section v-if="topCampaigns.length" class="panel mb-7">
            <h2 class="text-lg font-semibold">{{ $t('campaigns_top') }}</h2>
            <p class="mt-1 text-xs text-slate-500">{{ $t('campaigns_top_help') }}</p>
            <div class="mt-5 grid gap-5 lg:grid-cols-2">
                <article v-for="(row, index) in topCampaigns" :key="index" class="min-w-0">
                    <div class="flex items-start justify-between gap-4">
                        <div class="min-w-0">
                            <h3 class="break-words text-sm font-semibold">
                                {{ row.utm_campaign || row.utm_id || $t('campaigns_unspecified') }}
                            </h3>
                            <p class="mt-1 break-words text-xs text-slate-500">
                                {{ row.utm_source || $t('campaigns_unspecified') }} ·
                                {{ row.utm_medium || $t('campaigns_unspecified') }}
                            </p>
                        </div>
                        <div class="shrink-0 text-right text-sm">
                            <strong class="text-brand">{{ $t('campaigns_leads_short') }}: {{ row.leads }}</strong>
                            <p class="mt-1 text-xs text-slate-500">
                                {{ $t('campaigns_visits_short') }}: {{ row.visits }}
                            </p>
                        </div>
                    </div>
                    <div class="mt-3 h-1.5 overflow-hidden rounded-full bg-slate-100 dark:bg-slate-800">
                        <div
                            class="h-full rounded-full bg-violet-400"
                            :style="{ width: `${(Number(row.visits) / maxVisits) * 100}%` }"
                        />
                    </div>
                </article>
            </div>
        </section>
        <div class="mb-4 flex flex-wrap items-end justify-between gap-3">
            <h2 class="text-lg font-semibold">{{ $t('campaigns_log') }}</h2>
            <p class="text-xs text-slate-500">{{ $t('campaigns_local_time') }}</p>
        </div>
        <p v-if="!visits.data.length" class="panel text-center text-slate-500">{{ $t('campaigns_empty') }}</p>
        <div class="grid gap-4 xl:grid-cols-2">
            <article
                v-for="visit in visits.data"
                :key="visit.public_id"
                class="panel min-w-0 !p-5"
                :data-visit-id="visit.public_id"
            >
                <div
                    class="flex flex-wrap items-start justify-between gap-3 border-b border-slate-100 pb-3 dark:border-slate-700"
                >
                    <div>
                        <p class="text-sm font-semibold">{{ date(visit.created_at) }}</p>
                        <p class="mt-1 text-xs text-slate-500">
                            {{ visit.landing_path }} · {{ visit.locale === 'ro' ? 'Română' : 'English' }}
                        </p>
                    </div>
                    <span class="rounded-full bg-slate-100 px-2 py-1 text-xs text-slate-500 dark:bg-slate-800">{{
                        $t('campaigns_visit')
                    }}</span>
                </div>
                <dl class="my-4 grid grid-cols-2 gap-3 sm:grid-cols-3">
                    <div v-for="key in tagKeys" :key="key" class="min-w-0">
                        <dt class="text-[11px] font-medium text-slate-400">{{ key }}</dt>
                        <dd
                            class="mt-1 break-words text-sm"
                            :class="visit[key] ? 'text-slate-700 dark:text-slate-200' : 'text-slate-400'"
                        >
                            {{ visit[key] || '—' }}
                        </dd>
                    </div>
                </dl>
                <Link
                    v-if="visit.leads_count"
                    :href="`/admin/leads?campaign_visit=${visit.public_id}`"
                    class="inline-flex items-center gap-2 rounded-lg bg-violet-50 px-3 py-2 text-sm font-medium text-brand dark:bg-violet-950"
                    >{{ $t('campaigns_view_leads', { count: visit.leads_count }) }}
                    <i class="pi pi-arrow-up-right" aria-hidden="true"
                /></Link>
                <p v-else class="text-xs text-slate-500">{{ $t('campaigns_no_lead') }}</p>
            </article>
        </div>
        <nav
            v-if="visits.last_page > 1"
            :aria-label="$t('campaigns_pagination')"
            class="mt-6 flex items-center justify-center gap-4"
        >
            <Link v-if="visits.current_page > 1" :href="pageUrl(visits.current_page - 1)" class="text-brand">{{
                $t('leads_previous')
            }}</Link
            ><span class="text-sm text-slate-500">{{ visits.current_page }} / {{ visits.last_page }}</span
            ><Link
                v-if="visits.current_page < visits.last_page"
                :href="pageUrl(visits.current_page + 1)"
                class="text-brand"
                >{{ $t('leads_next') }}</Link
            >
        </nav>
    </AdminLayout>
</template>

<style scoped>
form :deep(.p-select) {
    width: 100%;
    min-width: 0;
}
form :deep(.p-select-label) {
    min-width: 0;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
</style>
