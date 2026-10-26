<script setup lang="ts">
import ProductPhoto from './ProductPhoto.vue';
import ProductIngredients from '@/components/storefront/ProductIngredients.vue';
import { computed, ref, watch } from 'vue';
import Dialog from 'primevue/dialog';
import Select from 'primevue/select';
import Checkbox from 'primevue/checkbox';
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
        Number.isInteger(quantity.value) &&
        quantity.value >= 1 &&
        quantity.value <= 50 &&
        (!props.product.variants.length || props.product.variants.some((v) => v.id === variant.value)) &&
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
        image_path: p.image_path,
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
            <div class="h-48 overflow-hidden rounded-2xl sm:h-60">
                <ProductPhoto :path="product.image_path" :name="product.name" />
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <span
                    v-if="product.portion_size"
                    class="rounded-full border border-slate-200 px-3 py-1 text-xs font-medium text-slate-600"
                    >{{ $t('product_serving') }} · {{ product.portion_size }}</span
                >
                <span
                    v-if="product.contains_allergens || product.ingredients?.some((i) => i.contains_allergens)"
                    class="rounded-full bg-amber-50 px-3 py-1 text-xs text-amber-800"
                    >{{ $t('product_allergen_badge') }}</span
                >
                <span
                    v-if="product.from_frozen || product.ingredients?.some((i) => i.from_frozen)"
                    class="rounded-full bg-sky-50 px-3 py-1 text-xs text-sky-800"
                    >{{ $t('product_frozen_badge') }}</span
                >
            </div>
            <p v-if="product.description" class="text-sm leading-relaxed text-slate-600 dark:text-slate-300">
                {{ product.description }}
            </p>
            <ProductIngredients :product="product" />
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
        </div>
        <template #footer>
            <div class="grid w-full gap-3">
                <div class="flex items-center justify-between gap-4">
                    <div class="flex items-center gap-2" :aria-label="$t('quantity')">
                        <Button
                            icon="pi pi-minus"
                            outlined
                            rounded
                            size="small"
                            :aria-label="$t('decrease_quantity')"
                            :disabled="quantity <= 1"
                            @click="quantity--"
                        />
                        <span class="min-w-6 text-center font-semibold" aria-live="polite">{{ quantity }}</span>
                        <Button
                            icon="pi pi-plus"
                            outlined
                            rounded
                            size="small"
                            :aria-label="$t('increase_quantity')"
                            :disabled="quantity >= 50"
                            @click="quantity++"
                        />
                    </div>
                    <div class="text-right">
                        <p class="text-xs text-slate-500">{{ $t('total') }}</p>
                        <strong class="text-xl">{{ money(price * quantity) }}</strong>
                    </div>
                </div>
                <Button
                    class="w-full"
                    :label="$t('add_to_cart_total', { total: money(price * quantity) })"
                    icon="pi pi-shopping-bag"
                    :disabled="!valid"
                    @click="add"
                />
            </div>
        </template>
    </Dialog>
</template>
