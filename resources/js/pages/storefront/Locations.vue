<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import StorefrontLayout from '@/layouts/StorefrontLayout.vue';
import type { RestaurantBrand } from '@/types';
defineProps<{ restaurant: RestaurantBrand; locations: { name: string; address: string; storefront_path: string }[] }>();
</script>
<template>
    <StorefrontLayout :name="restaurant.name" :brand="restaurant">
        <Head :title="restaurant.name" />
        <section class="mx-auto max-w-3xl py-8">
            <h1 class="text-3xl font-semibold">{{ $t('store_choose_location') }}</h1>
            <p class="muted mt-3">{{ $t('store_choose_location_hint') }}</p>
            <div class="mt-8 grid gap-4 sm:grid-cols-2">
                <Link
                    v-for="place in locations"
                    :key="place.storefront_path"
                    :href="place.storefront_path"
                    class="panel block transition hover:border-violet-300"
                >
                    <h2 class="text-lg font-semibold">{{ place.name }}</h2>
                    <p class="muted mt-2">{{ place.address }}</p>
                    <span class="mt-5 inline-block font-medium text-brand">{{ $t('store_view_menu') }} →</span>
                </Link>
            </div>
        </section>
    </StorefrontLayout>
</template>
