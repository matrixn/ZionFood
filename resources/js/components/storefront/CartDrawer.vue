<script setup lang="ts">
import CartProductImage from './CartProductImage.vue';
import { useCart } from '@/stores/cart';
import ZFDrawer from '@/components/ui/ZFDrawer.vue';
import Button from 'primevue/button';
import { money } from '@/lib/http';
defineProps<{ showImages?: boolean; canCheckout?: boolean; blockedMessage?: string }>();
const visible = defineModel<boolean>('visible', { required: true });
const emit = defineEmits<{ checkout: [] }>();
const cart = useCart();
</script>
<template>
    <ZFDrawer v-model:visible="visible" :title="$t('your_cart')"
        ><p v-if="!cart.lines.length" class="muted py-8">{{ $t('your_next_favourite_meal_is_waiting') }}</p>
        <article
            v-for="line in cart.lines"
            :key="line.key"
            class="flex items-center gap-3 border-b border-slate-100 py-3 dark:border-slate-800"
            data-testid="cart-line"
        >
            <CartProductImage v-if="showImages !== false" :path="line.image_path" :name="line.name" />
            <div class="min-w-0 flex-1">
                <p class="text-sm font-semibold leading-snug">{{ line.quantity }} × {{ line.name }}</p>
                <p
                    v-if="line.variant_name || line.modifier_names.length"
                    class="mt-1 text-xs leading-snug text-slate-500"
                >
                    {{ [line.variant_name, ...line.modifier_names].filter(Boolean).join(', ') }}
                </p>
            </div>
            <div class="flex shrink-0 items-center gap-1">
                <strong class="text-sm">{{ money(line.quantity * line.unit_price) }}</strong>
                <Button
                    icon="pi pi-trash"
                    text
                    size="small"
                    :aria-label="$t('cart_remove_named', { name: line.name })"
                    @click="cart.remove(line.key)"
                />
            </div>
        </article>
        <template #footer>
            <div v-if="cart.lines.length" class="grid w-full gap-3">
                <div class="flex justify-between text-lg font-semibold">
                    <span>{{ $t('subtotal') }}</span
                    ><span>{{ money(cart.total) }}</span>
                </div>
                <p class="muted">{{ $t('final_availability_and_totals_are_confirmed_at_checkout') }}</p>
                <p v-if="blockedMessage" role="status" class="rounded-lg bg-amber-50 p-3 text-sm text-amber-900">
                    {{ blockedMessage }}
                </p>
                <Button
                    :disabled="canCheckout === false"
                    :label="$t('continue_to_checkout')"
                    icon="pi pi-arrow-right"
                    @click="
                        visible = false;
                        emit('checkout');
                    "
                />
            </div>
        </template>
    </ZFDrawer>
</template>
