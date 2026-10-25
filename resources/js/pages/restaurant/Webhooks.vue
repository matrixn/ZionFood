<script setup lang="ts">
import { tr as t } from '@/i18n';

import { ref, onMounted, onUnmounted } from 'vue';
import { Head } from '@inertiajs/vue3';
import RestaurantLayout from '@/layouts/RestaurantLayout.vue';
import ZFPageHeader from '@/components/ui/ZFPageHeader.vue';
import ZFConfirmDialog from '@/components/ui/ZFConfirmDialog.vue';
import { useConfirm } from 'primevue/useconfirm';
import Button from 'primevue/button';
import InputText from 'primevue/inputtext';
import MultiSelect from 'primevue/multiselect';
import ToggleSwitch from 'primevue/toggleswitch';
import Dialog from 'primevue/dialog';
import Tag from 'primevue/tag';
import { api } from '@/lib/http';
import type { WorkspaceProps } from '@/types';
interface Endpoint {
    id: number;
    name: string;
    url: string;
    enabled: boolean;
    events: string[];
}
interface Delivery {
    id: number;
    endpoint_id: number;
    endpoint?: { name: string; deleted_at: string | null };
    event_id: string;
    status: string;
    attempts: number;
    http_status: number | null;
    failure_code: string | null;
    created_at: string;
    next_attempt_at: string | null;
}
const props = defineProps<WorkspaceProps>();
const confirm = useConfirm();
const endpoints = ref<Endpoint[]>([]);
const deliveries = ref<Delivery[]>([]);
const next = ref<string | null>(null);
const expandedHistory = ref(false);
const visible = ref(false);
const editing = ref<number | null>(null);
const busy = ref(false);
const error = ref('');
const form = ref({ name: '', url: '', enabled: false, events: ['order.created', 'order.updated'], secret: '' });
const events = ['order.created', 'order.updated', 'menu.updated', 'item.updated'];
const base = `/api/v1/locations/${props.location.public_id}/webhooks`;
let timer: ReturnType<typeof setInterval>;
async function load() {
    expandedHistory.value = false;
    try {
        const data = await api<{
            endpoints: Endpoint[];
            deliveries: { data: Delivery[]; next_page_url: string | null };
        }>(base);
        endpoints.value = data.endpoints;
        deliveries.value = data.deliveries.data;
        next.value = data.deliveries.next_page_url;
        error.value = '';
    } catch (e) {
        error.value = (e as Error).message;
    }
}
async function more() {
    if (!next.value) return;
    expandedHistory.value = true;
    try {
        const data = await api<{ deliveries: { data: Delivery[]; next_page_url: string | null } }>(next.value);
        deliveries.value.push(...data.deliveries.data);
        next.value = data.deliveries.next_page_url;
    } catch (e) {
        error.value = (e as Error).message;
    }
}
function open(endpoint?: Endpoint) {
    editing.value = endpoint?.id ?? null;
    form.value = {
        name: endpoint?.name ?? '',
        url: endpoint?.url ?? '',
        enabled: endpoint?.enabled ?? false,
        events: endpoint ? [...endpoint.events] : ['order.created', 'order.updated'],
        secret: '',
    };
    error.value = '';
    visible.value = true;
}
async function save() {
    busy.value = true;
    try {
        await api(base + (editing.value ? '/' + editing.value : ''), editing.value ? 'PUT' : 'POST', form.value);
        form.value.secret = '';
        visible.value = false;
        await load();
    } catch (e) {
        error.value = (e as Error).message;
    } finally {
        busy.value = false;
    }
}
function remove(endpoint: Endpoint) {
    confirm.require({
        header: t('remove_webhook'),
        message: t('remove_this_endpoint_and_cancel_its_pending_deliveries'),
        accept: async () => {
            try {
                await api(base + '/' + endpoint.id, 'DELETE');
                await load();
            } catch (e) {
                error.value = (e as Error).message;
            }
        },
    });
}
async function retry(delivery: Delivery) {
    try {
        await api(`/api/v1/locations/${props.location.public_id}/webhook-deliveries/${delivery.id}/retry`, 'POST');
        await load();
    } catch (e) {
        error.value = (e as Error).message;
    }
}
onMounted(() => {
    void load();
    timer = setInterval(() => {
        if (!visible.value && !expandedHistory.value) void load();
    }, 15000);
});
onUnmounted(() => clearInterval(timer));
</script>
<template>
    <RestaurantLayout v-bind="$props"
        ><Head :title="$t('webhooks')" /><ZFPageHeader
            :title="$t('events_that_reach_your_tools')"
            :description="$t('signed_event_notifications_delivery_history_and_controlled_retries')"
            ><Button :label="$t('add_webhook')" icon="pi pi-plus" @click="open()"
        /></ZFPageHeader>
        <p v-if="error && !visible" role="alert" class="mb-5 text-red-600">{{ error }}</p>
        <section class="panel mb-6">
            <h2 class="mb-4 text-xl font-semibold">{{ $t('webhook_endpoints') }}</h2>
            <p v-if="!endpoints.length" class="muted">{{ $t('no_webhook_endpoints_configured') }}</p>
            <article
                v-for="endpoint in endpoints"
                :key="endpoint.id"
                data-testid="webhook-endpoint"
                class="flex flex-wrap items-center justify-between gap-4 border-t border-slate-200 py-5"
            >
                <div class="min-w-0">
                    <strong>{{ endpoint.name }}</strong>
                    <p class="muted my-2 break-all">{{ endpoint.url }}</p>
                    <p class="muted">{{ endpoint.events.join(', ') }}</p>
                    <Tag
                        class="mt-2"
                        :severity="endpoint.enabled ? 'success' : 'secondary'"
                        :value="endpoint.enabled ? $t('webhook_enabled') : $t('webhook_disabled')"
                    />
                </div>
                <div class="flex gap-2">
                    <Button :label="$t('edit_webhook')" outlined @click="open(endpoint)" /><Button
                        :label="$t('remove_webhook')"
                        text
                        severity="danger"
                        @click="remove(endpoint)"
                    />
                </div>
            </article>
        </section>
        <section class="panel">
            <div class="mb-4 flex items-center justify-between">
                <h2 class="text-xl font-semibold">{{ $t('delivery_history') }}</h2>
                <Button :label="$t('refresh')" text icon="pi pi-refresh" @click="load" />
            </div>
            <p v-if="!deliveries.length" class="muted">
                {{ $t('no_deliveries_yet_new_matching_events_will_appear_here') }}
            </p>
            <div
                v-for="delivery in deliveries"
                :key="delivery.id"
                class="grid items-center gap-3 border-t border-slate-200 py-4 lg:grid-cols-[1fr_auto_auto]"
            >
                <div>
                    <strong>{{
                        endpoints.find((e) => e.id === delivery.endpoint_id)?.name ?? delivery.endpoint?.name
                    }}</strong>
                    <p class="muted text-xs">{{ delivery.event_id }}</p>
                    <p class="muted mt-2">
                        {{ $t('delivery_status_' + delivery.status) }} ·
                        {{ $t('attempts_count', { count: delivery.attempts })
                        }}<span v-if="delivery.http_status"> · HTTP {{ delivery.http_status }}</span>
                    </p>
                    <p v-if="delivery.failure_code" class="muted">
                        {{
                            $t(
                                'webhook_failure_' +
                                    (delivery.failure_code.startsWith('http_') ? 'http' : delivery.failure_code),
                            )
                        }}
                    </p>
                </div>
                <p class="muted">
                    {{ new Date(delivery.created_at).toLocaleString($i18n.locale === 'ro' ? 'ro-RO' : 'en-GB') }}
                </p>
                <Button
                    v-if="
                        ['failed', 'blocked', 'retrying'].includes(delivery.status) &&
                        endpoints.some((e) => e.id === delivery.endpoint_id && e.enabled)
                    "
                    :label="$t('retry_delivery')"
                    outlined
                    @click="retry(delivery)"
                />
            </div>
            <Button v-if="next" :label="$t('load_more_deliveries')" text class="mt-4" @click="more" />
        </section>
        <p class="muted mt-5">
            {{ $t('events_contain_public_identifiers_only_fetch_full_records_through_your_authorized_api_inte') }}
        </p>
        <Dialog
            v-model:visible="visible"
            :header="editing ? $t('edit_webhook') : $t('add_webhook')"
            modal
            class="w-full max-w-xl"
            @after-hide="
                form.secret = '';
                error = '';
            "
            ><form class="grid gap-5" @submit.prevent="save">
                <p v-if="error" role="alert" class="text-red-600">{{ error }}</p>
                <label class="field">{{ $t('name') }}<InputText v-model="form.name" required maxlength="100" /></label
                ><label class="field"
                    >{{ $t('public_https_url')
                    }}<InputText
                        v-model="form.url"
                        type="url"
                        required
                        placeholder="https://example.com/zionfood/webhook"
                /></label>
                <p class="muted">
                    {{
                        $t('only_public_https_port_443_credentials_query_parameters_private_addresses_and_redirects_ar')
                    }}
                </p>
                <label class="field">{{ $t('events') }}<MultiSelect v-model="form.events" :options="events" /></label
                ><label class="field"
                    >{{ $t('signing_secret')
                    }}<InputText
                        v-model="form.secret"
                        type="password"
                        autocomplete="new-password"
                        :required="!editing"
                        :minlength="32"
                        :maxlength="128"
                /></label>
                <p class="muted">
                    {{
                        $t('use_the_same_secret_in_your_receiver_32128_letters_digits_hyphens_or_underscores_leave_bla')
                    }}
                </p>
                <label class="flex items-center gap-3"
                    ><ToggleSwitch v-model="form.enabled" />{{ $t('enable_webhook_delivery') }}</label
                ><Button type="submit" :label="$t('save_webhook')" :loading="busy" /></form></Dialog
        ><ZFConfirmDialog
    /></RestaurantLayout>
</template>
