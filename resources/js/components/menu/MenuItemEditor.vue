<script setup lang="ts">
import { ref, watch } from 'vue';
import Dialog from 'primevue/dialog';
import Button from 'primevue/button';
import InputText from 'primevue/inputtext';
import Textarea from 'primevue/textarea';
import InputNumber from 'primevue/inputnumber';
import Select from 'primevue/select';
import MultiSelect from 'primevue/multiselect';
import ToggleSwitch from 'primevue/toggleswitch';
import ZFFormField from '@/components/ui/ZFFormField.vue';
import { api } from '@/lib/http';
import type { Product, Category, ModifierGroup } from '@/types';
const props = defineProps<{
    locationId: string;
    product: Product | null;
    categories: Category[];
    groups: ModifierGroup[];
}>();
const visible = defineModel<boolean>('visible', { required: true });
const emit = defineEmits<{ saved: [] }>();
const form = ref({
    category_id: 0,
    name: '',
    description: '',
    price: 0,
    tax_basis_points: 0,
    is_available: true,
    variants: [] as { name: string; price: number }[],
    modifier_group_ids: [] as number[],
});
const image = ref<File>();
const error = ref('');
const busy = ref(false);
watch(visible, (open) => {
    if (!open) return;
    const p = props.product;
    form.value = {
        category_id: p?.category_id ?? props.categories[0]?.id ?? 0,
        name: p?.name ?? '',
        description: p?.description ?? '',
        price: p?.price ?? 0,
        tax_basis_points: p?.tax_basis_points ?? 0,
        is_available: p?.is_available ?? true,
        variants: p?.variants.map((v) => ({ name: v.name, price: v.price })) ?? [],
        modifier_group_ids: p?.modifier_groups.map((g) => g.id) ?? [],
    };
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
        ><form class="grid gap-5" @submit.prevent="save">
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
                <ZFFormField :label="$t('price_bani')"
                    ><InputNumber v-model="form.price" :min="0" :max="10000000" input-id="product-price" /></ZFFormField
                ><ZFFormField :label="$t('tax_rate_basis_points')"
                    ><InputNumber v-model="form.tax_basis_points" :min="0" :max="10000"
                /></ZFFormField>
            </div>
            <div class="flex items-center gap-3">
                <ToggleSwitch v-model="form.is_available" input-id="available" /><label for="available">{{
                    $t('available_to_order')
                }}</label>
            </div>
            <ZFFormField :label="$t('modifier_groups')"
                ><MultiSelect
                    v-model="form.modifier_group_ids"
                    :options="groups"
                    option-label="name"
                    option-value="id"
                    :aria-label="$t('modifier_groups')"
            /></ZFFormField>
            <div>
                <h3 class="mb-3 font-semibold">{{ $t('variants') }}</h3>
                <div v-for="(variant, i) in form.variants" :key="i" class="mb-3 flex gap-2">
                    <InputText
                        v-model="variant.name"
                        :placeholder="$t('variant_name')"
                        :aria-label="$t('variant_name')"
                        required
                    /><InputNumber v-model="variant.price" :min="0" :aria-label="$t('variant_price_in_bani')" /><Button
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
                    @click="form.variants.push({ name: '', price: 0 })"
                />
            </div>
            <ZFFormField :label="$t('product_image_jpeg_png_or_webp_max_4_mb')"
                ><input
                    type="file"
                    accept="image/jpeg,image/png,image/webp"
                    :aria-label="$t('product_image')"
                    @change="image = ($event.target as HTMLInputElement).files?.[0]" /></ZFFormField
            ><Button :label="$t('save_product')" type="submit" :loading="busy" /></form
    ></Dialog>
</template>
