<script setup lang="ts">
import { computed, ref } from 'vue';
import Button from 'primevue/button';
import ZFStatusBadge from '@/components/ui/ZFStatusBadge.vue';
import { api } from '@/lib/http';
import type { Order, OrderStatus } from '@/types';
const props = defineProps<{ order: Order; now: number }>();
const emit = defineEmits<{ changed: []; details: [order: Order] }>();
const busy = ref(false);
const error = ref('');
const reason = ref('');
const age = computed(() => Math.max(0, Math.floor((props.now - new Date(props.order.created_at).getTime()) / 60000)));
async function transition(status: OrderStatus) {
    busy.value = true;
    error.value = '';
    try {
        await api('/api/v1/orders/' + props.order.public_id + '/status', 'PATCH', {
            status,
            version: props.order.version,
            reason: reason.value || null,
        });
        emit('changed');
    } catch (e) {
        error.value = (e as Error).message;
        emit('changed');
    } finally {
        busy.value = false;
    }
}
</script>
<template>
    <article class="panel !p-5">
        <div class="mb-4 flex justify-between gap-2">
            <button class="font-semibold text-brand" @click="emit('details', order)">
                #{{ order.public_id.slice(-8) }}</button
            ><span :class="age > 20 ? 'text-red-600' : 'muted'">{{ age }} min</span>
        </div>
        <ZFStatusBadge :status="order.status" />
        <p class="my-3 text-sm font-medium">{{ order.customer_name }} · {{ order.fulfillment }}</p>
        <div v-for="item in order.items" :key="item.id" class="my-3 border-t border-slate-100 pt-3">
            <strong class="text-sm">{{ item.quantity }} × {{ item.name }}</strong>
            <p class="muted">{{ item.variant_name }}</p>
            <p v-for="mod in item.modifiers" :key="mod.id" class="muted">+ {{ mod.name }}</p>
        </div>
        <p v-if="order.notes" class="my-3 rounded-lg bg-amber-50 p-3 text-sm text-amber-900">{{ order.notes }}</p>
        <p v-if="error" role="alert" class="my-3 text-sm text-red-600">{{ error }}</p>
        <div class="mt-4 grid gap-2">
            <Button
                v-for="status in order.transitions.filter((s) => !['rejected', 'cancelled'].includes(s))"
                :key="status"
                :label="
                    (
                        {
                            accepted: 'Accept order',
                            preparing: 'Start preparing',
                            ready: 'Mark ready',
                            out_for_delivery: 'Send for delivery',
                            completed: 'Complete order',
                        } as Record<string, string>
                    )[status]
                "
                :loading="busy"
                class="!min-h-12"
                @click="transition(status)"
            /><template v-if="order.status === 'pending'"
                ><input
                    v-model="reason"
                    placeholder="Reason for rejection"
                    aria-label="Reason for rejection"
                    maxlength="500"
                    class="rounded-lg border border-slate-300 bg-transparent p-3 text-sm" /><Button
                    label="Reject order"
                    severity="danger"
                    outlined
                    :disabled="!reason.trim()"
                    :loading="busy"
                    @click="transition('rejected')" /></template
            ><Button
                v-if="order.transitions.includes('cancelled')"
                label="Cancel order"
                severity="secondary"
                text
                :loading="busy"
                @click="transition('cancelled')"
            />
        </div>
    </article>
</template>
