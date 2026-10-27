<script setup lang="ts">
import { useSetupEditorGuard } from '@/lib/useSetupEditorGuard';
import { tr as t } from '@/i18n';

import { useId, computed, ref } from 'vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import RestaurantLayout from '@/layouts/RestaurantLayout.vue';
import ZFPageHeader from '@/components/ui/ZFPageHeader.vue';
import ZFEmptyState from '@/components/ui/ZFEmptyState.vue';
import IngredientManager from '@/components/menu/IngredientManager.vue';
import MenuItemEditor from '@/components/menu/MenuItemEditor.vue';
import ModifierGroupEditor from '@/components/menu/ModifierGroupEditor.vue';
import Button from 'primevue/button';
import InputText from 'primevue/inputtext';
import Select from 'primevue/select';
import Dialog from 'primevue/dialog';
import { api, money } from '@/lib/http';
import { useConfirm } from 'primevue/useconfirm';
import ZFConfirmDialog from '@/components/ui/ZFConfirmDialog.vue';
import type { WorkspaceProps, Menu, Product, ModifierGroup, Category, Ingredient } from '@/types';
const categoryFormId = useId();
const props = defineProps<
    WorkspaceProps & {
        menus: Menu[];
        groups: ModifierGroup[];
        ingredients: Ingredient[];
        menuWritable: boolean;
        modifierWritable: boolean;
    }
>();
const categories = computed(() => props.menus.flatMap((m) => m.categories));
const editor = ref(false);
const ingredientsOpen = ref(false);
const product = ref<Product | null>(null);
const groupEditor = ref(false);
const group = ref<ModifierGroup | null>(null);
const categoryEditor = ref(false);
const confirm = useConfirm();
const error = ref('');
const category = useForm({ id: null as number | null, menu_id: props.menus[0]?.id ?? 0, name: '' });
function edit(p: Product | null) {
    product.value = p;
    editor.value = true;
}
function editCategory(c?: Category) {
    category.id = c?.id ?? null;
    category.menu_id = c?.menu_id ?? props.menus[0]?.id ?? 0;
    category.name = c?.name ?? '';
    category.clearErrors();
    categoryEditor.value = true;
}
function remove(p: Product) {
    confirm.require({
        message: t('remove_product_confirm', { name: p.name }),
        header: t('remove_product'),
        accept: async () => {
            try {
                await api('/app/items/' + p.public_id, 'DELETE');
                router.reload();
            } catch (e) {
                error.value = (e as Error).message;
            }
        },
    });
}
useSetupEditorGuard(
    () => !!props.embedded,
    () => editor.value || groupEditor.value || (categoryEditor.value && category.isDirty),
);
</script>
<template>
    <RestaurantLayout v-bind="$props"
        ><Head v-if="!embedded" :title="$t('menu_studio')" /><ZFPageHeader
            :title="$t('made_to_be_ordered')"
            :description="$t('build_a_menu_that_feels_like_your_restaurant')"
            ><div v-if="menuWritable" class="flex flex-wrap gap-2">
                <Button
                    v-if="canManage"
                    :label="$t('ingredients_manage')"
                    outlined
                    icon="pi pi-list"
                    @click="ingredientsOpen = true"
                />
                <Button :label="$t('category')" outlined icon="pi pi-plus" @click="editCategory()" /><Button
                    :label="$t('add_product')"
                    icon="pi pi-plus"
                    :disabled="!categories.length"
                    @click="edit(null)"
                /></div
        ></ZFPageHeader>
        <p v-if="!menuWritable" class="muted mb-5">{{ $t('permissions_read_only') }}</p>
        <p v-if="error" role="alert">{{ error }}</p>
        <ZFEmptyState
            v-if="!categories.length"
            :title="$t('your_menu_starts_here')"
            :description="$t('create_your_first_category_then_add_something_delicious')" />
        <section v-for="c in categories" :key="c.id" class="mb-8">
            <div class="mb-4 flex items-center gap-3">
                <h2 class="text-xl font-semibold">{{ c.name }}</h2>
                <span class="muted">{{ c.items.length }} {{ $t('products') }}</span
                ><Button
                    v-if="menuWritable"
                    icon="pi pi-pencil"
                    text
                    size="small"
                    :aria-label="$t('edit_category_named', { name: c.name })"
                    @click="editCategory(c)"
                />
            </div>
            <div class="grid gap-4 xl:grid-cols-3 md:grid-cols-2">
                <article v-for="p in c.items" :key="p.id" class="panel">
                    <img
                        v-if="p.image_path"
                        :src="'/storage/' + p.image_path"
                        :alt="p.name"
                        class="mb-4 h-36 w-full rounded-xl object-cover"
                    />
                    <div class="flex items-start justify-between gap-2">
                        <h3 class="font-semibold">{{ p.name }}</h3>
                        <span :class="p.is_available ? 'text-success' : 'text-slate-400'" class="text-xs">{{
                            p.is_available ? $t('available') : $t('paused')
                        }}</span>
                    </div>
                    <p class="muted my-3 line-clamp-2">{{ p.description || $t('no_description_yet') }}</p>
                    <div class="mt-4 flex items-center justify-between">
                        <strong>{{ money(p.price) }}</strong>
                        <div v-if="menuWritable">
                            <Button
                                icon="pi pi-pencil"
                                text
                                :aria-label="$t('edit_named', { name: p.name })"
                                @click="edit(p)"
                            /><Button
                                icon="pi pi-trash"
                                text
                                severity="danger"
                                :aria-label="$t('remove_named', { name: p.name })"
                                @click="remove(p)"
                            />
                        </div>
                    </div>
                </article>
            </div>
        </section>
        <section class="panel">
            <div class="mb-4 flex items-center justify-between">
                <h2 class="text-xl font-semibold">{{ $t('modifier_groups') }}</h2>
                <Button
                    v-if="modifierWritable"
                    :label="$t('add_group')"
                    text
                    icon="pi pi-plus"
                    @click="
                        group = null;
                        groupEditor = true;
                    "
                />
            </div>
            <div
                v-for="g in groups"
                :key="g.id"
                class="flex items-center justify-between border-t border-slate-100 py-3"
            >
                <div>
                    <strong>{{ g.name }}</strong>
                    <p class="muted">
                        {{ $t('select') }} {{ g.min_choices }}–{{ g.max_choices }} · {{ g.options.length }}
                        {{ $t('options') }}
                    </p>
                </div>
                <Button
                    v-if="modifierWritable"
                    icon="pi pi-pencil"
                    text
                    :aria-label="$t('edit_named', { name: g.name })"
                    @click="
                        group = g;
                        groupEditor = true;
                    "
                />
            </div>
            <p v-if="!groups.length" class="muted">{{ $t('create_extras_sides_and_customization_choices') }}</p>
        </section>
        <Dialog v-model:visible="ingredientsOpen" modal :header="$t('ingredients_title')" class="w-full max-w-3xl">
            <IngredientManager
                :ingredients="ingredients"
                :restaurant-id="location.restaurant_id"
                :base="`/app/locations/${location.public_id}/ingredients`"
            />
            <template #footer><Button :label="$t('modal_close')" outlined @click="ingredientsOpen = false" /></template>
        </Dialog>
        <MenuItemEditor
            v-model:visible="editor"
            :product="product"
            :location-id="location.public_id"
            :categories="categories"
            :groups="groups"
            :ingredients="ingredients"
            @saved="router.reload()" /><ModifierGroupEditor
            v-model:visible="groupEditor"
            :group="group"
            :location-id="location.public_id" /><Dialog
            v-model:visible="categoryEditor"
            :header="$t('menu_category')"
            modal
            ><form
                :id="categoryFormId"
                class="grid gap-4"
                @submit.prevent="
                    category.post('/app/locations/' + location.public_id + '/categories', {
                        onSuccess: () => (categoryEditor = false),
                    })
                "
            >
                <label class="field"
                    ><span class="field-label">{{ $t('menu') }}<FieldHelp :label="$t('menu')" field="menu" /></span
                    ><Select
                        v-model="category.menu_id"
                        :aria-label="$t('menu')"
                        :options="menus"
                        option-label="name"
                        option-value="id" /></label
                ><label class="field"
                    ><span class="field-label"
                        >{{ $t('category_name') }}<FieldHelp :label="$t('category_name')" field="category_name" /></span
                    ><InputText v-model="category.name" :aria-label="$t('category_name')" required
                /></label>
                <p v-for="(message, key) in category.errors" :key="key" role="alert" class="text-red-600">
                    {{ message }}
                </p>
            </form>
            <template #footer
                ><Button
                    :form="categoryFormId"
                    :disabled="!category.name.trim() || !category.menu_id || category.processing"
                    type="submit"
                    :label="$t('save_category')"
                    :loading="category.processing"
            /></template> </Dialog
        ><ZFConfirmDialog
    /></RestaurantLayout>
</template>
