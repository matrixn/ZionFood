<script setup lang="ts">
import { tr as t } from '@/i18n';

import { computed, ref, watch } from 'vue';
import Dialog from 'primevue/dialog';
import InputText from 'primevue/inputtext';
import Textarea from 'primevue/textarea';
import Select from 'primevue/select';
import Button from 'primevue/button';
import { router } from '@inertiajs/vue3';
import { useCart } from '@/stores/cart';
import { api, money } from '@/lib/http';
import type { Zone } from '@/types';
const props = defineProps<{
    locationId: string;
    fulfillment: { pickup_enabled: boolean; delivery_enabled: boolean };
    zones: Zone[];
}>();
const visible = defineModel<boolean>('visible', { required: true });
const cart = useCart();
const form = ref({
    customer_name: '',
    customer_phone: '',
    fulfillment: props.fulfillment.pickup_enabled ? 'pickup' : 'delivery',
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
    [
        { label: t('pickup'), value: 'pickup', enabled: props.fulfillment.pickup_enabled },
        { label: t('delivery'), value: 'delivery', enabled: props.fulfillment.delivery_enabled },
    ].filter((m) => m.enabled),
);
const fee = computed(() =>
    form.value.fulfillment === 'delivery'
        ? (props.zones.find((z) => z.id === form.value.delivery_zone_id)?.fee ?? 0)
        : 0,
);
watch(visible, (open) => {
    if (open) error.value = '';
});
async function submit() {
    busy.value = true;
    error.value = '';
    const payload = {
        ...form.value,
        items: cart.lines.map((l) => ({
            product_id: l.product_id,
            variant_id: l.variant_id,
            quantity: l.quantity,
            modifier_ids: l.modifier_ids,
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
        sessionStorage.setItem(`zf-order-${result.order_id}`, result.tracking_token);
        cart.clear();
        visible.value = false;
        router.visit(`/track/${result.order_id}`);
    } catch (e) {
        error.value = (e as Error).message;
    } finally {
        busy.value = false;
    }
}
</script>
<template>
    <Dialog v-model:visible="visible" :header="$t('almost_at_your_table')" modal class="w-full max-w-xl"
        ><form class="grid gap-5" @submit.prevent="submit">
            <p v-if="error" role="alert" class="rounded-lg bg-red-50 p-3 text-red-700">{{ error }}</p>
            <label class="field"
                >{{ $t('your_name') }}<InputText v-model="form.customer_name" autocomplete="name" required /></label
            ><label class="field"
                >{{ $t('phone_number')
                }}<InputText v-model="form.customer_phone" type="tel" autocomplete="tel" required /></label
            ><label class="field"
                >{{ $t('how_would_you_like_your_order')
                }}<Select
                    v-model="form.fulfillment"
                    :options="methods"
                    option-label="label"
                    option-value="value" /></label
            ><template v-if="form.fulfillment === 'delivery'"
                ><label class="field"
                    >{{ $t('delivery_address')
                    }}<InputText v-model="form.delivery_address" autocomplete="street-address" required /></label
                ><label class="field"
                    >{{ $t('postal_code')
                    }}<InputText v-model="form.postal_code" autocomplete="postal-code" required /></label
                ><label class="field"
                    >{{ $t('delivery_zone')
                    }}<Select
                        v-model="form.delivery_zone_id"
                        :options="zones"
                        option-label="name"
                        option-value="id" /></label></template
            ><label class="field"
                >{{ $t('order_notes') }}<Textarea v-model="form.notes" rows="2" maxlength="1000"
            /></label>
            <div class="flex justify-between text-lg font-semibold">
                <span>{{ fee ? $t('total_with_delivery') : $t('total') }}</span
                ><span>{{ money(cart.total + fee) }}</span>
            </div>
            <p class="muted">{{ $t('pay_at_pickup_or_on_delivery_your_order_is_subject_to_restaurant_acceptance') }}</p>
            <Button
                type="submit"
                :label="$t('place_order')"
                :loading="busy"
                :disabled="!cart.lines.length || !methods.length"
            /></form
    ></Dialog>
</template>
