<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import StorefrontLayout from '@/layouts/StorefrontLayout.vue';
import type { RestaurantBrand } from '@/types';
defineProps<{ restaurant: RestaurantBrand; reason: 'service_disabled' | 'table_closed'; menuUrl: string }>();
</script>
<template>
    <StorefrontLayout :name="restaurant.name" :brand="restaurant">
        <Head :title="$t('dining_unavailable_title')"><meta name="robots" content="noindex" /></Head>
        <section class="panel mx-auto my-10 max-w-xl space-y-5 text-center">
            <i class="pi pi-info-circle text-4xl text-brand" aria-hidden="true" />
            <h1 class="text-2xl font-semibold">{{ $t('dining_unavailable_title') }}</h1>
            <p class="leading-relaxed text-slate-600">{{ $t('dining_reason_' + reason) }}</p>
            <p class="muted">{{ $t('dining_ask_team') }}</p>
            <Link :href="menuUrl" class="inline-block rounded-xl bg-brand px-5 py-3 font-semibold text-white">{{
                $t('store_view_menu')
            }}</Link>
        </section>
    </StorefrontLayout>
</template>
