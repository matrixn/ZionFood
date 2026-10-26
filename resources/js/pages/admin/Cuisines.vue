<script setup lang="ts">
import { computed, onBeforeUnmount, ref } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/layouts/AdminLayout.vue';
import ZFPageHeader from '@/components/ui/ZFPageHeader.vue';
import CuisineCard from '@/components/restaurant/CuisineCard.vue';
import Button from 'primevue/button';
import InputText from 'primevue/inputtext';
import InputNumber from 'primevue/inputnumber';
import Textarea from 'primevue/textarea';
import ToggleSwitch from 'primevue/toggleswitch';
import type { Cuisine } from '@/types/cuisine';
const props = defineProps<{ cuisines: Cuisine[] }>();
const editing = ref<number | null>(null);
const preview = ref('');
const search = ref('');
const input = ref<HTMLInputElement | null>(null);
let objectUrl = '';
const form = useForm({
    slug: '',
    name_ro: '',
    name_en: '',
    description_ro: '',
    description_en: '',
    is_active: true,
    sort_order: 0,
    image: null as File | null,
    image_credit: '',
    image_source_url: '',
    image_license: '',
    image_license_url: '',
});
const filtered = computed(() =>
    props.cuisines.filter((c) => `${c.name_ro} ${c.name_en}`.toLowerCase().includes(search.value.toLowerCase())),
);
function cleanup() {
    if (objectUrl) URL.revokeObjectURL(objectUrl);
    objectUrl = '';
}
function edit(c?: Cuisine) {
    cleanup();
    form.reset();
    form.clearErrors();
    editing.value = c?.id ?? null;
    preview.value = c?.image_path ?? '';
    if (input.value) input.value.value = '';
    if (c)
        Object.assign(form, {
            slug: c.slug,
            name_ro: c.name_ro,
            name_en: c.name_en,
            description_ro: c.description_ro,
            description_en: c.description_en,
            is_active: c.is_active,
            sort_order: c.sort_order,
            image_credit: c.image_credit ?? '',
            image_source_url: c.image_source_url ?? '',
            image_license: c.image_license ?? '',
            image_license_url: c.image_license_url ?? '',
        });
    document.getElementById('cuisine-editor')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
}
function photo(event: Event) {
    cleanup();
    form.image = (event.target as HTMLInputElement).files?.[0] ?? null;
    preview.value = form.image
        ? (objectUrl = URL.createObjectURL(form.image))
        : (props.cuisines.find((c) => c.id === editing.value)?.image_path ?? '');
    if (form.image) {
        form.image_credit = '';
        form.image_source_url = '';
        form.image_license = '';
        form.image_license_url = '';
    }
}
function save() {
    form.post(editing.value ? `/admin/cuisines/${editing.value}` : '/admin/cuisines', {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => edit(),
    });
}
onBeforeUnmount(cleanup);
</script>
<template>
    <AdminLayout
        ><Head :title="$t('cuisine_admin_title')" /><ZFPageHeader
            :title="$t('cuisine_admin_title')"
            :description="$t('cuisine_admin_hint')"
        />
        <form id="cuisine-editor" class="panel mb-8 scroll-mt-5 space-y-5" @submit.prevent="save">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <h2 class="text-xl font-semibold">{{ editing ? $t('cuisine_edit') : $t('cuisine_create') }}</h2>
                <Button v-if="editing" :label="$t('cuisine_new')" text @click="edit()" />
            </div>
            <div v-if="Object.keys(form.errors).length" role="alert" class="rounded-xl bg-red-50 p-4 text-red-700">
                <p v-for="(message, key) in form.errors" :key="key">{{ message }}</p>
            </div>
            <div class="grid gap-5 lg:grid-cols-2">
                <label class="field"
                    >{{ $t('cuisine_name_ro') }}<InputText v-model="form.name_ro" required maxlength="100"
                /></label>
                <label class="field"
                    >{{ $t('cuisine_name_en') }}<InputText v-model="form.name_en" required maxlength="100"
                /></label>
                <label class="field"
                    >{{ $t('cuisine_description_ro')
                    }}<Textarea v-model="form.description_ro" required rows="3" maxlength="1000"
                /></label>
                <label class="field"
                    >{{ $t('cuisine_description_en')
                    }}<Textarea v-model="form.description_en" required rows="3" maxlength="1000"
                /></label>
                <label class="field"
                    >{{ $t('cuisine_slug')
                    }}<InputText v-model="form.slug" required maxlength="100" pattern="[a-z0-9]+(-[a-z0-9]+)*"
                /></label>
                <label class="field"
                    >{{ $t('cuisine_sort_order') }}<InputNumber v-model="form.sort_order" fluid :min="0" :max="65535"
                /></label>
                <label class="field"
                    >{{ $t('cuisine_photo')
                    }}<input
                        ref="input"
                        type="file"
                        accept="image/jpeg,image/png,image/webp"
                        :required="!editing"
                        @change="photo"
                    /><span class="muted text-xs">{{ $t('cuisine_photo_hint') }}</span></label
                >
                <img
                    v-if="preview"
                    :src="preview"
                    :alt="$t('cuisine_photo_preview')"
                    class="h-40 w-full rounded-xl object-cover"
                />
            </div>
            <details class="rounded-xl border border-slate-200 p-4">
                <summary class="cursor-pointer font-medium">{{ $t('cuisine_photo_credit') }}</summary>
                <div class="mt-4 grid gap-4 sm:grid-cols-2">
                    <label class="field"
                        >{{ $t('cuisine_credit_author') }}<InputText v-model="form.image_credit" maxlength="500"
                    /></label>
                    <label class="field"
                        >{{ $t('cuisine_photo_source')
                        }}<InputText v-model="form.image_source_url" type="url" maxlength="1000"
                    /></label>
                    <label class="field"
                        >{{ $t('cuisine_photo_license') }}<InputText v-model="form.image_license" maxlength="150"
                    /></label>
                    <label class="field"
                        >{{ $t('cuisine_license_url')
                        }}<InputText v-model="form.image_license_url" type="url" maxlength="1000"
                    /></label>
                </div>
            </details>
            <label class="flex items-center gap-3"
                ><ToggleSwitch v-model="form.is_active" :aria-label="$t('cuisine_catalog_active')" />{{
                    $t('cuisine_catalog_active')
                }}</label
            >
            <p class="muted text-sm">{{ $t('cuisine_deactivation_hint') }}</p>
            <Button type="submit" :label="$t('cuisine_save')" :loading="form.processing" />
        </form>
        <label class="field mb-5">{{ $t('cuisine_search') }}<InputText v-model="search" /></label>
        <div class="grid gap-5 sm:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-4">
            <CuisineCard v-for="c in filtered" :key="c.id" :cuisine="c"
                ><Button :label="$t('cuisine_edit')" text icon="pi pi-pencil" @click="edit(c)"
            /></CuisineCard>
        </div>
        <p v-if="!filtered.length" class="muted py-8 text-center">{{ $t('cuisine_no_results') }}</p>
    </AdminLayout>
</template>
