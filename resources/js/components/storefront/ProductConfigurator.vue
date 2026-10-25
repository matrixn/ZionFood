<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import Dialog from 'primevue/dialog';
import Select from 'primevue/select';
import Checkbox from 'primevue/checkbox';
import InputNumber from 'primevue/inputnumber';
import Button from 'primevue/button';
import { useCart } from '@/stores/cart';
import { money } from '@/lib/http';
import type { Product } from '@/types';
const props = defineProps<{ product: Product | null }>();
const visible = defineModel<boolean>('visible', { required: true });
const variant = ref<number | null>(null);
const choices = ref<number[]>([]);
const quantity = ref(1);
const cart = useCart();
watch(visible, (open) => {
    if (open) {
        variant.value = props.product?.variants[0]?.id ?? null;
        choices.value = [];
        quantity.value = 1;
    }
});
const selected = computed(
    () => props.product?.modifier_groups.flatMap((g) => g.options).filter((o) => choices.value.includes(o.id)) ?? [],
);
const price = computed(
    () =>
        (props.product?.variants.find((v) => v.id === variant.value)?.price ?? props.product?.price ?? 0) +
        selected.value.reduce((sum, o) => sum + o.price, 0),
);
const valid = computed(
    () =>
        !!props.product &&
        props.product.modifier_groups.every((g) => {
            const count = g.options.filter((o) => choices.value.includes(o.id)).length;
            return count >= g.min_choices && count <= g.max_choices;
        }),
);
function add() {
    const p = props.product;
    if (!p || !valid.value) return;
    cart.add({
        key: crypto.randomUUID(),
        product_id: p.public_id,
        name: p.name,
        quantity: quantity.value,
        variant_id: variant.value,
        variant_name: p.variants.find((v) => v.id === variant.value)?.name ?? null,
        modifier_ids: [...choices.value],
        modifier_names: selected.value.map((o) => o.name),
        unit_price: price.value,
    });
    visible.value = false;
}
</script>
<template>
    <Dialog v-model:visible="visible" :header="product?.name" modal class="w-full max-w-lg"
        ><div v-if="product" class="grid gap-5">
            <p class="muted">{{ product.description }}</p>
            <label v-if="product.variants.length" class="field"
                >{{ $t('size_variant')
                }}<Select
                    v-model="variant"
                    :options="product.variants.map((v) => ({ ...v, label: v.name + ' · ' + money(v.price) }))"
                    option-label="label"
                    option-value="id"
            /></label>
            <fieldset v-for="g in product.modifier_groups" :key="g.id" class="rounded-xl border border-slate-200 p-4">
                <legend class="px-2 font-semibold">
                    {{ g.name }} <span class="muted">({{ g.min_choices }}–{{ g.max_choices }})</span>
                </legend>
                <label v-for="o in g.options" :key="o.id" class="flex items-center gap-3 py-2"
                    ><Checkbox v-model="choices" :value="o.id" />{{ o.name
                    }}<span class="ml-auto text-sm">+ {{ money(o.price) }}</span></label
                >
            </fieldset>
            <label class="field"
                >{{ $t('quantity') }}<InputNumber v-model="quantity" :min="1" :max="50" show-buttons /></label
            ><Button
                :label="$t('add_to_cart_total', { total: money(price * quantity) })"
                :disabled="!valid"
                @click="add"
            /></div
    ></Dialog>
</template>
