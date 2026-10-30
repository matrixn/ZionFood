<script setup lang="ts">
import ZFDrawer from '@/components/ui/ZFDrawer.vue';
import ZFStatusBadge from '@/components/ui/ZFStatusBadge.vue';
import OrderTrackingTimeline from './OrderTrackingTimeline.vue';
import { useLocationDates } from '@/lib/locationDates';
const dates = useLocationDates();
import { money } from '@/lib/http';
import type { Order } from '@/types';
import { computed, ref, watch } from 'vue';
import { orderRejectionReason } from '@/lib/orderRejection';
import Select from 'primevue/select';
import InputText from 'primevue/inputtext';
import Button from 'primevue/button';
import { api } from '@/lib/http';
const props = defineProps<{ order: Order | null; canWrite?: boolean; feature?: 'orders' | 'kitchen' }>();
const emit = defineEmits<{ updated: [order: Order] }>();
const next = ref<string | null>(null);
const reason = ref('');
const error = ref('');
const busy = ref(false);
const rejectionReason = computed(() => (props.order ? orderRejectionReason(props.order) : null));
watch(
    () => [props.order?.public_id, props.order?.version],
    () => {
        next.value = null;
        error.value = '';
        reason.value = '';
    },
);
async function update() {
    if (!props.canWrite || !props.order || !next.value) return;
    busy.value = true;
    error.value = '';
    try {
        const result = await api<{ data: Order }>(
            `/api/v1/${props.feature === 'kitchen' ? 'kitchen-orders' : 'orders'}/${props.order.public_id}/status`,
            'PATCH',
            { status: next.value, version: props.order.version, reason: reason.value || null },
        );
        emit('updated', result.data);
    } catch (e) {
        error.value = (e as Error).message;
    } finally {
        busy.value = false;
    }
}
const visible = defineModel<boolean>('visible', { required: true });
</script>
<template>
    <ZFDrawer v-model:visible="visible" :title="$t('order_details')"
        ><div v-if="order" class="grid gap-6">
            <div class="flex justify-between">
                <strong>#{{ order.public_id.slice(-8) }}</strong
                ><ZFStatusBadge :status="order.status" />
            </div>
            <section v-if="order.scheduled_for" class="rounded-xl bg-violet-50 p-4 text-violet-950">
                <strong>{{ $t('scheduled_order') }}</strong>
                <p>{{ dates.dateTime(order.scheduled_for) }}</p>
                <p v-if="order.preparation_at" class="text-sm">
                    {{ $t('preparation_starts') }}: {{ dates.dateTime(order.preparation_at) }}
                </p>
            </section>
            <section
                v-if="rejectionReason"
                :aria-label="$t('reason_for_rejection')"
                class="rounded-xl border border-rose-200 bg-rose-50 p-4 text-rose-950 dark:border-rose-800 dark:bg-rose-950/40 dark:text-rose-100"
            >
                <h3 class="flex items-center gap-2 font-semibold">
                    <i class="pi pi-times-circle" aria-hidden="true" />{{ $t('reason_for_rejection') }}
                </h3>
                <p class="mt-2 whitespace-pre-wrap break-words text-sm leading-relaxed">{{ rejectionReason }}</p>
            </section>
            <div>
                <h3 class="font-semibold">{{ order.customer_name }}</h3>
                <p v-if="order.table_code" class="my-2 font-semibold text-brand">
                    {{ $t('dining_table') }} {{ order.table_code }} · {{ order.table_area }}
                </p>
                <p class="muted">{{ order.customer_phone }}</p>
                <p class="muted mt-2">{{ order.delivery_address }}</p>
                <a
                    v-if="order.delivery_latitude != null && order.delivery_longitude != null"
                    :href="`https://www.openstreetmap.org/?mlat=${order.delivery_latitude}&mlon=${order.delivery_longitude}#map=17/${order.delivery_latitude}/${order.delivery_longitude}`"
                    target="_blank"
                    rel="noopener"
                    class="mt-3 inline-block text-brand"
                    >{{ $t('map_open_destination') }}</a
                >
                <p v-if="order.notes" class="my-3 rounded-xl bg-amber-50 p-3 text-amber-900">{{ order.notes }}</p>
            </div>
            <div v-for="item in order.items" :key="item.id" class="border-b border-slate-200 pb-3">
                <div class="flex justify-between">
                    <strong>{{ item.quantity }} × {{ item.name }}</strong
                    ><span>{{ money(item.total) }}</span>
                </div>
                <p class="muted mt-1">{{ item.variant_name }}</p>
                <p v-for="mod in item.modifiers" :key="mod.id" class="muted">
                    + {{ mod.quantity ?? 1 }} × {{ mod.name }}
                </p>
            </div>
            <p v-if="order.discount" class="text-sm text-emerald-700">
                {{ $t('promotion_discount') }}: −{{ money(order.discount, order.currency) }}
            </p>
            <div class="flex justify-between font-semibold">
                <span>{{ $t('total') }}</span
                ><span>{{ money(order.total, order.currency) }}</span>
            </div>
            <OrderTrackingTimeline :history="order.history" />
        </div>
        <template v-if="canWrite && order?.transitions.length" #footer>
            <div class="grid gap-3">
                <p v-if="error" role="alert" class="text-sm text-red-700">{{ error }}</p>
                <label class="field"
                    ><span class="field-label"
                        >{{ $t('order_next_status')
                        }}<FieldHelp :label="$t('order_next_status')" field="order_next_status" /></span
                    ><Select
                        v-model="next"
                        :aria-label="$t('order_next_status')"
                        :options="order.transitions.map((status) => ({ label: $t('status_' + status), value: status }))"
                        option-label="label"
                        option-value="value"
                /></label>
                <span v-if="next === 'rejected'" class="field-control-help"
                    ><InputText
                        v-model="reason"
                        :aria-label="$t('reason_for_rejection')"
                        :placeholder="$t('reason_for_rejection')"
                        maxlength="500" /><FieldHelp
                        :label="$t('reason_for_rejection')"
                        field="reason"
                        kind="InputText"
                /></span>
                <Button
                    :label="$t('order_update_status')"
                    :disabled="busy || !next || (next === 'rejected' && !reason.trim())"
                    :loading="busy"
                    @click="update"
                />
            </div>
        </template>
    </ZFDrawer>
</template>
