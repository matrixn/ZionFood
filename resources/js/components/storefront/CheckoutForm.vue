<script setup lang="ts">
import { tr as t } from '@/i18n';

import { computed, onBeforeUnmount, ref, useId, watch } from 'vue';
import Dialog from 'primevue/dialog';
import InputText from 'primevue/inputtext';
import Textarea from 'primevue/textarea';
import Select from 'primevue/select';
import Button from 'primevue/button';
import { router } from '@inertiajs/vue3';
import { useCart } from '@/stores/cart';
import { api, money } from '@/lib/http';
import type { Zone } from '@/types';
import DeliveryMap from '@/components/maps/DeliveryMap.vue';
import { matchingZone, type DeliveryPoint, type MapConfig } from '@/lib/deliveryGeometry';
const props = defineProps<{
    canOrder?: boolean;
    blockedMessage?: string;
    locationId: string;
    fulfillment: { pickup_enabled: boolean; delivery_enabled: boolean };
    zones: Zone[];
    tableSessionToken?: string | null;
    mapConfig: MapConfig;
    mapCenter: number[];
}>();
const emit = defineEmits<{ placed: [] }>();
const formId = useId();
const visible = defineModel<boolean>('visible', { required: true });
const cart = useCart();
const point = ref<DeliveryPoint | null>(null);
const hasPolygons = computed(() => props.zones.some((zone) => zone.geometry));
const polygonZone = computed(() => matchingZone(props.zones, point.value));
const form = ref({
    customer_name: '',
    customer_phone: '',
    fulfillment: props.tableSessionToken ? 'dine_in' : props.fulfillment.pickup_enabled ? 'pickup' : 'delivery',
    delivery_address: '',
    postal_code: '',
    delivery_zone_id: null as number | null,
    notes: '',
});
const error = ref('');
const busy = ref(false);
const payloadKey = ref('');
const key = ref(cart.checkoutKey);
const methods = computed(() =>
    (props.tableSessionToken
        ? [{ label: t('fulfillment_dine_in'), value: 'dine_in', enabled: true }]
        : [
              { label: t('pickup'), value: 'pickup', enabled: props.fulfillment.pickup_enabled },
              { label: t('delivery'), value: 'delivery', enabled: props.fulfillment.delivery_enabled },
          ]
    ).filter((m) => m.enabled),
);
const fee = computed(() =>
    form.value.fulfillment === 'delivery'
        ? hasPolygons.value
            ? (polygonZone.value?.fee ?? 0)
            : (props.zones.find((z) => z.id === form.value.delivery_zone_id)?.fee ?? 0)
        : 0,
);
const addressLoading = ref(false);
const addressHint = ref('');
let lookup = 0;
let addressVersion = 0;
let timer: ReturnType<typeof setTimeout> | undefined;
function cancelLookup() {
    lookup++;
    clearTimeout(timer);
    addressLoading.value = false;
}
function editedAddress() {
    addressVersion++;
    cancelLookup();
    addressHint.value = '';
}
watch(
    () => [visible.value, form.value.fulfillment, point.value?.latitude, point.value?.longitude, polygonZone.value?.id],
    () => {
        cancelLookup();
        addressHint.value = '';
        if (!visible.value || form.value.fulfillment !== 'delivery' || !point.value || !polygonZone.value) return;
        const selected = { ...point.value };
        const generation = lookup;
        const version = addressVersion;
        timer = setTimeout(async () => {
            if (generation !== lookup || version !== addressVersion) return;
            addressLoading.value = true;
            try {
                const response = await api<{ address: string | null; incomplete: boolean }>(
                    `/api/v1/public/locations/${props.locationId}/address-from-pin`,
                    'POST',
                    selected,
                );
                if (generation !== lookup || version !== addressVersion) return;
                if (response.address) form.value.delivery_address = response.address;
                addressHint.value = response.address
                    ? response.incomplete
                        ? 'checkout_address_partial'
                        : 'checkout_address_filled'
                    : 'checkout_address_manual';
            } catch {
                if (generation === lookup && version === addressVersion) addressHint.value = 'checkout_address_manual';
            } finally {
                if (generation === lookup) addressLoading.value = false;
            }
        }, 650);
    },
);
onBeforeUnmount(cancelLookup);
const deliveryZone = computed(() =>
    hasPolygons.value ? polygonZone.value : props.zones.find((z) => z.id === form.value.delivery_zone_id),
);
const restrictedProducts = computed(() =>
    cart.lines.filter(
        (l) =>
            l.order_types !== null &&
            l.order_types !== undefined &&
            !l.order_types.includes(props.tableSessionToken ? 'dine_in' : form.value.fulfillment),
    ),
);
const valid = computed(() => {
    if (
        props.canOrder === false ||
        restrictedProducts.value.length > 0 ||
        !cart.lines.length ||
        busy.value ||
        addressLoading.value ||
        !form.value.customer_name.trim() ||
        !form.value.customer_phone.trim() ||
        !methods.value.some((m) => m.value === form.value.fulfillment)
    )
        return false;
    if (form.value.fulfillment !== 'delivery') return true;
    if (!form.value.delivery_address.trim() || !deliveryZone.value || cart.total < deliveryZone.value.minimum_order)
        return false;
    return hasPolygons.value
        ? !!polygonZone.value
        : !!form.value.postal_code.trim() && form.value.postal_code.startsWith(deliveryZone.value.postal_prefix);
});
watch(visible, (open) => {
    if (open) error.value = '';
});
watch(
    () => props.tableSessionToken,
    (token) => {
        if (token) form.value.fulfillment = 'dine_in';
    },
);
async function submit() {
    if (!valid.value) return;
    cancelLookup();
    busy.value = true;
    error.value = '';
    const payload = {
        ...form.value,
        ...(props.tableSessionToken ? { fulfillment: 'dine_in', table_session_token: props.tableSessionToken } : {}),
        ...(form.value.fulfillment === 'delivery' && hasPolygons.value
            ? {
                  delivery_zone_id: polygonZone.value?.id ?? null,
                  delivery_latitude: point.value?.latitude,
                  delivery_longitude: point.value?.longitude,
              }
            : {}),
        items: cart.lines.map((l) => ({
            product_id: l.product_id,
            variant_id: l.variant_id,
            quantity: l.quantity,
            ...(l.modifiers ? { modifiers: l.modifiers } : { modifier_ids: l.modifier_ids }),
        })),
    };
    const signature = JSON.stringify(payload);
    if (payloadKey.value && payloadKey.value !== signature) key.value = crypto.randomUUID();
    payloadKey.value = signature;
    try {
        const result = await api<{ order_id: string; tracking_token: string }>(
            `/api/v1/public/locations/${props.locationId}/checkout`,
            'POST',
            payload,
            { 'Idempotency-Key': key.value },
        );
        if (props.tableSessionToken) {
            try {
                sessionStorage.setItem(`zf-order-${result.order_id}`, result.tracking_token);
            } catch {
                /* The table receipt is already available through the in-memory session. */
            }
        } else sessionStorage.setItem(`zf-order-${result.order_id}`, result.tracking_token);
        cart.clear();
        key.value = cart.checkoutKey;
        payloadKey.value = '';
        visible.value = false;
        if (props.tableSessionToken) emit('placed');
        else router.visit(`/track/${result.order_id}`);
    } catch (e) {
        error.value = (e as Error).message;
    } finally {
        busy.value = false;
    }
}
</script>
<template>
    <Dialog v-model:visible="visible" :header="$t('almost_at_your_table')" modal class="w-full max-w-xl"
        ><form :id="formId" class="grid gap-5" @submit.prevent="submit">
            <p v-if="error" role="alert" class="rounded-lg bg-red-50 p-3 text-red-700">{{ error }}</p>
            <label class="field"
                ><span class="field-label"
                    >{{ $t('your_name') }}<FieldHelp :label="$t('your_name')" field="your_name" /></span
                ><InputText
                    v-model="form.customer_name"
                    :aria-label="$t('your_name')"
                    autocomplete="name"
                    maxlength="150"
                    required /></label
            ><label class="field"
                ><span class="field-label"
                    >{{ $t('phone_number') }}<FieldHelp :label="$t('phone_number')" field="phone_number" /></span
                ><InputText
                    v-model="form.customer_phone"
                    :aria-label="$t('phone_number')"
                    type="tel"
                    autocomplete="tel"
                    maxlength="30"
                    required /></label
            ><label class="field"
                ><span class="field-label"
                    >{{ $t('how_would_you_like_your_order')
                    }}<FieldHelp
                        :label="$t('how_would_you_like_your_order')"
                        field="how_would_you_like_your_order" /></span
                ><Select
                    v-model="form.fulfillment"
                    :aria-label="$t('how_would_you_like_your_order')"
                    :options="methods"
                    option-label="label"
                    option-value="value" /></label
            ><template v-if="form.fulfillment === 'delivery'"
                ><label class="field"
                    ><span class="field-label"
                        >{{ $t('delivery_address')
                        }}<FieldHelp :label="$t('delivery_address')" field="delivery_address" /></span
                    ><InputText
                        v-model="form.delivery_address"
                        :aria-label="$t('delivery_address')"
                        autocomplete="street-address"
                        maxlength="500"
                        required
                        @update:model-value="editedAddress" /></label
                ><template v-if="hasPolygons"
                    ><DeliveryMap
                        v-model:point="point"
                        mode="pick"
                        :zones="zones"
                        :map-config="mapConfig"
                        :center="mapCenter"
                    />
                    <p v-if="addressLoading" role="status" class="text-sm text-slate-500">
                        {{ $t('checkout_address_loading') }}
                    </p>
                    <p v-if="addressHint" role="status" class="text-sm text-slate-500">{{ $t(addressHint) }}</p>
                    <p v-if="point && !polygonZone" role="alert" class="text-red-700">{{ $t('map_outside') }}</p>
                    <p v-if="polygonZone" class="font-medium text-brand">
                        {{ polygonZone.name }} · {{ money(polygonZone.fee) }}
                    </p>
                    <p class="text-sm text-slate-500">{{ $t('map_address_hint') }}</p></template
                ><label v-else class="field"
                    ><span class="field-label"
                        >{{ $t('postal_code') }}<FieldHelp :label="$t('postal_code')" field="postal_code" /></span
                    ><InputText
                        v-model="form.postal_code"
                        :aria-label="$t('postal_code')"
                        autocomplete="postal-code"
                        required /></label
                ><label v-if="!hasPolygons" class="field"
                    ><span class="field-label"
                        >{{ $t('delivery_zone') }}<FieldHelp :label="$t('delivery_zone')" field="delivery_zone" /></span
                    ><Select
                        v-model="form.delivery_zone_id"
                        :aria-label="$t('delivery_zone')"
                        :options="zones"
                        option-label="name"
                        option-value="id" /></label></template
            ><label class="field"
                ><span class="field-label"
                    >{{ $t('order_notes') }}<FieldHelp :label="$t('order_notes')" field="order_notes" /></span
                ><Textarea v-model="form.notes" :aria-label="$t('order_notes')" rows="2" maxlength="1000"
            /></label>
        </form>
        <template #footer>
            <div class="grid w-full gap-3" data-testid="checkout-footer">
                <p v-if="restrictedProducts.length" role="alert" class="text-sm text-red-700">
                    {{ $t('mig_service_conflict') }}: {{ restrictedProducts.map((p) => p.name).join(', ') }}
                </p>
                <p v-if="blockedMessage" role="status" class="rounded-lg bg-amber-50 p-3 text-sm text-amber-900">
                    {{ blockedMessage }}
                </p>
                <div class="flex items-center justify-between gap-4 text-lg font-semibold">
                    <span>{{ fee ? $t('total_with_delivery') : $t('total') }}</span
                    ><span>{{ money(cart.total + fee) }}</span>
                </div>
                <p
                    v-if="form.fulfillment === 'delivery' && deliveryZone && cart.total < deliveryZone.minimum_order"
                    role="status"
                    class="text-sm text-amber-800"
                >
                    {{ $t('checkout_minimum', { total: money(deliveryZone.minimum_order) }) }}
                </p>
                <p class="text-xs leading-relaxed text-slate-500">
                    {{
                        tableSessionToken
                            ? $t('dining_payment_hint')
                            : $t('pay_at_pickup_or_on_delivery_your_order_is_subject_to_restaurant_acceptance')
                    }}
                </p>
                <Button type="submit" :form="formId" :label="$t('place_order')" :loading="busy" :disabled="!valid" />
            </div>
        </template>
    </Dialog>
</template>
