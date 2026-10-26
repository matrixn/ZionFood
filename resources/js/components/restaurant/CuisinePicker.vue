<script setup lang="ts">
import { computed, ref } from 'vue';
import { i18n, localeTag } from '@/i18n';
import InputText from 'primevue/inputtext';
import Button from 'primevue/button';
import CuisineCard from './CuisineCard.vue';
import type { Cuisine, CuisineSelection } from '@/types/cuisine';
const props = defineProps<{ cuisines: Cuisine[]; saved: CuisineSelection[]; saving?: boolean }>();
defineEmits<{ save: [] }>();
const ids = defineModel<number[]>({ required: true });
const search = ref('');
const normalize = (value: string) =>
    value
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .toLowerCase();
const filtered = computed(() =>
    props.cuisines.filter((c) => normalize(`${c.name_ro} ${c.name_en}`).includes(normalize(search.value))),
);
const selected = computed(() =>
    ids.value.map((id) => props.cuisines.find((c) => c.id === id)).filter((c): c is Cuisine => !!c),
);
const since = (id: number) => props.saved.find((c) => c.id === id)?.selected_at;
function toggle(id: number) {
    ids.value = ids.value.includes(id) ? ids.value.filter((item) => item !== id) : [...ids.value, id];
}
</script>
<template>
    <div class="space-y-6">
        <div
            class="sticky top-3 z-20 flex flex-wrap items-center justify-between gap-3 rounded-xl border border-violet-200 bg-white/95 p-3 shadow-sm backdrop-blur dark:border-violet-800 dark:bg-slate-900/95"
        >
            <span class="text-sm font-semibold text-brand" aria-live="polite">{{
                $t('cuisine_selected_count', { count: selected.length })
            }}</span>
            <a href="#selected-cuisines" class="text-sm font-medium text-brand underline">{{
                $t('cuisine_view_selected')
            }}</a>
            <Button :label="$t('setup_save')" :loading="saving" class="w-full sm:w-auto" @click="$emit('save')" />
        </div>
        <p class="muted">{{ $t('cuisine_picker_hint') }}</p>
        <section
            id="selected-cuisines"
            class="scroll-mt-24 rounded-2xl border border-violet-200 bg-violet-50/60 p-4 dark:border-violet-800 dark:bg-violet-950/30"
            data-testid="selected-cuisines"
        >
            <h3 class="font-semibold text-brand" aria-live="polite">
                {{ $t('cuisine_selected_count', { count: selected.length }) }}
            </h3>
            <p v-if="!selected.length" class="mt-3 text-sm text-slate-500">{{ $t('cuisine_none_selected') }}</p>
            <div v-else class="mt-4 grid gap-3 sm:grid-cols-2">
                <div
                    v-for="c in selected"
                    :key="c.id"
                    class="flex min-w-0 items-center gap-3 rounded-xl bg-white p-3 dark:bg-slate-900"
                >
                    <img :src="c.image_path" alt="" class="h-12 w-12 shrink-0 rounded-lg object-cover" />
                    <div class="min-w-0 flex-1">
                        <p class="font-medium">{{ i18n.global.locale.value === 'ro' ? c.name_ro : c.name_en }}</p>
                        <p class="text-xs" :class="c.is_active ? 'text-brand' : 'text-amber-700'">
                            {{
                                !c.is_active
                                    ? $t('cuisine_unavailable')
                                    : since(c.id)
                                      ? $t('cuisine_active_since', {
                                            date: new Date(since(c.id)!).toLocaleDateString(localeTag()),
                                        })
                                      : $t('cuisine_pending')
                            }}
                        </p>
                    </div>
                    <Button
                        icon="pi pi-times"
                        text
                        rounded
                        :aria-label="
                            $t('cuisine_remove', { name: i18n.global.locale.value === 'ro' ? c.name_ro : c.name_en })
                        "
                        @click="toggle(c.id)"
                    />
                </div>
            </div>
        </section>
        <label class="field"
            >{{ $t('cuisine_search') }}<InputText v-model="search" :placeholder="$t('cuisine_search_placeholder')"
        /></label>
        <div class="grid gap-5 sm:grid-cols-2 2xl:grid-cols-3">
            <CuisineCard
                v-for="c in filtered"
                :key="c.id"
                :cuisine="c"
                :selected="ids.includes(c.id)"
                :selected-at="since(c.id)"
                selectable
                @toggle="toggle(c.id)"
            />
        </div>
        <p v-if="!filtered.length" class="muted py-8 text-center">{{ $t('cuisine_no_results') }}</p>
    </div>
</template>
