<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import AdminLayout from '@/layouts/AdminLayout.vue';
import ZFPageHeader from '@/components/ui/ZFPageHeader.vue';
import IngredientManager from '@/components/menu/IngredientManager.vue';
import type { Ingredient } from '@/types';
defineProps<{ ingredients: Ingredient[] }>();
</script>
<template>
    <AdminLayout
        ><Head :title="$t('ingredients_title')" /><ZFPageHeader
            :title="$t('ingredients_title')"
            :description="$t('ingredients_shared')"
        />
        <section class="panel"><IngredientManager :ingredients="ingredients" base="/admin/ingredients" /></section>
    </AdminLayout>
</template>
