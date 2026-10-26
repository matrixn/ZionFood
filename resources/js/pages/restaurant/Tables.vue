<script setup lang="ts">
import { onMounted, ref, watch } from 'vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import QRCode from 'qrcode';
import RestaurantLayout from '@/layouts/RestaurantLayout.vue';
import Button from 'primevue/button';
import type { WorkspaceProps } from '@/types';
interface Table {
    public_id: string;
    code: string;
    area: string;
    seats: number;
    qr_url: string | null;
    sessions: { public_id: string; expires_at: string }[];
    ordering_paused: boolean;
}
const props = defineProps<WorkspaceProps & { tables: Table[]; staffActivation: boolean }>();
const qr = ref<Record<string, string>>({});
const form = useForm({});
let generation = 0;
async function generate() {
    const request = ++generation;
    const images: Record<string, string> = {};
    for (const table of props.tables)
        if (table.qr_url) images[table.public_id] = await QRCode.toDataURL(table.qr_url, { width: 240, margin: 2 });
    if (request === generation) qr.value = images;
}
function operation(table: Table, action: string) {
    form.post(`/app/tables/${table.public_id}/${action}`, { preserveScroll: true });
}
onMounted(generate);
watch(() => props.tables, generate);
</script>
<template>
    <RestaurantLayout v-bind="$props"
        ><Head :title="$t('dining_tables')" />
        <div class="mb-6 flex flex-wrap justify-between gap-4">
            <h1 class="text-3xl font-semibold">{{ $t('dining_tables') }}</h1>
            <Button :label="$t('booking_refresh')" icon="pi pi-refresh" outlined @click="router.reload()" />
        </div>
        <p class="mb-5 rounded-xl bg-violet-50 p-4 text-sm text-brand">{{ $t('dining_qr_explanation') }}</p>
        <p v-if="!location.fulfillment.dine_in_enabled" class="mb-5 rounded-xl bg-amber-50 p-4 text-amber-900">
            {{ $t('dining_disabled') }}
        </p>
        <p v-for="(message, key) in form.errors" :key="key" role="alert" class="mb-4 text-red-700">{{ message }}</p>
        <p v-if="!tables.length" class="panel muted">{{ $t('dining_no_tables') }}</p>
        <div class="grid gap-5 sm:grid-cols-2 xl:grid-cols-3">
            <article v-for="table in tables" :key="table.public_id" class="panel space-y-4">
                <h2 class="text-xl font-semibold">{{ table.code }} · {{ table.area }}</h2>
                <p class="muted">
                    {{ table.seats }} {{ $t('setup_seats') }} ·
                    {{
                        table.ordering_paused || (staffActivation && !table.sessions.length)
                            ? $t('dining_closed')
                            : $t('dining_open')
                    }}
                </p>
                <img
                    v-if="qr[table.public_id]"
                    :src="qr[table.public_id]"
                    :alt="$t('dining_qr_for', { table: table.code })"
                    class="mx-auto h-48 w-48"
                />
                <div class="flex flex-wrap gap-2">
                    <Button
                        v-if="table.ordering_paused || (staffActivation && !table.sessions.length)"
                        :label="$t('dining_open_table')"
                        :disabled="!location.fulfillment.dine_in_enabled"
                        :loading="form.processing"
                        @click="operation(table, 'open')"
                    /><Button
                        v-else
                        :label="$t('dining_close_table')"
                        outlined
                        :loading="form.processing"
                        @click="operation(table, 'close')"
                    /><a
                        v-if="qr[table.public_id]"
                        :href="qr[table.public_id]"
                        :download="`${table.code}-qr.png`"
                        class="rounded-lg border px-4 py-3 text-sm font-semibold text-brand"
                        >{{ $t('dining_download_qr') }}</a
                    >
                </div>
                <details v-if="canManage">
                    <summary class="cursor-pointer text-sm text-slate-500">{{ $t('dining_qr_admin') }}</summary>
                    <p class="my-3 text-sm">{{ $t('dining_rotate_warning') }}</p>
                    <a
                        :href="table.qr_url ?? undefined"
                        target="_blank"
                        rel="noreferrer"
                        class="mb-3 block text-brand underline"
                        >{{ $t('dining_preview_qr') }}</a
                    >
                    <Button
                        :label="$t('dining_rotate_qr')"
                        severity="danger"
                        outlined
                        :loading="form.processing"
                        @click="operation(table, 'rotate')"
                    />
                </details>
            </article></div
    ></RestaurantLayout>
</template>
