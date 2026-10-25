<script setup lang="ts">
import { computed, ref } from 'vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import RestaurantLayout from '@/layouts/RestaurantLayout.vue';
import ZFPageHeader from '@/components/ui/ZFPageHeader.vue';
import ZFEmptyState from '@/components/ui/ZFEmptyState.vue';
import MenuItemEditor from '@/components/menu/MenuItemEditor.vue';
import ModifierGroupEditor from '@/components/menu/ModifierGroupEditor.vue';
import Button from 'primevue/button';
import InputText from 'primevue/inputtext';
import Select from 'primevue/select';
import Dialog from 'primevue/dialog';
import { api, money } from '@/lib/http';
import { useConfirm } from 'primevue/useconfirm';
import ZFConfirmDialog from '@/components/ui/ZFConfirmDialog.vue';
import type { WorkspaceProps, Menu, Product, ModifierGroup, Category } from '@/types';
const props = defineProps<WorkspaceProps & { menus: Menu[]; groups: ModifierGroup[] }>();
const categories = computed(() => props.menus.flatMap((m) => m.categories));
const editor = ref(false);
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
        message: `Remove ${p.name} from the menu? Historical orders will retain their snapshots.`,
        header: 'Remove product',
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
</script>
<template>
    <RestaurantLayout v-bind="$props"
        ><Head title="Menu studio" /><ZFPageHeader
            title="Made to be ordered."
            description="Build a menu that feels like your restaurant."
            ><div class="flex gap-2">
                <Button label="Category" outlined icon="pi pi-plus" @click="editCategory()" /><Button
                    label="Add product"
                    icon="pi pi-plus"
                    :disabled="!categories.length"
                    @click="edit(null)"
                /></div
        ></ZFPageHeader>
        <p v-if="error" role="alert">{{ error }}</p>
        <ZFEmptyState
            v-if="!categories.length"
            title="Your menu starts here"
            description="Create your first category, then add something delicious." />
        <section v-for="c in categories" :key="c.id" class="mb-8">
            <div class="mb-4 flex items-center gap-3">
                <h2 class="text-xl font-semibold">{{ c.name }}</h2>
                <span class="muted">{{ c.items.length }} products</span
                ><Button
                    icon="pi pi-pencil"
                    text
                    size="small"
                    :aria-label="'Edit category ' + c.name"
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
                            p.is_available ? 'Available' : 'Paused'
                        }}</span>
                    </div>
                    <p class="muted my-3 line-clamp-2">{{ p.description || 'No description yet.' }}</p>
                    <div class="mt-4 flex items-center justify-between">
                        <strong>{{ money(p.price) }}</strong>
                        <div>
                            <Button icon="pi pi-pencil" text :aria-label="'Edit ' + p.name" @click="edit(p)" /><Button
                                icon="pi pi-trash"
                                text
                                severity="danger"
                                :aria-label="'Remove ' + p.name"
                                @click="remove(p)"
                            />
                        </div>
                    </div>
                </article>
            </div>
        </section>
        <section class="panel">
            <div class="mb-4 flex items-center justify-between">
                <h2 class="text-xl font-semibold">Modifier groups</h2>
                <Button
                    label="Add group"
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
                    <p class="muted">Select {{ g.min_choices }}–{{ g.max_choices }} · {{ g.options.length }} options</p>
                </div>
                <Button
                    icon="pi pi-pencil"
                    text
                    :aria-label="'Edit ' + g.name"
                    @click="
                        group = g;
                        groupEditor = true;
                    "
                />
            </div>
            <p v-if="!groups.length" class="muted">Create extras, sides and customization choices.</p>
        </section>
        <MenuItemEditor
            v-model:visible="editor"
            :product="product"
            :location-id="location.public_id"
            :categories="categories"
            :groups="groups"
            @saved="router.reload()" /><ModifierGroupEditor
            v-model:visible="groupEditor"
            :group="group"
            :location-id="location.public_id" /><Dialog v-model:visible="categoryEditor" header="Menu category" modal
            ><form
                class="grid gap-4"
                @submit.prevent="
                    category.post('/app/locations/' + location.public_id + '/categories', {
                        onSuccess: () => (categoryEditor = false),
                    })
                "
            >
                <label class="field"
                    >Menu<Select
                        v-model="category.menu_id"
                        :options="menus"
                        option-label="name"
                        option-value="id" /></label
                ><label class="field">Category name<InputText v-model="category.name" required /></label>
                <p v-for="(message, key) in category.errors" :key="key" role="alert" class="text-red-600">
                    {{ message }}
                </p>
                <Button type="submit" label="Save category" :loading="category.processing" /></form></Dialog
        ><ZFConfirmDialog
    /></RestaurantLayout>
</template>
