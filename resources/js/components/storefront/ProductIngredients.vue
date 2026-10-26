<script setup lang="ts">
import { ingredientName } from '@/lib/ingredients';
import type { Product } from '@/types';
defineProps<{ product: Product; showPortion?: boolean }>();
</script>
<template>
    <div class="space-y-2">
        <p v-if="showPortion && product.portion_size" class="text-xs font-medium text-slate-500">
            {{ $t('product_serving') }} · {{ product.portion_size }}
        </p>
        <p
            v-if="product.ingredients?.length"
            :aria-label="$t('ingredients_title')"
            class="text-xs font-normal leading-relaxed text-slate-500 dark:text-slate-400"
        >
            ({{ product.ingredients.map(ingredientName).join(', ') }})
        </p>
    </div>
</template>
