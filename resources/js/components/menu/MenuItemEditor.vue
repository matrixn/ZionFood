<script setup lang="ts">
import { useId, computed, ref, watch } from 'vue';
import Dialog from 'primevue/dialog';
import Button from 'primevue/button';
import InputText from 'primevue/inputtext';
import Textarea from 'primevue/textarea';
import InputNumber from 'primevue/inputnumber';
import Select from 'primevue/select';
import MultiSelect from 'primevue/multiselect';
import Checkbox from 'primevue/checkbox';
import ToggleSwitch from 'primevue/toggleswitch';
import ZFFormField from '@/components/ui/ZFFormField.vue';
import { localeTag } from '@/i18n';
import { ingredientName } from '@/lib/ingredients';
import { api } from '@/lib/http';
import type { Product, Category, ModifierGroup, Ingredient } from '@/types';
const productFormId = useId();
const props = defineProps<{
    locationId: string;
    product: Product | null;
    categories: Category[];
    groups: ModifierGroup[];
    ingredients: Ingredient[];
}>();
const visible = defineModel<boolean>('visible', { required: true });
const emit = defineEmits<{ saved: [] }>();
const form = ref({
    order_types: null as string[] | null,
    kitchen_name: '',
    category_id: 0,
    name: '',
    description: '',
    price: 0,
    portion_size: '',
    ingredient_ids: [] as number[],
    contains_allergens: false,
    from_frozen: false,
    tax_basis_points: 0,
    is_available: true,
    variants: [] as { id?: number; name: string; price: number; is_default: boolean; modifier_group_ids: number[] }[],
    modifier_group_ids: [] as number[],
});
const priceInLei = computed({
    get: () => form.value.price / 100,
    set: (value: number | null) => {
        form.value.price = Math.round((value ?? 0) * 100);
    },
});
const ingredientOptions = computed(() =>
    props.ingredients
        .filter((i) => (i.is_active && !i.deleted_at) || form.value.ingredient_ids.includes(i.id))
        .map((i) => ({ ...i, label: ingredientName(i) })),
);
const ingredientSearch = ref('');
const ingredientMatches = computed(() => {
    const clean = (value: string) => value.normalize('NFD').replace(/\p{M}/gu, '').toLowerCase();
    return ingredientOptions.value.filter((i) => clean(i.label).includes(clean(ingredientSearch.value)));
});
const selectedIngredients = computed(() =>
    form.value.ingredient_ids
        .map((id) => ingredientOptions.value.find((i) => i.id === id))
        .filter((i): i is NonNullable<typeof i> => !!i),
);
const image = ref<File>();
const error = ref('');
const busy = ref(false);
watch(visible, (open) => {
    if (!open) return;
    const p = props.product;
    form.value = {
        order_types: p?.order_types ?? null,
        kitchen_name: p?.kitchen_name ?? '',
        category_id: p?.category_id ?? props.categories[0]?.id ?? 0,
        name: p?.name ?? '',
        description: p?.description ?? '',
        price: p?.price ?? 0,
        portion_size: p?.portion_size ?? '',
        ingredient_ids: p?.ingredients?.map((i) => i.id) ?? [],
        contains_allergens: p?.contains_allergens ?? false,
        from_frozen: p?.from_frozen ?? false,
        tax_basis_points: p?.tax_basis_points ?? 0,
        is_available: p?.is_available ?? true,
        variants:
            p?.variants.map((v) => ({
                id: v.id,
                name: v.name,
                price: v.price,
                is_default: v.is_default ?? false,
                modifier_group_ids: v.modifier_groups?.map((g) => g.id) ?? [],
            })) ?? [],
        modifier_group_ids: p?.modifier_groups.map((g) => g.id) ?? [],
    };
    ingredientSearch.value = '';
    image.value = undefined;
    error.value = '';
});
async function save() {
    busy.value = true;
    error.value = '';
    try {
        const endpoint = props.product
            ? `/api/v1/items/${props.product.public_id}`
            : `/api/v1/locations/${props.locationId}/items`;
        if (image.value) {
            const data = new FormData();
            data.append('_method', props.product ? 'PUT' : 'POST');
            data.append('payload', JSON.stringify(form.value));
            data.append('image', image.value);
            await api(endpoint, 'POST', data);
        } else await api(endpoint, props.product ? 'PUT' : 'POST', form.value);
        visible.value = false;
        emit('saved');
    } catch (e) {
        error.value = (e as Error).message;
    } finally {
        busy.value = false;
    }
}
</script>
<template>
    <Dialog
        v-model:visible="visible"
        :header="product ? $t('edit_product') : $t('create_product')"
        modal
        class="w-full max-w-2xl"
        ><form :id="productFormId" class="grid gap-5" @submit.prevent="save">
            <p v-if="error" role="alert" class="text-red-600">{{ error }}</p>
            <ZFFormField :label="$t('name')" for="product-name"
                ><InputText id="product-name" v-model="form.name" required /></ZFFormField
            ><ZFFormField :label="$t('category')"
                ><Select
                    v-model="form.category_id"
                    :options="categories"
                    option-label="name"
                    option-value="id"
                    :aria-label="$t('product_category')" /></ZFFormField
            ><ZFFormField :label="$t('description')"
                ><Textarea v-model="form.description" rows="3" :aria-label="$t('product_description')"
            /></ZFFormField>
            <div class="grid grid-cols-2 gap-4">
                <ZFFormField :label="$t('product_price_lei')" for="product-price"
                    ><InputNumber
                        v-model="priceInLei"
                        :min="0"
                        :max="100000"
                        :min-fraction-digits="2"
                        :max-fraction-digits="2"
                        mode="currency"
                        currency="RON"
                        :locale="localeTag()"
                        input-id="product-price"
                        :aria-label="$t('product_price_lei')" /></ZFFormField
                ><ZFFormField :label="$t('tax_rate_basis_points')"
                    ><InputNumber v-model="form.tax_basis_points" :min="0" :max="10000"
                /></ZFFormField>
            </div>
            <ZFFormField :label="$t('product_portion')" for="product-portion">
                <InputText
                    id="product-portion"
                    v-model="form.portion_size"
                    maxlength="50"
                    :placeholder="$t('product_portion_hint')"
                />
            </ZFFormField>
            <fieldset
                class="min-w-0 space-y-3 rounded-xl border border-slate-200 p-3"
                data-testid="product-ingredients"
            >
                <legend class="px-1 font-semibold">{{ $t('ingredients_title') }}</legend>
                <span class="field-control-help"
                    ><InputText
                        v-model="ingredientSearch"
                        :aria-label="$t('ingredients_search')"
                        :placeholder="$t('ingredients_search')" /><FieldHelp
                        :label="$t('ingredients_search')"
                        field="ingredientSearch"
                        kind="InputText"
                /></span>
                <p class="text-xs text-slate-500" aria-live="polite">
                    {{ $t('ingredients_selected_count', { count: form.ingredient_ids.length }) }}
                </p>
                <div v-if="selectedIngredients.length" class="flex flex-wrap gap-2" data-testid="selected-ingredients">
                    <button
                        v-for="i in selectedIngredients"
                        :key="i.id"
                        type="button"
                        class="max-w-full rounded-lg bg-violet-50 px-2 py-1 text-left text-sm text-brand"
                        :aria-label="$t('ingredients_unselect', { name: i.label })"
                        @click="form.ingredient_ids = form.ingredient_ids.filter((id) => id !== i.id)"
                    >
                        {{ i.label }} <i class="pi pi-times ml-1 text-xs" aria-hidden="true" />
                    </button>
                </div>
                <div
                    class="grid max-h-52 gap-2 overflow-y-auto rounded-lg bg-slate-50 p-2 dark:bg-slate-800 sm:grid-cols-2"
                >
                    <label
                        v-for="i in ingredientMatches"
                        :key="i.id"
                        class="flex min-w-0 items-start gap-2 rounded-lg p-2 text-sm hover:bg-white dark:hover:bg-slate-700"
                    >
                        <Checkbox v-model="form.ingredient_ids" :value="i.id" :aria-label="i.label" />
                        <span class="min-w-0 break-words"
                            ><span class="field-label"
                                >{{ i.label }}<FieldHelp :label="i.label" field="ingredients" /></span
                            ><small v-if="!i.is_active || i.deleted_at" class="block text-slate-500">{{
                                $t('ingredients_hidden')
                            }}</small></span
                        >
                    </label>
                    <p v-if="!ingredientMatches.length" class="p-2 text-sm text-slate-500">
                        {{ $t('ingredients_none_found') }}
                    </p>
                </div>
            </fieldset>
            <p
                v-if="
                    form.ingredient_ids.some(
                        (id) =>
                            ingredients.find((i) => i.id === id)?.is_active === false ||
                            ingredients.find((i) => i.id === id)?.deleted_at,
                    )
                "
                role="status"
                class="text-sm text-amber-800"
            >
                {{ $t('ingredients_retained') }}
            </p>
            <p class="text-xs text-slate-500">{{ $t('ingredients_product_hint') }}</p>
            <label class="flex items-center gap-3"
                ><ToggleSwitch v-model="form.contains_allergens" :aria-label="$t('ingredients_allergens')" /><span
                    class="field-label"
                    >{{ $t('ingredients_allergens')
                    }}<FieldHelp :label="$t('ingredients_allergens')" field="ingredients_allergens" /></span
            ></label>
            <label class="flex items-center gap-3"
                ><ToggleSwitch v-model="form.from_frozen" :aria-label="$t('ingredients_frozen')" /><span
                    class="field-label"
                    >{{ $t('ingredients_frozen')
                    }}<FieldHelp :label="$t('ingredients_frozen')" field="ingredients_frozen" /></span
            ></label>
            <ZFFormField :label="$t('mig_order_types')" :help="$t('mig_order_types_help')"
                ><MultiSelect
                    v-model="form.order_types"
                    :options="['pickup', 'delivery', 'dine_in']"
                    :aria-label="$t('mig_order_types')" /><Button
                    text
                    size="small"
                    :label="$t('mig_all_services')"
                    @click="form.order_types = null"
            /></ZFFormField>
            <ZFFormField :label="$t('mig_kitchen_name')"
                ><InputText v-model="form.kitchen_name" :aria-label="$t('mig_kitchen_name')"
            /></ZFFormField>
            <div class="flex items-center gap-3">
                <ToggleSwitch v-model="form.is_available" input-id="available" /><label for="available"
                    ><span class="field-label"
                        >{{ $t('available_to_order')
                        }}<FieldHelp :label="$t('available_to_order')" field="available_to_order" /></span
                ></label>
            </div>
            <ZFFormField :label="$t('modifier_groups')"
                ><MultiSelect
                    v-model="form.modifier_group_ids"
                    append-to="self"
                    :options="groups"
                    option-label="name"
                    option-value="id"
                    :aria-label="$t('modifier_groups')"
            /></ZFFormField>
            <div>
                <h3 class="mb-3 font-semibold">{{ $t('variants') }}</h3>
                <div
                    v-for="(variant, i) in form.variants"
                    :key="i"
                    class="mb-3 flex flex-wrap gap-2 rounded-xl border border-slate-200 p-3"
                >
                    <span class="field-control-help"
                        ><InputText
                            v-model="variant.name"
                            :placeholder="$t('variant_name')"
                            :aria-label="$t('variant_name')"
                            required /><FieldHelp :label="$t('variant_name')" field="name" kind="InputText" /></span
                    ><span class="field-control-help"
                        ><InputNumber
                            :model-value="variant.price / 100"
                            :min="0"
                            :max="100000"
                            :min-fraction-digits="2"
                            :max-fraction-digits="2"
                            mode="currency"
                            currency="RON"
                            :locale="localeTag()"
                            :aria-label="$t('product_variant_price_lei')"
                            @update:model-value="variant.price = Math.round(($event ?? 0) * 100)" /><FieldHelp
                            :label="$t('product_variant_price_lei')"
                            field=""
                            kind="InputNumber" /></span
                    ><ZFFormField :label="$t('mig_size_groups')"
                        ><MultiSelect
                            v-model="variant.modifier_group_ids"
                            :options="groups"
                            option-label="name"
                            option-value="id"
                            :aria-label="$t('mig_size_groups')"
                    /></ZFFormField>
                    <label class="flex items-center gap-2"
                        ><Checkbox v-model="variant.is_default" binary :aria-label="$t('mig_default')" /><span
                            >{{ $t('mig_default')
                            }}<FieldHelp :label="$t('mig_default')" :text="$t('mig_default_help')" /></span></label
                    ><Button
                        icon="pi pi-times"
                        text
                        :aria-label="$t('remove_variant')"
                        @click="form.variants.splice(i, 1)"
                    />
                </div>
                <Button
                    :label="$t('add_variant')"
                    size="small"
                    text
                    icon="pi pi-plus"
                    @click="form.variants.push({ name: '', price: 0, is_default: false, modifier_group_ids: [] })"
                />
            </div>
            <ZFFormField :label="$t('product_image_jpeg_png_or_webp_max_4_mb')"
                ><input
                    type="file"
                    accept="image/jpeg,image/png,image/webp"
                    :aria-label="$t('product_image')"
                    @change="image = ($event.target as HTMLInputElement).files?.[0]"
            /></ZFFormField>
        </form>
        <template #footer
            ><Button
                :form="productFormId"
                :disabled="
                    !form.name.trim() ||
                    !form.category_id ||
                    !Number.isInteger(form.price) ||
                    form.variants.some((v) => !v.name.trim()) ||
                    busy
                "
                :label="$t('save_product')"
                type="submit"
                :loading="busy"
        /></template>
    </Dialog>
</template>

<style scoped>
.field {
    min-width: 0;
}
:deep(.p-inputtext),
:deep(.p-inputnumber),
:deep(.p-select),
:deep(.p-multiselect) {
    width: 100%;
    min-width: 0;
    max-width: 100%;
}
</style>
