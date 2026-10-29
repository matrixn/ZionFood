<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import LanguageSelector from '@/components/ui/LanguageSelector.vue';
import ZFLogo from '@/components/ui/ZFLogo.vue';
import { useStorefrontBrand } from '@/lib/brand';
import type { RestaurantBrand } from '@/types';
const props = defineProps<{ name?: string; brand?: RestaurantBrand }>();
useStorefrontBrand(() => props.brand);
</script>
<template>
    <div class="min-h-screen">
        <header class="border-b border-slate-200 bg-white px-4 py-5 dark:bg-slate-900">
            <div class="mx-auto flex max-w-6xl flex-wrap items-center justify-between gap-4">
                <div class="flex items-center gap-3">
                    <img
                        v-if="brand?.logo_path"
                        :src="'/storage/' + brand.logo_path"
                        :alt="brand.name"
                        class="h-12 w-12 rounded-xl bg-white object-contain p-1"
                    /><span class="text-xl font-bold">{{ name ?? $t('your_order') }}</span>
                </div>
                <div class="ml-auto flex items-center gap-3">
                    <slot name="actions" /><slot name="language"><LanguageSelector /></slot>
                </div>
            </div>
        </header>
        <main class="mx-auto max-w-6xl p-5 md:p-8"><slot /></main>
        <footer class="mt-12 border-t border-slate-200 p-6 text-center">
            <div v-if="brand" class="mb-4 flex flex-wrap justify-center gap-5 text-sm">
                <a v-if="brand.contact_phone" :href="'tel:' + brand.contact_phone" class="text-brand">{{
                    brand.contact_phone
                }}</a
                ><a v-if="brand.contact_email" :href="'mailto:' + brand.contact_email" class="text-brand">{{
                    brand.contact_email
                }}</a
                ><a
                    v-if="brand.website"
                    :href="brand.website"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="text-brand"
                    >{{ $t('website') }}</a
                >
            </div>
            <p class="text-sm text-slate-500">
                {{ $t('ordering_powered_by') }}
                <Link
                    href="/"
                    class="inline-flex items-center gap-1 align-middle font-semibold text-brand"
                    aria-label="ZionFood"
                    ><ZFLogo :size="24" />ZionFood</Link
                >
            </p>
        </footer>
    </div>
</template>
