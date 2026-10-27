<script setup lang="ts">
import ProductPhoto from './ProductPhoto.vue';
import ProductFoodData from './ProductFoodData.vue';
import ProductIngredients from '@/components/storefront/ProductIngredients.vue';
import { computed, ref, watch } from 'vue';
import Dialog from 'primevue/dialog';
import Select from 'primevue/select';
import Checkbox from 'primevue/checkbox';
import Button from 'primevue/button';
import InputNumber from 'primevue/inputnumber';
import { useCart } from '@/stores/cart';
import { money } from '@/lib/http';
import type { Product } from '@/types';
const props = defineProps<{ product: Product | null; showImages?: boolean }>();
const visible = defineModel<boolean>('visible', { required: true });
const variant = ref<number | null>(null);
const choices = ref<number[]>([]);
const quantity = ref(1);
const amounts = ref<Record<number, number>>({});
const selectedVariant = computed(() => props.product?.variants.find((v) => v.id === variant.value));
const groups = computed(() =>
    [...(props.product?.modifier_groups ?? []), ...(selectedVariant.value?.modifier_groups ?? [])].filter(
        (g, i, all) => all.findIndex((x) => x.id === g.id) === i,
    ),
);
function resetChoices() {
    choices.value = groups.value.flatMap((g) => g.options.filter((o) => o.is_default).map((o) => o.id));
    amounts.value = Object.fromEntries(choices.value.map((id) => [id, 1]));
}
watch(variant, resetChoices);
const cart = useCart();
watch(visible, (open) => {
    if (open) {
        variant.value = (props.product?.variants.find((v) => v.is_default) ?? props.product?.variants[0])?.id ?? null;
        resetChoices();
        quantity.value = 1;
    }
});
const selected = computed(() => groups.value.flatMap((g) => g.options).filter((o) => choices.value.includes(o.id)));
const price = computed(
    () =>
        (props.product?.variants.find((v) => v.id === variant.value)?.price ?? props.product?.price ?? 0) +
        selected.value.reduce((sum, o) => sum + o.price * (amounts.value[o.id] ?? 1), 0),
);
const valid = computed(
    () =>
        !!props.product &&
        Number.isInteger(quantity.value) &&
        quantity.value >= 1 &&
        quantity.value <= 50 &&
        (!props.product.variants.length || props.product.variants.some((v) => v.id === variant.value)) &&
        groups.value.every((g) => {
            const count = g.options.filter((o) => choices.value.includes(o.id)).length;
            const required = g.is_required ?? g.min_choices > 0;
            return (
                ((!required && count === 0) ||
                    (count >= Math.max(required ? 1 : 0, g.min_choices) && count <= g.max_choices)) &&
                g.options.every(
                    (o) =>
                        !choices.value.includes(o.id) ||
                        (Number.isInteger(amounts.value[o.id] ?? 1) &&
                            (amounts.value[o.id] ?? 1) >= 1 &&
                            (amounts.value[o.id] ?? 1) <= (g.allow_quantity ? (o.max_quantity ?? 50) : 1)),
                )
            );
        }),
);
function add() {
    const p = props.product;
    if (!p || !valid.value) return;
    cart.add({
        key: crypto.randomUUID(),
        product_id: p.public_id,
        order_types: p.order_types,
        name: p.name,
        image_path: p.image_path,
        quantity: quantity.value,
        variant_id: variant.value,
        variant_name: p.variants.find((v) => v.id === variant.value)?.name ?? null,
        modifier_ids: [],
        modifiers: choices.value.map((id) => ({ id, quantity: amounts.value[id] ?? 1 })),
        modifier_names: selected.value.map((o) => `${amounts.value[o.id] ?? 1} × ${o.name}`),
        unit_price: price.value,
    });
    visible.value = false;
}
</script>
<template>
    <Dialog v-model:visible="visible" :header="product?.name" modal class="w-full max-w-lg"
        ><div v-if="product" class="grid gap-5">
            <div v-if="showImages !== false" class="h-48 overflow-hidden rounded-2xl sm:h-60">
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
            <ProductFoodData
                :product="
                    selectedVariant?.nutrition
                        ? { ...product, content: { ...product.content!, nutrition: selectedVariant.nutrition } }
                        : product
                "
            />
            <label v-if="product.variants.length" class="field"
                ><span class="field-label"
                    >{{ $t('size_variant') }}<FieldHelp :label="$t('size_variant')" field="size_variant" /></span
                ><Select
                    v-model="variant"
                    :aria-label="$t('size_variant')"
                    :options="product.variants.map((v) => ({ ...v, label: v.name + ' · ' + money(v.price) }))"
                    option-label="label"
                    option-value="id"
            /></label>
            <fieldset v-for="g in groups" :key="g.id" class="rounded-xl border border-slate-200 p-4">
                <legend class="px-2 font-semibold">
                    {{ g.name }} <span class="muted">({{ g.min_choices }}–{{ g.max_choices }})</span>
                </legend>
                <label v-for="o in g.options" :key="o.id" class="flex items-center gap-3 py-2"
                    ><Checkbox v-model="choices" :aria-label="o.name" :value="o.id" /><span class="field-label"
                        >{{ o.name }}<FieldHelp :label="o.name" field="options" /></span
                    ><span v-if="g.allow_quantity && choices.includes(o.id)" class="field-control-help"
                        ><InputNumber
                            :model-value="amounts[o.id] ?? 1"
                            :aria-label="$t('quantity') + ' · ' + o.name"
                            :min="1"
                            :max="o.max_quantity ?? 50"
                            show-buttons
                            class="w-28"
                            @update:model-value="amounts[o.id] = $event ?? 1" /><FieldHelp
                            :label="$t('quantity')"
                            :text="$t('mig_quantity_help')" /></span
                    ><span class="ml-auto text-sm">+ {{ money(o.price) }}</span></label
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
