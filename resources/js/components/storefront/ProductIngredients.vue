<script setup lang="ts">
import { ingredientName } from '@/lib/ingredients';
import type { Product } from '@/types';
defineProps<{ product: Product }>();
</script>
<template>
    <div class="my-3 space-y-2 text-sm">
        <p v-if="product.portion_size" class="font-medium text-slate-600 dark:text-slate-300">
            {{ product.portion_size }}
        </p>
        <p v-if="product.ingredients?.length" class="leading-relaxed text-slate-500">
            <span class="font-medium">{{ $t('ingredients_title') }}:</span>
            {{ product.ingredients.map(ingredientName).join(', ') }}
        </p>
    </div>
</template>
