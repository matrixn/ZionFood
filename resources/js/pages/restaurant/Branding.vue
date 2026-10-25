<script setup lang="ts">
import { computed, ref, watch, onUnmounted } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import RestaurantLayout from '@/layouts/RestaurantLayout.vue';
import ZFPageHeader from '@/components/ui/ZFPageHeader.vue';
import InputText from 'primevue/inputtext';
import Textarea from 'primevue/textarea';
import Button from 'primevue/button';
import Checkbox from 'primevue/checkbox';
import type { WorkspaceProps } from '@/types';
const props = defineProps<WorkspaceProps>();
const brand = computed(() => props.location.restaurant);
const form = useForm({
    name: brand.value.name,
    slug: brand.value.slug,
    description: brand.value.description ?? '',
    tagline: brand.value.tagline ?? '',
    primary_color: brand.value.primary_color ?? '#635BFF',
    contact_email: brand.value.contact_email ?? '',
    contact_phone: brand.value.contact_phone ?? '',
    website: brand.value.website ?? '',
    logo: null as File | null,
    cover: null as File | null,
    remove_logo: false,
    remove_cover: false,
});
const logoPreview = ref('');
const coverPreview = ref('');
watch(
    () => form.logo,
    (file) => {
        if (logoPreview.value) URL.revokeObjectURL(logoPreview.value);
        logoPreview.value = file ? URL.createObjectURL(file) : '';
    },
);
watch(
    () => form.cover,
    (file) => {
        if (coverPreview.value) URL.revokeObjectURL(coverPreview.value);
        coverPreview.value = file ? URL.createObjectURL(file) : '';
    },
);
onUnmounted(() => {
    if (logoPreview.value) URL.revokeObjectURL(logoPreview.value);
    if (coverPreview.value) URL.revokeObjectURL(coverPreview.value);
});
const logo = computed(
    () =>
        logoPreview.value ||
        (!form.remove_logo && props.location.restaurant.logo_path
            ? '/storage/' + props.location.restaurant.logo_path
            : ''),
);
const cover = computed(
    () =>
        coverPreview.value ||
        (!form.remove_cover && props.location.restaurant.cover_path
            ? '/storage/' + props.location.restaurant.cover_path
            : ''),
);
const color = computed(() => (/^#[0-9a-f]{6}$/i.test(form.primary_color) ? form.primary_color : '#635BFF'));
function save() {
    form.post('/app/locations/' + props.location.public_id + '/branding', {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => {
            form.logo = null;
            form.cover = null;
            form.remove_logo = false;
            form.remove_cover = false;
        },
    });
}
</script>
<template>
    <RestaurantLayout v-bind="$props"
        ><Head :title="$t('restaurant_branding')" /><ZFPageHeader
            :title="$t('make_it_unmistakably_yours')"
            :description="$t('your_identity_images_and_public_contact_details_across_every_storefront')" />
        <div class="grid gap-6 xl:grid-cols-[1.2fr_1fr]">
            <form class="grid gap-6" @submit.prevent="save">
                <section class="panel grid gap-5">
                    <h2 class="text-xl font-semibold">{{ $t('restaurant_identity') }}</h2>
                    <label class="field"
                        >{{ $t('restaurant_name') }}<InputText v-model="form.name" required maxlength="150" /></label
                    ><label class="field"
                        >{{ $t('storefront_address_slug') }}<InputText v-model="form.slug" required maxlength="50"
                    /></label>
                    <p class="muted">
                        {{
                            $t(
                                'changing_the_address_updates_the_managed_zionfood_subdomain_custom_domains_remain_unchange',
                            )
                        }}
                    </p>
                    <label class="field">{{ $t('tagline') }}<InputText v-model="form.tagline" maxlength="180" /></label
                    ><label class="field"
                        >{{ $t('description') }}<Textarea v-model="form.description" rows="4" maxlength="1000"
                    /></label>
                    <div class="field">
                        <label for="brand-color">{{ $t('brand_color') }}</label>
                        <div class="flex items-center gap-3">
                            <input
                                id="brand-color"
                                v-model="form.primary_color"
                                type="color"
                                class="h-12 w-14 cursor-pointer rounded-lg border border-slate-200"
                            /><InputText v-model="form.primary_color" :aria-label="$t('hex_color')" maxlength="7" />
                        </div>
                    </div>
                </section>
                <section class="panel grid gap-5">
                    <h2 class="text-xl font-semibold">{{ $t('logo_and_cover') }}</h2>
                    <label class="field"
                        >{{ $t('restaurant_logo')
                        }}<input
                            type="file"
                            accept="image/jpeg,image/png,image/webp"
                            @change="form.logo = ($event.target as HTMLInputElement).files?.[0] ?? null" /></label
                    ><label v-if="brand.logo_path" class="flex items-center gap-3"
                        ><Checkbox v-model="form.remove_logo" binary />{{ $t('remove_existing_logo') }}</label
                    ><label class="field"
                        >{{ $t('cover_image')
                        }}<input
                            type="file"
                            accept="image/jpeg,image/png,image/webp"
                            @change="form.cover = ($event.target as HTMLInputElement).files?.[0] ?? null" /></label
                    ><label v-if="brand.cover_path" class="flex items-center gap-3"
                        ><Checkbox v-model="form.remove_cover" binary />{{ $t('remove_existing_cover') }}</label
                    >
                    <p class="muted">{{ $t('jpeg_png_or_webp_maximum_4_mb_per_image') }}</p>
                </section>
                <section class="panel grid gap-5">
                    <h2 class="text-xl font-semibold">{{ $t('public_contact_details') }}</h2>
                    <label class="field"
                        >{{ $t('contact_email') }}<InputText v-model="form.contact_email" type="email" /></label
                    ><label class="field"
                        >{{ $t('contact_phone')
                        }}<InputText v-model="form.contact_phone" type="tel" maxlength="30" /></label
                    ><label class="field"
                        >{{ $t('website_https')
                        }}<InputText v-model="form.website" type="url" placeholder="https://restaurant.ro"
                    /></label>
                </section>
                <div v-if="Object.keys(form.errors).length" role="alert" class="panel text-red-600">
                    <p v-for="(message, key) in form.errors" :key="key">{{ message }}</p>
                </div>
                <Button
                    type="submit"
                    :label="$t('save_branding')"
                    :loading="form.processing"
                    class="justify-self-start"
                />
            </form>
            <aside class="self-start xl:sticky xl:top-6">
                <div class="panel !overflow-hidden !p-0">
                    <div class="relative min-h-48 bg-ink p-7 text-white">
                        <img
                            v-if="cover"
                            :src="cover"
                            :alt="$t('cover_preview')"
                            class="absolute inset-0 h-full w-full object-cover opacity-40"
                        />
                        <div class="relative">
                            <img
                                v-if="logo"
                                :src="logo"
                                :alt="$t('logo_preview')"
                                class="mb-5 h-16 w-16 rounded-xl bg-white object-contain p-1"
                            /><span class="text-xs font-medium uppercase tracking-widest">{{
                                $t('storefront_preview')
                            }}</span>
                            <h2 class="my-3 text-3xl font-semibold">{{ form.name }}</h2>
                            <p>{{ form.tagline }}</p>
                        </div>
                    </div>
                    <div class="grid gap-4 p-6">
                        <p class="muted">{{ form.description }}</p>
                        <p class="muted">{{ form.contact_phone }}<br />{{ form.contact_email }}</p>
                        <div
                            class="rounded-xl px-5 py-3 text-center font-semibold"
                            :style="{ backgroundColor: color, color: '#fff' }"
                        >
                            {{ $t('preview_order_button') }}
                        </div>
                        <a :href="'/store/' + location.public_id" target="_blank" class="text-sm font-medium text-brand"
                            >{{ $t('open_storefront') }} <i aria-hidden="true" class="pi pi-arrow-up-right ml-1"
                        /></a>
                    </div>
                </div>
            </aside></div
    ></RestaurantLayout>
</template>
