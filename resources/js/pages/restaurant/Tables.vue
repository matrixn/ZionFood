<script setup lang="ts">
import { onMounted, onBeforeUnmount, ref, watch, computed } from 'vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import QRCode from 'qrcode';
import Tooltip from 'primevue/tooltip';
import ConfirmDialog from 'primevue/confirmdialog';
import { useConfirm } from 'primevue/useconfirm';
import RestaurantLayout from '@/layouts/RestaurantLayout.vue';
import Button from 'primevue/button';
import Select from 'primevue/select';
import { tr as t } from '@/i18n';
import type { WorkspaceProps } from '@/types';
const vTooltip = Tooltip;
interface Table {
    public_id: string;
    code: string;
    area: string;
    seats: number;
    qr_url: string | null;
    sessions: { public_id: string; expires_at: string }[];
    ordering_paused: boolean;
}
const props = defineProps<
    WorkspaceProps & {
        tables: Table[];
        staffActivation: boolean;
        orderingWindow: {
            is_open: boolean;
            reason: string;
            opens_at: string | null;
            closes_at: string | null;
            cutoff_at: string | null;
            timezone: string;
        };
    }
>();
const qr = ref<Record<string, string>>({});
const form = useForm({});
const canWrite = computed(() => props.permissions.tables === 'manage');
const confirm = useConfirm();
const selectedArea = ref<string | null>(null);
const selectedTables = computed(() =>
    selectedArea.value === null ? props.tables : props.tables.filter((table) => table.area === selectedArea.value),
);
const areaOptions = computed(() => [
    { value: null, label: t('tables_all_rooms') },
    ...rooms.value.map((area) => ({ value: area, label: area })),
]);
const rooms = computed(() => [...new Set(props.tables.map((table) => table.area))]);
function effectiveReason(table: Table) {
    if (!props.location.fulfillment.dine_in_enabled) return 'service_disabled';
    if (table.ordering_paused) return 'table_closed';
    if (!props.orderingWindow.is_open) return props.orderingWindow.reason;
    if (props.staffActivation && !table.sessions.length) return 'staff_activation';
    return 'open';
}
function bulk(action: 'open' | 'close', area?: string) {
    const send = () =>
        router.post(
            `/app/locations/${props.location.public_id}/tables/bulk`,
            { operation: action, scope: area === undefined ? 'all' : 'area', ...(area === undefined ? {} : { area }) },
            {
                preserveScroll: true,
                onStart: () => (form.processing = true),
                onFinish: () => (form.processing = false),
                onError: (errors) => form.setError(errors),
            },
        );
    if (action === 'close')
        confirm.require({
            header: t('dining_bulk_close'),
            message: area === undefined ? t('dining_bulk_confirm_all') : t('dining_bulk_confirm_area', { area }),
            accept: send,
        });
    else send();
}
function refresh() {
    if (!form.processing) router.reload({ only: ['tables', 'orderingWindow', 'location', 'permissions'] });
}
let timer: ReturnType<typeof setInterval>;
onMounted(() => {
    timer = setInterval(refresh, 30000);
    window.addEventListener('focus', refresh);
});
onBeforeUnmount(() => {
    clearInterval(timer);
    window.removeEventListener('focus', refresh);
});
function disabledReason(action: 'open' | 'close' | 'rotate') {
    if (form.processing) return 'dining_action_busy';
    if (action === 'open' && !props.location.fulfillment.dine_in_enabled) return 'dining_open_disabled';
    return '';
}
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
        <div class="mb-4 flex items-center gap-2 rounded-xl bg-violet-50 p-3 text-xs text-brand dark:bg-violet-950">
            <i class="pi pi-qrcode" aria-hidden="true" /><span>{{ $t('tables_qr_short') }}</span
            ><FieldHelp :label="t('tables_qr_short')" :text="t('dining_qr_explanation')" />
        </div>
        <p v-if="!location.fulfillment.dine_in_enabled" class="mb-5 rounded-xl bg-amber-50 p-4 text-amber-900">
            {{ $t('dining_disabled') }}
            <a
                v-if="canManage"
                :href="`/app/locations/${location.public_id}/setup/services`"
                class="ml-2 font-semibold underline"
                >{{ $t('dining_enable_service') }}</a
            >
        </p>
        <p v-for="(message, key) in form.errors" :key="key" role="alert" class="mb-4 text-red-700">{{ message }}</p>
        <p v-if="!tables.length" class="panel muted">{{ $t('dining_no_tables') }}</p>
        <div v-if="!orderingWindow.is_open" class="mb-5 rounded-xl bg-amber-50 p-4 text-sm text-amber-900">
            <p>{{ $t('dining_reason_' + orderingWindow.reason) }}</p>
            <p v-if="orderingWindow.opens_at" class="mt-2">
                {{
                    $t('dining_window_times', {
                        open: orderingWindow.opens_at,
                        close: orderingWindow.closes_at,
                        cutoff: orderingWindow.cutoff_at,
                        zone: orderingWindow.timezone,
                    })
                }}
            </p>
            <p class="mt-2">{{ $t('dining_manual_vs_window') }}</p>
        </div>
        <section
            v-if="canWrite && tables.length"
            class="mb-5 rounded-2xl border border-slate-200 bg-white p-4 dark:border-slate-700 dark:bg-slate-900"
            data-testid="table-quick-controls"
        >
            <div class="mb-3 flex items-center justify-between gap-3">
                <h2 class="text-sm font-semibold">{{ $t('dining_bulk_title') }}</h2>
                <FieldHelp :text="t('tables_bulk_help')" :label="t('dining_bulk_title')" />
            </div>
            <div class="flex flex-wrap items-center gap-3">
                <span class="field-control-help table-scope"
                    ><Select
                        v-model="selectedArea"
                        :placeholder="$t('tables_all_rooms')"
                        :options="areaOptions"
                        option-label="label"
                        option-value="value"
                        :aria-label="$t('tables_control_scope')"
                        class="w-full" /><FieldHelp :label="t('tables_control_scope')" :text="t('tables_scope_help')"
                /></span>
                <span
                    v-tooltip.top="disabledReason('open') ? t(disabledReason('open')) : ''"
                    :tabindex="disabledReason('open') ? 0 : undefined"
                    ><Button
                        :aria-label="selectedArea === null ? t('dining_open_all') : t('tables_open_selected')"
                        :label="selectedArea === null ? t('dining_open_all') : t('tables_open_selected')"
                        icon="pi pi-check-circle"
                        size="small"
                        :disabled="!!disabledReason('open')"
                        @click="bulk('open', selectedArea ?? undefined)"
                        ><span class="sm:hidden">{{ $t('tables_activate_short') }}</span
                        ><span class="hidden sm:inline">{{
                            selectedArea === null ? t('dining_open_all') : t('tables_open_selected')
                        }}</span></Button
                    ></span
                >
                <Button
                    :aria-label="selectedArea === null ? t('dining_close_all') : t('tables_close_selected')"
                    :label="selectedArea === null ? t('dining_close_all') : t('tables_close_selected')"
                    icon="pi pi-times-circle"
                    severity="danger"
                    outlined
                    size="small"
                    :disabled="form.processing"
                    @click="bulk('close', selectedArea ?? undefined)"
                    ><span class="sm:hidden">{{ $t('tables_deactivate_short') }}</span
                    ><span class="hidden sm:inline">{{
                        selectedArea === null ? t('dining_close_all') : t('tables_close_selected')
                    }}</span></Button
                >
                <span class="text-xs text-slate-400">{{ selectedTables.length }} {{ $t('tables_count_short') }}</span>
            </div>
        </section>
        <div class="grid gap-5 sm:grid-cols-2 xl:grid-cols-3">
            <article
                v-for="table in tables"
                :key="table.public_id"
                class="panel space-y-4 !border-2 transition-colors"
                :class="
                    effectiveReason(table) === 'open'
                        ? '!border-emerald-400 bg-emerald-50/20 dark:!border-emerald-800'
                        : '!border-rose-400 bg-rose-50/20 dark:!border-rose-900'
                "
                :data-state="effectiveReason(table)"
                data-testid="table-card"
            >
                <h2 class="text-xl font-semibold">{{ table.code }} · {{ table.area }}</h2>
                <p class="text-xs text-slate-500">{{ table.seats }} {{ $t('setup_seats') }}</p>
                <div
                    class="flex items-center gap-2 text-sm"
                    :class="
                        effectiveReason(table) === 'open'
                            ? 'text-emerald-700 dark:text-emerald-400'
                            : 'text-rose-700 dark:text-rose-400'
                    "
                >
                    <span
                        class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full"
                        :class="
                            effectiveReason(table) === 'open'
                                ? 'bg-emerald-100 ring-1 ring-emerald-200 dark:bg-emerald-950'
                                : 'bg-rose-100 ring-1 ring-rose-200 dark:bg-rose-950'
                        "
                        ><i
                            :class="['pi', effectiveReason(table) === 'open' ? 'pi-check' : 'pi-times']"
                            aria-hidden="true"
                    /></span>
                    <span class="font-medium">{{ $t('tables_short_' + effectiveReason(table)) }}</span>
                    <FieldHelp
                        :label="t('tables_short_' + effectiveReason(table))"
                        :text="t('dining_reason_' + effectiveReason(table))"
                    />
                </div>
                <p v-if="!table.ordering_paused && effectiveReason(table) !== 'open'" class="text-xs text-slate-500">
                    {{ $t('dining_manual_active') }}
                </p>
                <img
                    v-if="qr[table.public_id]"
                    :src="qr[table.public_id]"
                    :alt="$t('dining_qr_for', { table: table.code })"
                    class="mx-auto h-48 w-48"
                />
                <div class="flex flex-wrap gap-2">
                    <span
                        v-if="canWrite && (table.ordering_paused || (staffActivation && !table.sessions.length))"
                        v-tooltip.top="disabledReason('open') ? t(disabledReason('open')) : ''"
                        :title="disabledReason('open') ? t(disabledReason('open')) : undefined"
                        :tabindex="disabledReason('open') ? 0 : undefined"
                        :aria-label="disabledReason('open') ? t(disabledReason('open')) : undefined"
                    >
                        <Button
                            :label="$t('dining_open_table')"
                            :disabled="!!disabledReason('open')"
                            :loading="form.processing"
                            @click="operation(table, 'open')" /></span
                    ><span
                        v-else-if="canWrite"
                        v-tooltip.top="disabledReason('close') ? t(disabledReason('close')) : ''"
                        :title="disabledReason('close') ? t(disabledReason('close')) : undefined"
                        :tabindex="disabledReason('close') ? 0 : undefined"
                    >
                        <Button
                            :label="$t('dining_close_table')"
                            outlined
                            :loading="form.processing"
                            :disabled="!!disabledReason('close')"
                            @click="operation(table, 'close')" /></span
                    ><a
                        v-if="qr[table.public_id]"
                        :href="qr[table.public_id]"
                        :download="`${table.code}-qr.png`"
                        class="rounded-lg border px-4 py-3 text-sm font-semibold text-brand"
                        >{{ $t('dining_download_qr') }}</a
                    >
                </div>
                <details v-if="canWrite">
                    <summary class="cursor-pointer text-sm text-slate-500">{{ $t('dining_qr_admin') }}</summary>
                    <p class="my-3 text-sm">{{ $t('dining_rotate_warning') }}</p>
                    <a
                        :href="table.qr_url ?? undefined"
                        target="_blank"
                        rel="noreferrer"
                        class="mb-3 block text-brand underline"
                        >{{ $t('dining_preview_qr') }}</a
                    >
                    <span
                        v-tooltip.top="disabledReason('rotate') ? t(disabledReason('rotate')) : ''"
                        :title="disabledReason('rotate') ? t(disabledReason('rotate')) : undefined"
                        :tabindex="disabledReason('rotate') ? 0 : undefined"
                    >
                        <Button
                            :disabled="!!disabledReason('rotate')"
                            :label="$t('dining_rotate_qr')"
                            severity="danger"
                            outlined
                            :loading="form.processing"
                            @click="operation(table, 'rotate')"
                    /></span>
                </details>
            </article>
        </div>
        <ConfirmDialog />
    </RestaurantLayout>
</template>

<style scoped>
.table-scope {
    flex: 1 1 12rem;
    min-width: 12rem;
}
@media (max-width: 640px) {
    .table-scope {
        flex-basis: 100%;
    }
}
.room-actions :deep(.p-button-label) {
    white-space: normal;
    overflow-wrap: anywhere;
}
</style>
