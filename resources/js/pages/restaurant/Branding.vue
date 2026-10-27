<script setup lang="ts">
import { useSetupEditorGuard } from '@/lib/useSetupEditorGuard';
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
            form.defaults();
        },
    });
}
useSetupEditorGuard(
    () => !!props.embedded,
    () => form.isDirty,
);
</script>
<template>
    <RestaurantLayout v-bind="$props"
        ><Head v-if="!embedded" :title="$t('restaurant_branding')" /><ZFPageHeader
            :title="$t('make_it_unmistakably_yours')"
            :description="$t('your_identity_images_and_public_contact_details_across_every_storefront')" />
        <div class="grid min-w-0 gap-6" :class="embedded ? 'grid-cols-1' : 'xl:grid-cols-[1.2fr_1fr]'">
            <form class="grid gap-6" @submit.prevent="save">
                <section class="panel grid gap-5">
                    <h2 class="text-xl font-semibold">{{ $t('restaurant_identity') }}</h2>
                    <label class="field"
                        ><span class="field-label"
                            >{{ $t('restaurant_name')
                            }}<FieldHelp :label="$t('restaurant_name')" field="restaurant_name" /></span
                        ><InputText
                            v-model="form.name"
                            :aria-label="$t('restaurant_name')"
                            required
                            maxlength="150" /></label
                    ><label class="field"
                        ><span class="field-label"
                            >{{ $t('storefront_address_slug')
                            }}<FieldHelp :label="$t('storefront_address_slug')" field="storefront_address_slug" /></span
                        ><InputText
                            v-model="form.slug"
                            :aria-label="$t('storefront_address_slug')"
                            required
                            maxlength="50"
                    /></label>
                    <p class="muted">
                        {{
                            $t(
                                'changing_the_address_updates_the_managed_zionfood_subdomain_custom_domains_remain_unchange',
                            )
                        }}
                    </p>
                    <label class="field"
                        ><span class="field-label"
                            >{{ $t('tagline') }}<FieldHelp :label="$t('tagline')" field="tagline" /></span
                        ><InputText v-model="form.tagline" :aria-label="$t('tagline')" maxlength="180" /></label
                    ><label class="field"
                        ><span class="field-label"
                            >{{ $t('description') }}<FieldHelp :label="$t('description')" field="description" /></span
                        ><Textarea v-model="form.description" :aria-label="$t('description')" rows="4" maxlength="1000"
                    /></label>
                    <div class="field">
                        <label for="brand-color"
                            ><span class="field-label"
                                >{{ $t('brand_color')
                                }}<FieldHelp :label="$t('brand_color')" field="brand_color" /></span
                        ></label>
                        <div class="flex items-center gap-3">
                            <input
                                id="brand-color"
                                v-model="form.primary_color"
                                type="color"
                                class="h-12 w-14 cursor-pointer rounded-lg border border-slate-200"
                            /><span class="field-control-help"
                                ><InputText
                                    v-model="form.primary_color"
                                    :aria-label="$t('hex_color')"
                                    maxlength="7" /><FieldHelp
                                    :label="$t('hex_color')"
                                    field="primary_color"
                                    kind="InputText"
                            /></span>
                        </div>
                    </div>
                </section>
                <section class="panel grid gap-5">
                    <h2 class="text-xl font-semibold">{{ $t('logo_and_cover') }}</h2>
                    <label class="field"
                        ><span class="field-label"
                            >{{ $t('restaurant_logo')
                            }}<FieldHelp :label="$t('restaurant_logo')" field="restaurant_logo" /></span
                        ><input
                            :aria-label="$t('restaurant_logo')"
                            type="file"
                            accept="image/jpeg,image/png,image/webp"
                            @change="form.logo = ($event.target as HTMLInputElement).files?.[0] ?? null" /></label
                    ><label v-if="brand.logo_path" class="flex items-center gap-3"
                        ><Checkbox v-model="form.remove_logo" :aria-label="$t('remove_existing_logo')" binary /><span
                            class="field-label"
                            >{{ $t('remove_existing_logo')
                            }}<FieldHelp
                                :label="$t('remove_existing_logo')"
                                field="remove_existing_logo" /></span></label
                    ><label class="field"
                        ><span class="field-label"
                            >{{ $t('cover_image') }}<FieldHelp :label="$t('cover_image')" field="cover_image" /></span
                        ><input
                            :aria-label="$t('cover_image')"
                            type="file"
                            accept="image/jpeg,image/png,image/webp"
                            @change="form.cover = ($event.target as HTMLInputElement).files?.[0] ?? null" /></label
                    ><label v-if="brand.cover_path" class="flex items-center gap-3"
                        ><Checkbox v-model="form.remove_cover" :aria-label="$t('remove_existing_cover')" binary /><span
                            class="field-label"
                            >{{ $t('remove_existing_cover')
                            }}<FieldHelp :label="$t('remove_existing_cover')" field="remove_existing_cover" /></span
                    ></label>
                    <p class="muted">{{ $t('jpeg_png_or_webp_maximum_4_mb_per_image') }}</p>
                </section>
                <section class="panel grid gap-5">
                    <h2 class="text-xl font-semibold">{{ $t('public_contact_details') }}</h2>
                    <label class="field"
                        ><span class="field-label"
                            >{{ $t('contact_email')
                            }}<FieldHelp :label="$t('contact_email')" field="contact_email" /></span
                        ><InputText
                            v-model="form.contact_email"
                            :aria-label="$t('contact_email')"
                            type="email" /></label
                    ><label class="field"
                        ><span class="field-label"
                            >{{ $t('contact_phone')
                            }}<FieldHelp :label="$t('contact_phone')" field="contact_phone" /></span
                        ><InputText
                            v-model="form.contact_phone"
                            :aria-label="$t('contact_phone')"
                            type="tel"
                            maxlength="30" /></label
                    ><label class="field"
                        ><span class="field-label"
                            >{{ $t('website_https')
                            }}<FieldHelp :label="$t('website_https')" field="website_https" /></span
                        ><InputText
                            v-model="form.website"
                            :aria-label="$t('website_https')"
                            type="url"
                            placeholder="https://restaurant.ro"
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
                        <a :href="location.storefront_path" target="_blank" class="text-sm font-medium text-brand"
                            >{{ $t('open_storefront') }} <i aria-hidden="true" class="pi pi-arrow-up-right ml-1"
                        /></a>
                    </div>
                </div>
            </aside></div
    ></RestaurantLayout>
</template>
