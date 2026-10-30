<script setup lang="ts">
import { ref } from 'vue';
import { Head } from '@inertiajs/vue3';
import RestaurantLayout from '@/layouts/RestaurantLayout.vue';
import AdminLayout from '@/layouts/AdminLayout.vue';
import InputText from 'primevue/inputtext';
import Select from 'primevue/select';
import Button from 'primevue/button';
import { api } from '@/lib/http';
import type { WorkspaceProps } from '@/types';
interface Log {
    id: number;
    request_id: string;
    direction: string;
    operation: string;
    method: string;
    http_status: number | null;
    outcome: string;
    duration_ms: number;
    attempt: number;
    replayed: boolean;
    error_code: string | null;
    event_id: string | null;
    created_at: string;
    destination_host: string | null;
    restaurant?: { name: string };
    location?: { name: string };
    connection?: { name: string };
}
interface Data {
    logs: { data: Log[]; current_page: number; last_page: number };
    restaurants: { public_id: string; name: string }[];
    retention_days: number;
}
const props = defineProps<Partial<WorkspaceProps> & { platform: boolean; initial: Data }>();
const data = ref(props.initial);
const error = ref('');
const busy = ref(false);
const filters = ref({
    restaurant: null as string | null,
    direction: null as string | null,
    status: '',
    outcome: null as string | null,
    request_id: '',
    event_id: '',
    from: '',
    to: '',
});
const endpoint = props.platform
    ? '/admin/communication-logs/data'
    : `/app/locations/${props.location!.public_id}/communication-logs/data`;
async function load(page = 1) {
    busy.value = true;
    error.value = '';
    try {
        const query = new URLSearchParams({ page: String(page) });
        Object.entries(filters.value).forEach(([key, value]) => {
            if (value) query.set(key, value);
        });
        data.value = await api<Data>(endpoint + '?' + query);
    } catch (e) {
        error.value = (e as Error).message;
    } finally {
        busy.value = false;
    }
}
</script>
<template>
    <component :is="platform ? AdminLayout : RestaurantLayout" v-bind="platform ? {} : $props">
        <Head :title="$t('comm_title')" />
        <h1 class="text-3xl font-semibold">{{ $t('comm_title') }}</h1>
        <p class="muted my-3">{{ $t('comm_intro', { days: data.retention_days }) }}</p>
        <form class="panel my-5 grid gap-4 sm:grid-cols-2 xl:grid-cols-4" @submit.prevent="load()">
            <label v-if="platform" class="field"
                ><span class="field-label"
                    >{{ $t('restaurant') }}<FieldHelp :label="$t('restaurant')" :text="$t('comm_filter_help')" /></span
                ><Select
                    v-model="filters.restaurant"
                    :aria-label="$t('restaurant')"
                    show-clear
                    :options="data.restaurants"
                    option-value="public_id"
                    option-label="name"
            /></label>
            <label class="field"
                ><span class="field-label"
                    >{{ $t('comm_direction')
                    }}<FieldHelp :label="$t('comm_direction')" :text="$t('comm_direction_help')" /></span
                ><Select
                    v-model="filters.direction"
                    :aria-label="$t('comm_direction')"
                    show-clear
                    :options="['inbound', 'outbound']"
            /></label>
            <label class="field"
                ><span class="field-label">HTTP<FieldHelp label="HTTP" :text="$t('comm_status_help')" /></span
                ><InputText v-model="filters.status" aria-label="HTTP" inputmode="numeric" maxlength="3"
            /></label>
            <label class="field"
                ><span class="field-label"
                    >{{ $t('comm_outcome')
                    }}<FieldHelp :label="$t('comm_outcome')" :text="$t('comm_filter_help')" /></span
                ><Select
                    v-model="filters.outcome"
                    :aria-label="$t('comm_outcome')"
                    show-clear
                    :options="[
                        'success',
                        'client_error',
                        'server_error',
                        'delivered',
                        'retrying',
                        'failed',
                        'blocked',
                    ]"
            /></label>
            <label v-for="key in ['request_id', 'event_id', 'from', 'to'] as const" :key="key" class="field"
                ><span class="field-label"
                    >{{ $t('comm_' + key)
                    }}<FieldHelp :label="$t('comm_' + key)" :text="$t('comm_filter_help')" /></span
                ><InputText
                    v-model="filters[key]"
                    :type="key === 'from' || key === 'to' ? 'date' : 'text'"
                    :aria-label="$t('comm_' + key)"
            /></label>
            <Button type="submit" :label="$t('comm_filter')" :loading="busy" />
        </form>
        <p v-if="error" role="alert" class="text-red-600">{{ error }}</p>
        <section class="panel overflow-x-auto">
            <table v-if="data.logs.data.length" class="w-full text-left text-sm">
                <thead class="[&_th]:px-3 [&_th]:py-3 [&_th]:whitespace-nowrap">
                    <tr>
                        <th>{{ $t('comm_time') }}</th>
                        <th>{{ $t('restaurant') }}</th>
                        <th>{{ $t('comm_direction') }}</th>
                        <th>{{ $t('comm_operation') }}</th>
                        <th>HTTP</th>
                        <th>{{ $t('comm_outcome') }}</th>
                        <th>{{ $t('comm_debug') }}</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="log in data.logs.data" :key="log.id" class="border-t border-slate-200 align-top">
                        <td class="p-3 whitespace-nowrap">{{ new Date(log.created_at).toLocaleString() }}</td>
                        <td class="p-3">
                            {{ log.restaurant?.name ?? '—' }}<small class="block">{{ log.location?.name }}</small
                            ><small class="block">{{ log.connection?.name }}</small>
                        </td>
                        <td class="p-3">{{ log.direction }}</td>
                        <td class="p-3 break-all">
                            {{ log.method }} {{ log.operation }}<small class="block">{{ log.destination_host }}</small>
                        </td>
                        <td class="p-3">{{ log.http_status ?? '—' }}</td>
                        <td class="p-3">
                            {{ log.outcome }}<small class="block">{{ log.error_code }}</small>
                        </td>
                        <td class="p-3">
                            <span class="block">{{ log.duration_ms }} ms · #{{ log.attempt }}</span
                            ><code class="block text-xs">{{ log.request_id }}</code
                            ><code class="block text-xs">{{ log.event_id }}</code
                            ><span v-if="log.replayed">{{ $t('comm_replayed') }}</span>
                        </td>
                    </tr>
                </tbody>
            </table>
            <p v-if="!data.logs.data.length" class="muted p-4">{{ $t('comm_empty') }}</p>
        </section>
        <div class="mt-4 flex items-center gap-3">
            <Button
                :label="$t('previous')"
                outlined
                :disabled="busy || data.logs.current_page <= 1"
                @click="load(data.logs.current_page - 1)"
            /><span>{{ data.logs.current_page }}/{{ data.logs.last_page }}</span
            ><Button
                :label="$t('next')"
                outlined
                :disabled="busy || data.logs.current_page >= data.logs.last_page"
                @click="load(data.logs.current_page + 1)"
            />
        </div>
    </component>
</template>
