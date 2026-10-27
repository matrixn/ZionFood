<script setup lang="ts">
import { computed, ref, watch, onMounted, onBeforeUnmount } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AdminLayout from '@/layouts/AdminLayout.vue';
import Button from 'primevue/button';
import Select from 'primevue/select';
import InputText from 'primevue/inputtext';
import InputNumber from 'primevue/inputnumber';
import Dialog from 'primevue/dialog';
import { api } from '@/lib/http';
import { useLocationDates } from '@/lib/locationDates';
import { tr as t } from '@/i18n';
interface Message {
    public_id: string;
    source: string;
    state: string;
    mailer: string | null;
    initiator_kind: string;
    initiator_name: string | null;
    attempts: number;
    reason: string | null;
    provider_email_id: string | null;
    created_at: string;
    available_at: string | null;
    expires_at: string | null;
    accepted_at: string | null;
    subject: string | null;
    recipients: Record<string, string[]>;
}
interface Provider {
    daily_limit: number;
    reserved: number;
    utc_day: string;
    reason: string | null;
    paused_until: string | null;
    reset_at: string;
    revision: number;
    usage: {
        daily: { used: number; limit: number | null; resets_at: string };
        monthly: { used: number; limit: number | null; resets_at: string };
    } | null;
    usage_checked_at: string | null;
    usage_error: string | null;
}
interface Data {
    messages: { data: Message[]; current_page: number; last_page: number; total: number };
    summary: Record<string, number>;
    provider: Provider;
    application_limit: number;
    mailers: string[];
    filters: Record<string, string | number | null>;
}
const props = defineProps<{ initial: Data }>();
const data = ref(props.initial);
watch(
    () => props.initial,
    (value) => (data.value = value),
);
const fields = ['source', 'state', 'initiator', 'mailer', 'recipient', 'from', 'to'] as const;
const filters = ref(
    Object.fromEntries(fields.map((key) => [key, props.initial.filters[key] ?? null])) as Record<
        (typeof fields)[number],
        string | null
    >,
);
const sources = [
    'system_error',
    'account_registration',
    'account_verification',
    'password_reset',
    'staff_invitation',
    'reservation',
    'email_test',
    'other',
    'legacy',
];
const states = [
    'notification_queued',
    'queued',
    'processing',
    'deferred',
    'accepted',
    'failed',
    'suppressed',
    'expired',
    'superseded',
    'local',
];
const kinds = ['system', 'user', 'guest', 'unknown'];
const options = (items: string[], prefix: string) => items.map((value) => ({ value, label: t(prefix + value) }));
const dates = useLocationDates();
const busy = ref(false);
const error = ref('');
const notice = ref('');
const loading = ref(false);
const selected = ref<Message | null>(null);
const dailyLimit = ref(data.value.provider.daily_limit);
const settingsRevision = ref(data.value.provider.revision);
const queued = computed(() =>
    ['notification_queued', 'queued', 'processing', 'deferred'].reduce(
        (sum, key) => sum + Number(data.value.summary[key] ?? 0),
        0,
    ),
);
function apply(page = 1) {
    router.get(
        '/admin/emails',
        { ...Object.fromEntries(Object.entries(filters.value).filter(([, v]) => !!v)), page },
        { preserveState: true, preserveScroll: true, replace: true },
    );
}
async function refresh() {
    if (busy.value || loading.value) return;
    loading.value = true;
    try {
        const query = new URLSearchParams(
            Object.entries(props.initial.filters)
                .filter(([, v]) => v !== null && v !== '')
                .map(([k, v]) => [k, String(v)]),
        );
        data.value = await api<Data>('/admin/emails/data?' + query);
        if (selected.value)
            selected.value =
                data.value.messages.data.find((m) => m.public_id === selected.value!.public_id) ?? selected.value;
    } catch (e) {
        error.value = (e as Error).message;
    } finally {
        loading.value = false;
    }
}
async function operation(kind: 'usage' | 'index' | 'settings') {
    busy.value = true;
    error.value = '';
    notice.value = '';
    try {
        if (kind === 'settings') {
            const result = await api<{ provider: Provider }>('/admin/emails/provider', 'PUT', {
                daily_limit: dailyLimit.value,
                revision: settingsRevision.value,
            });
            data.value.provider = result.provider;
            settingsRevision.value = result.provider.revision;
            notice.value = t('mail_settings_saved');
        } else if (kind === 'usage') {
            const result = await api<{ provider: Provider }>('/admin/emails/provider/usage', 'POST');
            data.value.provider = result.provider;
            notice.value = t(
                result.provider.usage_error ? 'mail_usage_error_' + result.provider.usage_error : 'mail_usage_updated',
            );
        } else {
            const result = await api<{ scanned: number; indexed: number; truncated: boolean }>(
                '/admin/emails/index-queue',
                'POST',
            );
            notice.value = t(result.truncated ? 'mail_index_partial' : 'mail_index_done', { count: result.indexed });
        }
    } catch (e) {
        error.value = (e as Error).message;
    } finally {
        busy.value = false;
        await refresh();
    }
}
const tone = (state: string) =>
    state === 'accepted'
        ? 'bg-emerald-50 text-emerald-800'
        : state === 'failed'
          ? 'bg-rose-50 text-rose-800'
          : state === 'deferred'
            ? 'bg-amber-50 text-amber-900'
            : 'bg-slate-100 text-slate-600';
let timer: ReturnType<typeof setInterval>;
onMounted(() => {
    timer = setInterval(refresh, 15000);
});
onBeforeUnmount(() => clearInterval(timer));
</script>
<template>
    <AdminLayout
        ><Head :title="$t('mail_admin_title')" />
        <div class="space-y-6">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <p class="mb-2 text-xs font-semibold uppercase tracking-widest text-brand">
                        {{ $t('platform_admin') }}
                    </p>
                    <h1 class="text-3xl font-semibold">{{ $t('mail_admin_title') }}</h1>
                    <p class="muted mt-2 max-w-3xl">{{ $t('mail_admin_intro') }}</p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <Button
                        :label="$t('mail_refresh')"
                        icon="pi pi-refresh"
                        outlined
                        :loading="loading"
                        @click="refresh"
                    /><Link href="/admin/email-alerts" class="rounded-xl border px-4 py-3 text-sm text-brand">{{
                        $t('alerts_title')
                    }}</Link>
                </div>
            </div>
            <p v-if="error" role="alert" class="rounded-xl bg-rose-50 p-4 text-rose-800">{{ error }}</p>
            <p v-if="notice" role="status" class="rounded-xl bg-violet-50 p-4 text-brand">{{ notice }}</p>
            <div class="grid gap-4 sm:grid-cols-3">
                <div class="panel">
                    <p class="muted text-sm">{{ $t('mail_waiting') }}</p>
                    <strong class="mt-2 block text-3xl">{{ queued }}</strong>
                </div>
                <div class="panel">
                    <p class="muted text-sm">{{ $t('mail_state_accepted') }}</p>
                    <strong class="mt-2 block text-3xl text-emerald-600">{{ data.summary.accepted ?? 0 }}</strong>
                </div>
                <div class="panel">
                    <p class="muted text-sm">{{ $t('mail_state_failed') }}</p>
                    <strong class="mt-2 block text-3xl text-rose-600">{{ data.summary.failed ?? 0 }}</strong>
                </div>
            </div>
            <section class="panel space-y-4">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <h2 class="text-xl font-semibold">{{ $t('mail_resend_quota') }}</h2>
                    <Button
                        :label="$t('mail_sync_usage')"
                        icon="pi pi-cloud-download"
                        outlined
                        size="small"
                        :disabled="busy"
                        :loading="busy"
                        @click="operation('usage')"
                    />
                </div>
                <div v-if="data.provider.reason" class="rounded-xl bg-amber-50 p-4 text-amber-900" role="status">
                    <strong>{{ $t('mail_provider_paused') }}</strong>
                    <p class="mt-1 text-sm">{{ $t('mail_reason_' + data.provider.reason) }}</p>
                    <p class="mt-2 text-sm">
                        {{
                            data.provider.paused_until
                                ? $t('mail_resume_at') + ': ' + dates.dateTime(data.provider.paused_until)
                                : $t('mail_monthly_unknown')
                        }}
                    </p>
                </div>
                <div class="grid gap-4 text-sm sm:grid-cols-3">
                    <div>
                        <p class="muted">
                            {{ $t('mail_local_reserved')
                            }}<FieldHelp :label="$t('mail_local_reserved')" :text="$t('mail_local_reserved_help')" />
                        </p>
                        <strong class="text-xl">{{ data.provider.reserved }} / {{ data.provider.daily_limit }}</strong>
                    </div>
                    <div>
                        <p class="muted">{{ $t('mail_provider_observed') }}</p>
                        <strong class="text-xl">{{
                            data.provider.usage
                                ? data.provider.usage.daily.used + ' / ' + (data.provider.usage.daily.limit ?? '∞')
                                : '—'
                        }}</strong>
                        <p v-if="data.provider.usage_checked_at" class="muted text-xs">
                            {{ dates.dateTime(data.provider.usage_checked_at) }}
                        </p>
                    </div>
                    <div>
                        <p class="muted">{{ $t('mail_next_reset') }}</p>
                        <strong>{{ dates.dateTime(data.provider.reset_at) }}</strong>
                        <p class="muted text-xs">{{ $t('mail_utc_reset') }}</p>
                    </div>
                </div>
                <p v-if="data.provider.usage_error" class="text-sm text-amber-800">
                    {{ $t('mail_usage_error_' + data.provider.usage_error) }}
                </p>
                <form
                    class="flex flex-wrap items-end gap-3 border-t border-slate-100 pt-4"
                    @submit.prevent="operation('settings')"
                >
                    <label class="field"
                        ><span class="field-label"
                            >{{ $t('mail_daily_guard')
                            }}<FieldHelp :label="$t('mail_daily_guard')" :text="$t('mail_daily_guard_help')" /></span
                        ><InputNumber
                            v-model="dailyLimit"
                            :aria-label="$t('mail_daily_guard')"
                            :min="1"
                            :max="100000" /></label
                    ><Button type="submit" :label="$t('save')" :disabled="busy" />
                    <p class="muted max-w-xl text-xs">
                        {{ $t('mail_application_quota', { limit: data.application_limit }) }}
                    </p>
                </form>
            </section>
            <form class="panel grid items-end gap-4 sm:grid-cols-2 xl:grid-cols-4" @submit.prevent="apply()">
                <label class="field"
                    ><span class="field-label"
                        >{{ $t('mail_filter_source')
                        }}<FieldHelp :label="$t('mail_filter_source')" :text="$t('mail_filters_help')" /></span
                    ><Select
                        v-model="filters.source"
                        :options="options(sources, 'mail_source_')"
                        option-label="label"
                        option-value="value"
                        show-clear
                        :placeholder="$t('mail_all')"
                        :aria-label="$t('mail_filter_source')"
                /></label>
                <label class="field"
                    ><span class="field-label"
                        >{{ $t('mail_filter_state')
                        }}<FieldHelp :label="$t('mail_filter_state')" :text="$t('mail_filters_help')" /></span
                    ><Select
                        v-model="filters.state"
                        :options="options(states, 'mail_state_')"
                        option-label="label"
                        option-value="value"
                        show-clear
                        :placeholder="$t('mail_all')"
                        :aria-label="$t('mail_filter_state')"
                /></label>
                <label class="field"
                    ><span class="field-label"
                        >{{ $t('mail_filter_initiator')
                        }}<FieldHelp :label="$t('mail_filter_initiator')" :text="$t('mail_filters_help')" /></span
                    ><Select
                        v-model="filters.initiator"
                        :options="options(kinds, 'mail_initiator_')"
                        option-label="label"
                        option-value="value"
                        show-clear
                        :placeholder="$t('mail_all')"
                        :aria-label="$t('mail_filter_initiator')"
                /></label>
                <label class="field"
                    ><span class="field-label"
                        >{{ $t('mail_filter_mailer')
                        }}<FieldHelp :label="$t('mail_filter_mailer')" :text="$t('mail_filters_help')" /></span
                    ><Select
                        v-model="filters.mailer"
                        :options="data.mailers"
                        show-clear
                        :placeholder="$t('mail_all')"
                        :aria-label="$t('mail_filter_mailer')"
                /></label>
                <label class="field"
                    ><span class="field-label"
                        >{{ $t('mail_recipient')
                        }}<FieldHelp :label="$t('mail_recipient')" :text="$t('mail_recipient_help')" /></span
                    ><InputText v-model="filters.recipient" type="email" :aria-label="$t('mail_recipient')"
                /></label>
                <label class="field"
                    ><span class="field-label"
                        >{{ $t('mail_date_from')
                        }}<FieldHelp :label="$t('mail_date_from')" :text="$t('mail_dates_help')" /></span
                    ><input
                        v-model="filters.from"
                        type="date"
                        :aria-label="$t('mail_date_from')"
                        class="rounded-lg border border-slate-300 p-3"
                /></label>
                <label class="field"
                    ><span class="field-label"
                        >{{ $t('mail_date_to')
                        }}<FieldHelp :label="$t('mail_date_to')" :text="$t('mail_dates_help')" /></span
                    ><input
                        v-model="filters.to"
                        type="date"
                        :aria-label="$t('mail_date_to')"
                        class="rounded-lg border border-slate-300 p-3"
                /></label>
                <Button type="submit" :label="$t('mail_apply_filters')" icon="pi pi-filter" />
            </form>
            <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white dark:bg-slate-900">
                <div class="flex flex-wrap justify-between gap-3 border-b border-slate-100 p-4">
                    <p class="text-sm font-semibold">{{ $t('mail_results', { count: data.messages.total }) }}</p>
                    <Button
                        :label="$t('mail_index_queue')"
                        icon="pi pi-inbox"
                        text
                        size="small"
                        :disabled="busy"
                        @click="operation('index')"
                    />
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead class="bg-slate-50 text-xs uppercase text-slate-500 dark:bg-slate-800">
                            <tr>
                                <th class="p-4">{{ $t('mail_subject') }}</th>
                                <th class="p-4">{{ $t('mail_filter_source') }}</th>
                                <th class="p-4">{{ $t('mail_filter_state') }}</th>
                                <th class="p-4">{{ $t('mail_next_attempt') }}</th>
                                <th class="p-4">{{ $t('details') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <tr
                                v-for="message in data.messages.data"
                                :key="message.public_id"
                                data-testid="email-delivery-row"
                            >
                                <td class="min-w-64 p-4">
                                    <strong class="block">{{ message.subject ?? $t('mail_not_rendered') }}</strong>
                                    <p class="mt-1 text-xs text-slate-500">{{ message.recipients.to.join(', ') }}</p>
                                    <p class="mt-1 text-xs text-slate-400">{{ dates.dateTime(message.created_at) }}</p>
                                </td>
                                <td class="p-4">
                                    <p>{{ $t('mail_source_' + message.source) }}</p>
                                    <p class="mt-1 text-xs text-slate-400">
                                        {{ message.initiator_name ?? $t('mail_initiator_' + message.initiator_kind) }}
                                    </p>
                                </td>
                                <td class="p-4">
                                    <span
                                        class="inline-block whitespace-nowrap rounded-full px-3 py-1 text-xs font-medium"
                                        :class="tone(message.state)"
                                        >{{ $t('mail_state_' + message.state) }}</span
                                    >
                                    <p v-if="message.reason" class="mt-1 max-w-48 text-xs text-slate-500">
                                        {{ $t('mail_reason_' + message.reason) }}
                                    </p>
                                </td>
                                <td class="whitespace-nowrap p-4 text-xs">
                                    {{ message.available_at ? dates.dateTime(message.available_at) : '—' }}
                                </td>
                                <td class="p-4">
                                    <Button
                                        icon="pi pi-eye"
                                        text
                                        :aria-label="$t('mail_view_details')"
                                        @click="selected = message"
                                    />
                                </td>
                            </tr>
                            <tr v-if="!data.messages.data.length">
                                <td colspan="5" class="p-10 text-center text-slate-500">{{ $t('mail_empty') }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div class="flex items-center justify-between gap-3 border-t p-4">
                    <Button
                        :label="$t('previous')"
                        outlined
                        size="small"
                        :disabled="data.messages.current_page <= 1"
                        @click="apply(data.messages.current_page - 1)"
                    /><span class="text-xs text-slate-500"
                        >{{ data.messages.current_page }} / {{ data.messages.last_page }}</span
                    ><Button
                        :label="$t('next')"
                        outlined
                        size="small"
                        :disabled="data.messages.current_page >= data.messages.last_page"
                        @click="apply(data.messages.current_page + 1)"
                    />
                </div>
            </section>
            <p class="muted text-xs">{{ $t('mail_history_help') }}</p>
        </div>
        <Dialog
            :visible="!!selected"
            modal
            class="w-full max-w-2xl"
            :header="$t('mail_view_details')"
            @update:visible="!$event && (selected = null)"
            ><template v-if="selected"
                ><h2 class="mb-4 text-lg font-semibold">{{ selected.subject ?? $t('mail_not_rendered') }}</h2>
                <dl class="space-y-4 text-sm">
                    <div v-for="kind in ['to', 'cc', 'bcc']" :key="kind">
                        <dt class="text-xs uppercase text-slate-400">{{ kind }}</dt>
                        <dd class="break-all">{{ selected.recipients[kind]?.join(', ') || '—' }}</dd>
                    </div>
                    <div>
                        <dt class="muted">{{ $t('mail_filter_source') }}</dt>
                        <dd>{{ $t('mail_source_' + selected.source) }}</dd>
                    </div>
                    <div>
                        <dt class="muted">{{ $t('mail_attempts') }}</dt>
                        <dd>{{ selected.attempts }}</dd>
                    </div>
                    <div>
                        <dt class="muted">{{ $t('mail_provider_id') }}</dt>
                        <dd class="break-all font-mono text-xs">{{ selected.provider_email_id ?? '—' }}</dd>
                    </div>
                    <div v-if="selected.reason">
                        <dt class="muted">{{ $t('mail_filter_state') }}</dt>
                        <dd>{{ $t('mail_reason_' + selected.reason) }}</dd>
                    </div>
                    <div v-if="selected.expires_at">
                        <dt class="muted">{{ $t('mail_expires') }}</dt>
                        <dd>{{ dates.dateTime(selected.expires_at) }}</dd>
                    </div>
                </dl>
                <p class="muted mt-6 text-xs">{{ $t('mail_content_help') }}</p></template
            ><template #footer><Button :label="$t('close')" @click="selected = null" /></template
        ></Dialog>
    </AdminLayout>
</template>
