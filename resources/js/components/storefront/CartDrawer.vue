<script setup lang="ts">
import { useCart } from '@/stores/cart';
import ZFDrawer from '@/components/ui/ZFDrawer.vue';
import Button from 'primevue/button';
import { money } from '@/lib/http';
const visible = defineModel<boolean>('visible', { required: true });
const emit = defineEmits<{ checkout: [] }>();
const cart = useCart();
</script>
<template>
    <ZFDrawer v-model:visible="visible" title="Your cart"
        ><p v-if="!cart.lines.length" class="muted py-8">Your next favourite meal is waiting.</p>
        <div v-for="line in cart.lines" :key="line.key" class="border-b border-slate-200 py-5">
            <div class="flex justify-between gap-3">
                <strong>{{ line.quantity }} × {{ line.name }}</strong
                ><span>{{ money(line.quantity * line.unit_price) }}</span>
            </div>
            <p class="muted mt-2">{{ [line.variant_name, ...line.modifier_names].filter(Boolean).join(', ') }}</p>
            <Button label="Remove" text size="small" @click="cart.remove(line.key)" />
        </div>
        <div v-if="cart.lines.length" class="mt-6 grid gap-5">
            <div class="flex justify-between text-lg font-semibold">
                <span>Subtotal</span><span>{{ money(cart.total) }}</span>
            </div>
            <p class="muted">Final availability and totals are confirmed at checkout.</p>
            <Button
                label="Continue to checkout"
                icon="pi pi-arrow-right"
                @click="
                    visible = false;
                    emit('checkout');
                "
            /></div
    ></ZFDrawer>
</template>
