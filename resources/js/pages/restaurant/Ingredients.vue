<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import RestaurantLayout from '@/layouts/RestaurantLayout.vue';
import ZFPageHeader from '@/components/ui/ZFPageHeader.vue';
import IngredientManager from '@/components/menu/IngredientManager.vue';
import type { Ingredient, WorkspaceProps } from '@/types';
defineProps<WorkspaceProps & { ingredients: Ingredient[] }>();
</script>
<template>
    <RestaurantLayout v-bind="$props"
        ><Head :title="$t('ingredients_title')" /><ZFPageHeader
            :title="$t('ingredients_title')"
            :description="$t('ingredients_private')"
        />
        <section class="panel">
            <IngredientManager
                :ingredients="ingredients"
                :restaurant-id="location.restaurant_id"
                :base="`/app/locations/${location.public_id}/ingredients`"
            />
        </section>
    </RestaurantLayout>
</template>
