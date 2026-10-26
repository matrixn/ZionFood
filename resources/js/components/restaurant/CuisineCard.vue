<script setup lang="ts">
import { computed } from 'vue';
import { i18n, localeTag } from '@/i18n';
import type { Cuisine } from '@/types/cuisine';
const props = defineProps<{ cuisine: Cuisine; selected?: boolean; selectedAt?: string | null; selectable?: boolean }>();
defineEmits<{ toggle: [] }>();
const name = computed(() => (i18n.global.locale.value === 'ro' ? props.cuisine.name_ro : props.cuisine.name_en));
const description = computed(() =>
    i18n.global.locale.value === 'ro' ? props.cuisine.description_ro : props.cuisine.description_en,
);
</script>
<template>
    <article
        :class="[
            'overflow-hidden rounded-2xl border bg-white shadow-sm transition dark:bg-slate-900',
            selected ? 'border-brand ring-2 ring-brand/20' : 'border-slate-200 dark:border-slate-700',
        ]"
        :data-testid="`cuisine-card-${cuisine.slug}`"
    >
        <button
            v-if="selectable"
            type="button"
            class="group block w-full text-left disabled:opacity-60"
            :aria-pressed="!!selected"
            :aria-label="name"
            :disabled="!cuisine.is_active && !selected"
            :data-testid="`cuisine-choice-${cuisine.slug}`"
            @click="$emit('toggle')"
        >
            <div class="relative aspect-[16/9] overflow-hidden bg-slate-100">
                <img
                    :src="cuisine.image_path"
                    :alt="name"
                    loading="lazy"
                    class="h-full w-full object-cover transition group-hover:scale-105"
                />
                <span
                    :class="[
                        'absolute right-3 top-3 grid h-8 w-8 place-items-center rounded-full shadow',
                        selected ? 'bg-brand text-white' : 'bg-white text-slate-400',
                    ]"
                    ><i :class="selected ? 'pi pi-check' : 'pi pi-plus'" aria-hidden="true"
                /></span>
            </div>
            <div class="space-y-2 p-4">
                <h3 class="text-lg font-semibold">{{ name }}</h3>
                <p class="min-h-14 text-sm leading-relaxed text-slate-500">{{ description }}</p>
                <p v-if="!cuisine.is_active" class="text-xs font-semibold text-amber-700">
                    {{ $t('cuisine_unavailable') }}
                </p>
                <p v-else-if="selected" class="text-xs font-semibold text-brand">
                    {{
                        selectedAt
                            ? $t('cuisine_active_since', { date: new Date(selectedAt).toLocaleDateString(localeTag()) })
                            : $t('cuisine_pending')
                    }}
                </p>
                <p v-else class="text-xs text-slate-400">{{ $t('cuisine_add') }}</p>
            </div>
        </button>
        <template v-else>
            <img :src="cuisine.image_path" :alt="name" loading="lazy" class="aspect-[16/9] w-full object-cover" />
            <div class="space-y-2 p-4">
                <h3 class="text-lg font-semibold">{{ name }}</h3>
                <p class="text-sm text-slate-500">{{ description }}</p>
                <p class="text-xs font-semibold" :class="cuisine.is_active ? 'text-emerald-700' : 'text-amber-700'">
                    {{ cuisine.is_active ? $t('cuisine_catalog_active') : $t('cuisine_unavailable') }}
                </p>
                <slot />
            </div>
        </template>
        <details
            v-if="cuisine.image_credit || cuisine.image_source_url"
            class="border-t border-slate-100 px-4 py-2 text-xs text-slate-400"
        >
            <summary class="cursor-pointer">{{ $t('cuisine_photo_credit') }}</summary>
            <p class="mt-2 break-words">{{ cuisine.image_credit }}</p>
            <a
                v-if="cuisine.image_source_url"
                :href="cuisine.image_source_url"
                target="_blank"
                rel="noreferrer"
                class="mr-3 underline"
                >{{ $t('cuisine_photo_source') }}</a
            >
            <a
                v-if="cuisine.image_license_url"
                :href="cuisine.image_license_url"
                target="_blank"
                rel="noreferrer"
                class="underline"
                >{{ cuisine.image_license || $t('cuisine_photo_license') }}</a
            >
        </details>
    </article>
</template>
