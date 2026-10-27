<script setup lang="ts">
import type { Product } from '@/types';
import { computed, inject, type Ref } from 'vue';
import { i18n, tr } from '@/i18n';
import { foodLabel, allergenCodes } from '@/lib/foodLabels';
const language = inject<Ref<string>>(
    'menu-content-language',
    computed(() => i18n.global.locale.value),
);
const label = (key: string) => foodLabel(language.value, key, tr);
const allergenName = (code: string) => label('allergen_group_' + (allergenCodes.indexOf(code) + 1));
defineProps<{ product: Product; compact?: boolean }>();
</script>
<template>
    <div v-if="product.content" class="space-y-3 text-xs leading-relaxed text-slate-500 dark:text-slate-400">
        <p v-if="product.content.allergens.length">
            <strong class="font-medium">{{ label('mc_allergens') }}:</strong>
            {{ product.content.allergens.map(allergenName).join(', ') }}
        </p>
        <p v-if="product.content.may_contain.length">
            <strong class="font-medium">{{ label('mc_may_contain') }}:</strong>
            {{ product.content.may_contain.map(allergenName).join(', ') }}
        </p>
        <div v-if="product.content.labels.length" class="flex flex-wrap gap-2">
            <span
                v-for="code in product.content.labels"
                :key="code"
                class="rounded-full bg-emerald-50 px-2 py-1 text-emerald-800"
                >{{ label('mc_label_' + code) }}</span
            >
        </div>
        <details v-if="product.content.nutrition && !compact" class="rounded-xl border border-slate-200 p-3">
            <summary class="cursor-pointer font-medium">
                {{ label('mc_nutrition') }} · {{ label('mc_' + product.content.nutrition.basis) }}
            </summary>
            <p v-if="product.content.nutrition.estimated" class="mt-2 text-xs text-amber-700">
                {{ label('auto_estimated_short') }}
            </p>
            <p v-if="product.content.nutrition.portion_grams" class="mt-2 text-xs">
                {{ label('mc_per_serving') }}: {{ product.content.nutrition.portion_grams }} g
            </p>
            <dl class="mt-3 grid grid-cols-2 gap-x-4 gap-y-2">
                <template v-for="(value, key) in product.content.nutrition.values" :key="key"
                    ><dt>{{ label('mc_nutrient_' + key) }}</dt>
                    <dd class="text-right tabular-nums">
                        {{ value === null ? '—' : new Intl.NumberFormat(language).format(value) }}
                    </dd></template
                >
            </dl>
        </details>
    </div>
</template>
