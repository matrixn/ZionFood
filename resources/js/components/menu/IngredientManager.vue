<script setup lang="ts">
import { useId, computed, ref } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import { useConfirm } from 'primevue/useconfirm';
import Button from 'primevue/button';
import InputText from 'primevue/inputtext';
import Checkbox from 'primevue/checkbox';
import Dialog from 'primevue/dialog';
import ConfirmDialog from 'primevue/confirmdialog';
import { useSetupEditorGuard } from '@/lib/useSetupEditorGuard';
import { tr } from '@/i18n';
import { ingredientName } from '@/lib/ingredients';
import type { Ingredient } from '@/types';
const ingredientFormId = useId();
const props = defineProps<{ ingredients: Ingredient[]; base: string; restaurantId?: number | null }>();
const search = ref('');
const reviewOnly = ref(false);
const visible = ref(false);
const editing = ref<number | null>(null);
const confirm = useConfirm();
const normalize = (s: string) => s.normalize('NFD').replace(/\p{M}/gu, '').toLowerCase();
const rows = computed(() =>
    props.ingredients.filter(
        (i) =>
            !i.deleted_at &&
            (!reviewOnly.value || i.needs_review) &&
            normalize(i.name + ' ' + (i.name_en ?? '')).includes(normalize(search.value)),
    ),
);
const editable = (i: Ingredient) => (i.restaurant_id ?? null) === (props.restaurantId ?? null);
const form = useForm({
    name: '',
    name_en: '',
    contains_allergens: false,
    from_frozen: false,
    is_active: true,
    needs_review: false,
});
function edit(i?: Ingredient) {
    editing.value = i?.id ?? null;
    Object.assign(form, {
        name: i?.name ?? '',
        name_en: i?.name_en ?? '',
        contains_allergens: i?.contains_allergens ?? false,
        from_frozen: i?.from_frozen ?? false,
        is_active: i?.is_active ?? true,
        needs_review: i?.needs_review ?? false,
    });
    form.clearErrors();
    visible.value = true;
}
function save() {
    form.post(props.base + (editing.value ? '/' + editing.value : ''), {
        preserveScroll: true,
        onSuccess: () => {
            visible.value = false;
        },
    });
}
function remove(i: Ingredient) {
    confirm.require({
        group: props.base,
        header: tr('ingredients_remove'),
        message: tr('ingredients_remove_hint'),
        accept: () => router.delete(props.base + '/' + i.id, { preserveScroll: true }),
    });
}
useSetupEditorGuard(
    () => true,
    () => visible.value && form.isDirty,
);
</script>
<template>
    <div class="space-y-5" data-testid="ingredient-manager">
        <p class="text-sm leading-relaxed text-slate-500">{{ $t('ingredients_catalog_hint') }}</p>
        <div class="flex flex-wrap items-center gap-3">
            <span class="field-control-help"
                ><InputText
                    v-model="search"
                    :placeholder="$t('ingredients_search')"
                    :aria-label="$t('ingredients_search')"
                    class="min-w-0 flex-1" /><FieldHelp
                    :label="$t('ingredients_search')"
                    field="search"
                    kind="InputText"
            /></span>
            <Button :label="$t('ingredients_add')" icon="pi pi-plus" @click="edit()" />
        </div>
        <label class="flex items-center gap-2 text-sm"
            ><Checkbox v-model="reviewOnly" :aria-label="$t('ingredients_review_only')" binary /><span
                class="field-label"
                >{{ $t('ingredients_review_only')
                }}<FieldHelp :label="$t('ingredients_review_only')" field="ingredients_review_only" /></span
        ></label>
        <p role="status" class="text-sm text-slate-500">{{ $t('ingredients_count', { count: rows.length }) }}</p>
        <div class="grid max-h-[65vh] gap-3 overflow-y-auto pr-1" :aria-label="$t('ingredients_title')">
            <article
                v-for="i in rows"
                :key="i.id"
                class="rounded-xl border border-slate-200 p-4 dark:border-slate-700"
                :data-testid="'ingredient-' + i.id"
            >
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div class="min-w-0 space-y-1">
                        <h3 class="break-words font-semibold">{{ ingredientName(i) }}</h3>
                        <p class="text-xs text-slate-500">
                            {{ i.restaurant_id ? $t('ingredients_private') : $t('ingredients_shared') }}
                        </p>
                        <p v-if="!i.is_active" class="text-xs text-slate-500">{{ $t('ingredients_hidden') }}</p>
                        <p v-if="i.needs_review" class="text-xs text-amber-800">{{ $t('ingredients_review') }}</p>
                    </div>
                    <div v-if="editable(i)" class="flex flex-wrap gap-2">
                        <Button :label="$t('edit')" size="small" outlined @click="edit(i)" />
                        <Button
                            :label="$t('ingredients_remove')"
                            size="small"
                            text
                            severity="danger"
                            @click="remove(i)"
                        />
                    </div>
                </div>
            </article>
        </div>
        <Dialog v-model:visible="visible" modal :header="$t('ingredients_edit')" class="w-full max-w-lg">
            <form :id="ingredientFormId" class="grid min-w-0 gap-4" @submit.prevent="save">
                <label class="field"
                    ><span class="field-label"
                        >{{ $t('ingredients_name')
                        }}<FieldHelp :label="$t('ingredients_name')" field="ingredients_name" /></span
                    ><InputText v-model="form.name" :aria-label="$t('ingredients_name')" required maxlength="150"
                /></label>
                <label class="field"
                    ><span class="field-label"
                        >{{ $t('ingredients_name_en')
                        }}<FieldHelp :label="$t('ingredients_name_en')" field="ingredients_name_en" /></span
                    ><InputText v-model="form.name_en" :aria-label="$t('ingredients_name_en')" maxlength="150"
                /></label>
                <label class="flex items-center gap-3"
                    ><Checkbox
                        v-model="form.contains_allergens"
                        :aria-label="$t('ingredients_allergens')"
                        binary /><span class="field-label"
                        >{{ $t('ingredients_allergens')
                        }}<FieldHelp :label="$t('ingredients_allergens')" field="ingredients_allergens" /></span
                ></label>
                <label class="flex items-center gap-3"
                    ><Checkbox v-model="form.from_frozen" :aria-label="$t('ingredients_frozen')" binary /><span
                        class="field-label"
                        >{{ $t('ingredients_frozen')
                        }}<FieldHelp :label="$t('ingredients_frozen')" field="ingredients_frozen" /></span
                ></label>
                <label class="flex items-center gap-3"
                    ><Checkbox v-model="form.is_active" :aria-label="$t('ingredients_visible')" binary /><span
                        class="field-label"
                        >{{ $t('ingredients_visible')
                        }}<FieldHelp :label="$t('ingredients_visible')" field="ingredients_visible" /></span
                ></label>
                <label class="flex items-center gap-3"
                    ><Checkbox v-model="form.needs_review" :aria-label="$t('ingredients_review')" binary /><span
                        class="field-label"
                        >{{ $t('ingredients_review')
                        }}<FieldHelp :label="$t('ingredients_review')" field="ingredients_review" /></span
                ></label>
                <p v-for="(message, key) in form.errors" :key="key" role="alert" class="text-sm text-red-700">
                    {{ message }}
                </p>
            </form>
            <template #footer
                ><Button
                    :form="ingredientFormId"
                    :disabled="!form.name.trim() || form.processing"
                    type="submit"
                    :label="$t('ingredients_save')"
                    :loading="form.processing"
            /></template>
        </Dialog>
        <ConfirmDialog :group="base" />
    </div>
</template>

<style scoped>
.field {
    min-width: 0;
}
</style>
