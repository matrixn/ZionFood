<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import RestaurantLayout from '@/layouts/RestaurantLayout.vue';
import ZFPageHeader from '@/components/ui/ZFPageHeader.vue';
import type { WorkspaceProps } from '@/types';
defineProps<WorkspaceProps & { manageableLocationIds: string[] }>();
</script>
<template>
    <RestaurantLayout v-bind="$props">
        <Head v-if="!embedded" :title="$t('nav_locations')" />
        <ZFPageHeader :title="$t('nav_locations')" :description="$t('locations_intro')" />
        <div class="grid gap-4 lg:grid-cols-2">
            <article
                v-for="place in locations"
                :key="place.public_id"
                class="panel min-w-0 space-y-3"
                :class="place.public_id === location.public_id ? 'ring-2 ring-brand/30' : ''"
            >
                <p class="text-xs font-medium text-slate-500">{{ place.restaurant.name }}</p>
                <h3 class="text-lg font-semibold">{{ place.name }}</h3>
                <p class="break-words text-sm text-slate-500">{{ place.address }}</p>
                <p v-if="place.public_id === location.public_id" class="text-xs font-semibold text-brand">
                    {{ $t('locations_current') }}
                </p>
                <Link
                    v-if="manageableLocationIds.includes(place.public_id)"
                    :href="`/app/locations/${place.public_id}/setup/basics`"
                    class="inline-flex items-center gap-2 rounded-lg bg-brand px-4 py-2 text-sm font-semibold text-white"
                >
                    <i class="pi pi-sliders-h" aria-hidden="true" />{{ $t('locations_configure') }}
                </Link>
            </article>
        </div>
    </RestaurantLayout>
</template>
