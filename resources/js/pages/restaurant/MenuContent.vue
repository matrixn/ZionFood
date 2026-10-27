<script setup lang="ts">
import { useLocationDates } from '@/lib/locationDates';
const dates = useLocationDates();
import { computed, ref, watch } from 'vue';
import { Head } from '@inertiajs/vue3';
import RestaurantLayout from '@/layouts/RestaurantLayout.vue';
import Button from 'primevue/button';
import InputText from 'primevue/inputtext';
import InputNumber from 'primevue/inputnumber';
import Textarea from 'primevue/textarea';
import MultiSelect from 'primevue/multiselect';
import Checkbox from 'primevue/checkbox';
import Dialog from 'primevue/dialog';
import { useToast } from 'primevue/usetoast';
import MenuItemEditor from '@/components/menu/MenuItemEditor.vue';
import ProductFoodData from '@/components/storefront/ProductFoodData.vue';
import { tr as t } from '@/i18n';
import { api } from '@/lib/http';
import { normalizeMenuContent } from '@/lib/menuContent';
import { useSetupEditorGuard } from '@/lib/useSetupEditorGuard';
import { useMenuNotifications } from '@/lib/menuNotifications';
import { allergenCodes } from '@/lib/foodLabels';
import type { WorkspaceProps, MenuContent, NutritionInputs, Product, Menu, ModifierGroup, Ingredient } from '@/types';
interface Choice {
    public_id: string;
    name: string;
    category: string;
    image_path: string | null;
    portion_size: string | null;
    has_nutrition: boolean;
    translation_count: number;
}
interface Current {
    public_id: string;
    name: string;
    description: string | null;
    image_path: string | null;
    product: Product;
    content: MenuContent;
    hash: string;
    nutrition_inputs: NutritionInputs;
    writable: boolean;
    drafts: { public_id: string; kind: string; status: string; created_at: string }[];
}
const props = defineProps<
    WorkspaceProps & {
        items: Choice[];
        current: Current | null;
        menus: Menu[];
        groups: ModifierGroup[];
        ingredients: Ingredient[];
        languages: Record<string, string>;
        aiReady: boolean;
    }
>();
const current = ref<Current | null>(props.current);
const inputs = ref<NutritionInputs>({
    ...(props.current?.nutrition_inputs ?? { portion_grams: null, ingredients_text: '', preparation: '' }),
});
const initial = ref(JSON.stringify(inputs.value));
const dirty = computed(() => JSON.stringify(inputs.value) !== initial.value);
const search = ref('');
const choices = ref(props.items);
const filtered = computed(() =>
    choices.value.filter((item) =>
        (item.name + ' ' + item.category).toLocaleLowerCase().includes(search.value.toLocaleLowerCase()),
    ),
);
const options = ref(['nutrition', 'food']);
const targetLanguages = ref<string[]>([]);
const languageOptions = computed(() => Object.entries(props.languages).map(([code, name]) => ({ code, name })));
const tasks = computed(() => [
    { code: 'nutrition', icon: 'pi-chart-bar', name: t('mc_nutrition'), hint: t('auto_nutrition_hint') },
    { code: 'food', icon: 'pi-shield', name: t('auto_food'), hint: t('auto_food_hint') },
    { code: 'rewrite', icon: 'pi-pencil', name: t('auto_rewrite'), hint: t('auto_rewrite_hint') },
    { code: 'translate', icon: 'pi-language', name: t('mc_translations'), hint: t('auto_translate_hint') },
]);
const busy = ref(false);
const loading = ref(false);
const error = ref('');
const history = ref(false);
const published = ref(false);
const editor = ref(false);
const queued = ref(false);
const bulk = ref(false);
const bulkLanguages = ref<string[]>([]);
const publishedContent = ref<MenuContent | null>(null);
const publishedHash = ref('');
const publishedError = ref('');
const manualLanguage = ref('en');
const allergenOptions = computed(() =>
    allergenCodes.map((code, index) => ({ code, name: t('allergen_group_' + (index + 1)) })),
);
const foodOptions = computed(() =>
    ['vegetarian', 'vegan', 'gluten_free', 'halal', 'nut_free', 'dairy_free', 'hot', 'raw'].map((code) => ({
        code,
        name: t('mc_label_' + code),
    })),
);
function openPublished() {
    if (!current.value) return;
    publishedContent.value = normalizeMenuContent(current.value.content);
    publishedHash.value = current.value.hash;
    publishedError.value = '';
    published.value = true;
}
function addManualLanguage() {
    if (!publishedContent.value || !current.value || publishedContent.value.translations[manualLanguage.value]) return;
    const product = current.value.product;
    publishedContent.value.translations[manualLanguage.value] = {
        name: product.name,
        description: product.description ?? '',
        ingredients_text: product.ingredients?.map((i) => i.name).join(', ') ?? '',
        portion_size: product.portion_size ?? '',
        category_name: '',
        menu_name: '',
        variants: Object.fromEntries(product.variants.map((v) => [v.id, v.name])),
        groups: Object.fromEntries(
            product.modifier_groups.map((g) => [
                g.id,
                { name: g.name, options: Object.fromEntries(g.options.map((o) => [o.id, o.name])) },
            ]),
        ),
    };
}
async function savePublished() {
    if (!current.value || !publishedContent.value) return;
    busy.value = true;
    publishedError.value = '';
    try {
        await api(`${base}/items/${current.value.public_id}`, 'PUT', {
            content: publishedContent.value,
            hash: publishedHash.value,
        });
        const preserved = { ...inputs.value };
        const wasDirty = dirty.value;
        const baseline = initial.value;
        sync(await api<Current>(`${base}/items/${current.value.public_id}`));
        if (wasDirty) {
            inputs.value = preserved;
            initial.value = baseline;
        }
        published.value = false;
        toast.add({ severity: 'success', summary: t('mc_saved'), life: 5000 });
    } catch (e) {
        publishedError.value = (e as Error).message;
    } finally {
        busy.value = false;
    }
}
async function translateMenu() {
    busy.value = true;
    error.value = '';
    try {
        await api(`${base}/generate`, 'POST', { item: null, kind: 'translate', languages: bulkLanguages.value });
        bulk.value = false;
        toast.add({ severity: 'info', summary: t('auto_queued'), detail: t('auto_background'), life: 7000 });
    } catch (e) {
        error.value = (e as Error).message;
    } finally {
        busy.value = false;
    }
}

const notices = useMenuNotifications();
const toast = useToast();
const base = `/app/locations/${props.location.public_id}/menu-content`;
const missing = computed(() => {
    if (!options.value.length) return t('auto_pick_task');
    if (
        options.value.includes('nutrition') &&
        (!inputs.value.portion_grams || inputs.value.ingredients_text.trim().length < 3)
    )
        return t('auto_missing');
    if (options.value.includes('translate') && !targetLanguages.value.length) return t('auto_pick_language');
    return '';
});
const pending = computed(() => current.value?.drafts.filter((d) => ['queued', 'processing'].includes(d.status)) ?? []);
function sync(row: Current) {
    if (!row.drafts.some((d) => ['queued', 'processing'].includes(d.status))) queued.value = false;
    current.value = row;
    inputs.value = { ...row.nutrition_inputs };
    initial.value = JSON.stringify(inputs.value);
    const choice = choices.value.find((item) => item.public_id === row.public_id);
    if (choice)
        Object.assign(choice, {
            name: row.name,
            image_path: row.image_path,
            portion_size: row.product.portion_size,
            has_nutrition: !!row.content.nutrition,
            translation_count: Object.keys(row.content.translations).length,
        });
}
async function load(id: string) {
    if (busy.value || loading.value) return;
    if (dirty.value && !confirm(t('mc_discard'))) return;
    loading.value = true;
    error.value = '';
    queued.value = false;
    try {
        sync(await api<Current>(`${base}/items/${id}`));
    } catch (e) {
        error.value = (e as Error).message;
    } finally {
        loading.value = false;
    }
}
async function savedProduct() {
    if (current.value) {
        initial.value = JSON.stringify(inputs.value);
        await load(current.value.public_id);
    }
}
async function generate() {
    if (!current.value || missing.value || busy.value) return;
    busy.value = true;
    error.value = '';
    try {
        if (dirty.value) {
            const content = normalizeMenuContent(current.value.content);
            content.nutrition_inputs = { ...inputs.value };
            await api(`${base}/items/${current.value.public_id}`, 'PUT', { content, hash: current.value.hash });
            sync(await api<Current>(`${base}/items/${current.value.public_id}`));
        }
        await api(`${base}/generate`, 'POST', {
            item: current.value.public_id,
            kind: 'analyze',
            options: options.value,
            languages: options.value.includes('translate') ? targetLanguages.value : [],
        });
        queued.value = true;
        sync(await api<Current>(`${base}/items/${current.value.public_id}`));
        toast.add({ severity: 'info', summary: t('auto_queued'), detail: t('auto_background'), life: 7000 });
    } catch (e) {
        error.value = (e as Error).message;
    } finally {
        busy.value = false;
    }
}
watch(
    () => notices.reviewed,
    async () => {
        if (!current.value || dirty.value || busy.value) return;
        try {
            sync(await api<Current>(`${base}/items/${current.value.public_id}`));
        } catch {
            /* Preserve the current editor on connectivity errors. */
        }
    },
);
watch(
    () => notices.records,
    async () => {
        if (!current.value || dirty.value || busy.value || !pending.value.length) return;
        if (
            notices.records.some(
                (n) =>
                    n.location === props.location.public_id &&
                    current.value?.drafts.some((d) => d.public_id === n.draft && d.status !== n.status),
            )
        ) {
            try {
                sync(await api<Current>(`${base}/items/${current.value.public_id}`));
            } catch {
                /* Retry on the next notification. */
            }
        }
    },
);
useSetupEditorGuard(
    () => true,
    () => dirty.value && !busy.value,
);
</script>
<template>
    <RestaurantLayout v-bind="props"
        ><Head :title="$t('mc_title')" />
        <div class="mx-auto max-w-[1440px] space-y-6">
            <header class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <p class="mb-2 text-xs font-semibold uppercase tracking-[0.2em] text-brand">
                        {{ $t('auto_eyebrow') }}
                    </p>
                    <h1 class="text-3xl font-semibold tracking-tight md:text-4xl">{{ $t('auto_title') }}</h1>
                    <p class="mt-3 max-w-2xl text-sm leading-relaxed text-slate-500">{{ $t('auto_intro') }}</p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <Button
                        :label="$t('auto_translate_menu')"
                        icon="pi pi-language"
                        severity="secondary"
                        outlined
                        :disabled="!aiReady || !current?.writable || busy"
                        @click="bulk = true"
                    />
                    <Button
                        :label="$t('auto_history')"
                        icon="pi pi-history"
                        severity="secondary"
                        outlined
                        :disabled="!current"
                        @click="history = true"
                    />
                </div>
            </header>
            <p v-if="!aiReady" class="rounded-2xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">
                {{ $t('mc_ai_unavailable') }}
            </p>
            <p v-if="error" role="alert" class="rounded-xl bg-red-50 p-4 text-sm text-red-700">{{ error }}</p>
            <div v-if="items.length" class="grid items-start gap-6 xl:grid-cols-[280px_minmax(0,1fr)]">
                <aside
                    class="rounded-2xl border border-slate-200 bg-white p-4 dark:border-slate-700 dark:bg-slate-900 xl:sticky xl:top-5"
                >
                    <div class="mb-3 flex items-center justify-between">
                        <h2 class="text-sm font-semibold">{{ $t('auto_pick_dish') }}</h2>
                        <span class="text-xs text-slate-400">{{ choices.length }}</span>
                    </div>
                    <span class="field-control-help"
                        ><InputText
                            v-model="search"
                            :placeholder="$t('auto_search')"
                            :aria-label="$t('auto_search')"
                            class="mb-3 w-full" /><FieldHelp :label="$t('auto_search')" field="search" kind="InputText"
                    /></span>
                    <div class="flex max-h-56 gap-2 overflow-auto xl:max-h-[62vh] xl:flex-col">
                        <button
                            v-for="item in filtered"
                            :key="item.public_id"
                            :disabled="busy || loading"
                            :aria-pressed="current?.public_id === item.public_id"
                            class="flex min-w-52 items-center gap-3 rounded-xl border p-3 text-left transition xl:min-w-0"
                            :class="
                                current?.public_id === item.public_id
                                    ? 'border-violet-300 bg-violet-50 dark:bg-violet-950'
                                    : 'border-transparent hover:bg-slate-50 dark:hover:bg-slate-800'
                            "
                            @click="load(item.public_id)"
                        >
                            <img
                                v-if="item.image_path"
                                :src="'/storage/' + item.image_path"
                                alt=""
                                class="h-11 w-11 shrink-0 rounded-lg object-cover"
                            /><span
                                v-else
                                class="flex h-11 w-11 shrink-0 items-center justify-center rounded-lg bg-slate-100 text-slate-400 dark:bg-slate-800"
                                ><i class="pi pi-shop" /></span
                            ><span class="min-w-0 flex-1"
                                ><strong class="block text-sm leading-snug">{{ item.name }}</strong
                                ><span class="mt-1 block text-[11px] text-slate-500">{{ item.category }}</span
                                ><span
                                    v-if="item.has_nutrition || item.translation_count"
                                    class="mt-1 block text-[10px] text-emerald-700"
                                    >{{ item.has_nutrition ? $t('auto_nutrition_ready') : ''
                                    }}{{
                                        item.translation_count
                                            ? (item.has_nutrition ? ' · ' : '') +
                                              item.translation_count +
                                              ' ' +
                                              $t(
                                                  item.translation_count === 1
                                                      ? 'auto_language_short'
                                                      : 'auto_languages_short',
                                              )
                                            : ''
                                    }}</span
                                ></span
                            >
                        </button>
                    </div>
                    <p v-if="!filtered.length" class="py-6 text-center text-sm text-slate-400">
                        {{ $t('auto_no_match') }}
                    </p>
                </aside>
                <div v-if="current" class="min-w-0 space-y-5" :class="{ 'opacity-60': loading }">
                    <div
                        class="sticky top-3 z-10 rounded-2xl border border-violet-200 bg-white/95 p-4 shadow-lg backdrop-blur dark:border-slate-700 dark:bg-slate-900/95"
                    >
                        <div class="flex items-center justify-between gap-4">
                            <div class="hidden min-w-0 flex-1 text-xs leading-relaxed text-slate-500 lg:block">
                                <p v-if="!current.writable">{{ $t('auto_read_only') }}</p>
                                <p v-else-if="missing" class="text-amber-700">{{ missing }}</p>
                                <p v-else><i class="pi pi-lock mr-1" />{{ $t('auto_review_first') }}</p>
                            </div>
                            <Button
                                :label="$t('auto_generate')"
                                class="w-full shrink-0 lg:w-auto"
                                icon="pi pi-sparkles"
                                :loading="busy"
                                :disabled="
                                    busy || loading || !aiReady || !current.writable || !!missing || !!pending.length
                                "
                                @click="generate"
                            />
                        </div>
                    </div>
                    <section class="overflow-hidden rounded-2xl bg-slate-950 text-white">
                        <div class="flex items-center gap-5 p-6 md:p-7">
                            <img
                                v-if="current.image_path"
                                :src="'/storage/' + current.image_path"
                                :alt="current.name"
                                class="h-24 w-24 shrink-0 rounded-2xl object-cover md:h-28 md:w-28"
                            />
                            <div class="min-w-0 flex-1">
                                <p class="mb-2 text-xs text-violet-300">{{ $t('auto_selected_dish') }}</p>
                                <h2 class="text-2xl font-semibold tracking-tight">{{ current.name }}</h2>
                                <p class="mt-2 line-clamp-2 text-sm leading-relaxed text-slate-300">
                                    {{ current.description || $t('auto_no_description') }}
                                </p>
                            </div>
                        </div>
                        <div
                            class="flex flex-wrap items-center justify-between gap-2 border-t border-white/10 px-6 py-3"
                        >
                            <span class="text-xs text-slate-400"
                                ><i class="pi pi-check-circle mr-1 text-emerald-400" />{{
                                    $t('auto_source_hint')
                                }}</span
                            >
                            <div class="flex gap-2">
                                <Button
                                    :label="$t('auto_published')"
                                    icon="pi pi-eye"
                                    text
                                    size="small"
                                    class="!text-violet-200"
                                    @click="openPublished"
                                /><Button
                                    :label="$t('auto_edit_product')"
                                    icon="pi pi-pencil"
                                    text
                                    size="small"
                                    class="!text-violet-200"
                                    :disabled="!current.writable || busy"
                                    @click="editor = true"
                                />
                            </div>
                        </div>
                    </section>
                    <div
                        v-if="queued || pending.length"
                        role="status"
                        class="flex gap-3 rounded-2xl border border-violet-200 bg-violet-50 p-4 text-sm dark:bg-violet-950"
                    >
                        <i class="pi pi-spin pi-spinner mt-1 text-brand" />
                        <div>
                            <strong>{{ $t('auto_queued') }}</strong>
                            <p class="mt-1 text-xs leading-relaxed text-slate-500">{{ $t('auto_background') }}</p>
                        </div>
                    </div>
                    <section
                        class="rounded-2xl border border-slate-200 bg-white p-5 dark:border-slate-700 dark:bg-slate-900 md:p-7"
                    >
                        <div class="mb-5 flex items-center gap-3">
                            <span
                                class="flex h-8 w-8 items-center justify-center rounded-full bg-violet-50 text-sm font-semibold text-brand"
                                >1</span
                            >
                            <div>
                                <h2 class="font-semibold">{{ $t('auto_inputs_title') }}</h2>
                                <p class="mt-1 text-xs text-slate-500">{{ $t('auto_inputs_hint') }}</p>
                            </div>
                        </div>
                        <div class="grid gap-5 md:grid-cols-[190px_minmax(0,1fr)]">
                            <label class="grid content-start gap-2 text-sm font-medium"
                                ><span class="field-label"
                                    >{{ $t('auto_portion_weight')
                                    }}<FieldHelp :label="$t('auto_portion_weight')" field="auto_portion_weight" /></span
                                ><InputNumber
                                    v-model="inputs.portion_grams"
                                    :min="1"
                                    :max="10000"
                                    :max-fraction-digits="1"
                                    suffix=" g"
                                    :disabled="!current.writable || busy"
                                    class="w-full min-w-0"
                                    input-class="w-full min-w-0"
                                    :aria-label="$t('auto_portion_weight')"
                                /><span class="text-xs font-normal leading-relaxed text-slate-400">{{
                                    $t('auto_one_portion_hint')
                                }}</span></label
                            ><label class="grid gap-2 text-sm font-medium"
                                ><span class="field-label"
                                    >{{ $t('auto_ingredients')
                                    }}<FieldHelp :label="$t('auto_ingredients')" field="auto_ingredients" /></span
                                ><Textarea
                                    v-model="inputs.ingredients_text"
                                    :aria-label="$t('auto_ingredients')"
                                    rows="3"
                                    maxlength="3000"
                                    :disabled="!current.writable || busy"
                                    class="w-full"
                                    :placeholder="$t('auto_ingredients_example')"
                                /><span class="text-xs font-normal leading-relaxed text-slate-400">{{
                                    $t('auto_ingredients_hint')
                                }}</span></label
                            >
                        </div>
                        <details class="mt-5 text-sm">
                            <summary class="cursor-pointer text-slate-500">
                                {{ $t('auto_preparation_optional') }}
                            </summary>
                            <span class="field-control-help"
                                ><Textarea
                                    v-model="inputs.preparation"
                                    rows="2"
                                    maxlength="500"
                                    :disabled="!current.writable || busy"
                                    :aria-label="$t('auto_preparation_optional')"
                                    :placeholder="$t('auto_preparation_example')"
                                    class="mt-3 w-full" /><FieldHelp
                                    :label="$t('auto_preparation_optional')"
                                    field="preparation"
                                    kind="Textarea"
                            /></span>
                        </details>
                    </section>
                    <section
                        class="rounded-2xl border border-slate-200 bg-white p-5 dark:border-slate-700 dark:bg-slate-900 md:p-7"
                    >
                        <div class="mb-5 flex items-center gap-3">
                            <span
                                class="flex h-8 w-8 items-center justify-center rounded-full bg-violet-50 text-sm font-semibold text-brand"
                                >2</span
                            >
                            <div>
                                <h2 class="font-semibold">{{ $t('auto_tasks_title') }}</h2>
                                <p class="mt-1 text-xs text-slate-500">{{ $t('auto_tasks_hint') }}</p>
                            </div>
                        </div>
                        <div class="grid gap-3 md:grid-cols-2">
                            <label
                                v-for="task in tasks"
                                :key="task.code"
                                class="flex cursor-pointer items-start gap-3 rounded-xl border p-4 transition"
                                :class="
                                    options.includes(task.code)
                                        ? 'border-violet-300 bg-violet-50/60 dark:bg-violet-950'
                                        : 'border-slate-200 dark:border-slate-700'
                                "
                                ><Checkbox
                                    v-model="options"
                                    :value="task.code"
                                    :disabled="!current.writable || busy"
                                    :aria-label="task.name"
                                /><span
                                    ><span class="block text-sm font-semibold"
                                        ><i :class="['pi', task.icon, 'mr-2 text-brand']" /><span class="field-label"
                                            >{{ task.name }}<FieldHelp :label="task.name" field="" /></span></span
                                    ><span class="mt-1 block text-xs leading-relaxed text-slate-500">{{
                                        task.hint
                                    }}</span></span
                                ></label
                            >
                        </div>
                        <div
                            v-if="options.includes('translate')"
                            class="mt-4 rounded-xl bg-slate-50 p-4 dark:bg-slate-800"
                        >
                            <label for="auto-languages" class="mb-2 block text-sm font-medium"
                                ><span class="field-label"
                                    >{{ $t('auto_target_languages')
                                    }}<FieldHelp
                                        :label="$t('auto_target_languages')"
                                        field="auto_target_languages" /></span></label
                            ><MultiSelect
                                v-model="targetLanguages"
                                input-id="auto-languages"
                                :options="languageOptions"
                                option-label="name"
                                option-value="code"
                                display="chip"
                                :selection-limit="3"
                                :disabled="!current.writable || busy"
                                :placeholder="$t('auto_pick_language')"
                                class="w-full"
                            />
                            <p class="mt-2 text-xs text-slate-400">{{ $t('auto_language_hint') }}</p>
                        </div>
                    </section>
                    <p v-if="missing || !current.writable" class="px-1 text-xs leading-relaxed text-amber-700">
                        {{ !current.writable ? $t('auto_read_only') : missing }}
                    </p>
                </div>
            </div>
            <div v-else class="panel py-16 text-center">
                <i class="pi pi-book mb-4 text-4xl text-violet-300" />
                <h2 class="text-xl font-semibold">{{ $t('auto_empty_title') }}</h2>
                <p class="muted mt-2">{{ $t('auto_empty_hint') }}</p>
                <a :href="'/app/locations/' + location.public_id + '/menu'" class="mt-5 inline-block text-brand"
                    >{{ $t('menu_studio') }} →</a
                >
            </div>
        </div>
        <Dialog
            v-model:visible="history"
            modal
            :header="$t('auto_history')"
            :style="{ width: '36rem', maxWidth: 'calc(100vw - 2rem)' }"
            ><p v-if="!current?.drafts.length" class="muted py-8 text-center">{{ $t('auto_no_history') }}</p>
            <button
                v-for="draft in current?.drafts"
                :key="draft.public_id"
                class="mb-2 flex w-full items-center justify-between rounded-xl border border-slate-200 p-4 text-left text-sm dark:border-slate-700"
                @click="
                    history = false;
                    notices.open(location.public_id, draft.public_id);
                "
            >
                <span
                    >{{ $t('mc_status_' + draft.status)
                    }}<span class="mt-1 block text-xs text-slate-400">{{
                        dates.dateTime(draft.created_at)
                    }}</span></span
                ><i class="pi pi-arrow-right text-brand" /></button
        ></Dialog>
        <Dialog
            v-model:visible="bulk"
            modal
            :header="$t('auto_translate_menu')"
            :style="{ width: '32rem', maxWidth: 'calc(100vw - 2rem)' }"
        >
            <p class="mb-5 text-sm leading-relaxed text-slate-500">{{ $t('auto_bulk_hint') }}</p>
            <p v-if="error" role="alert" class="mb-3 text-sm text-red-700">{{ error }}</p>
            <span class="field-control-help"
                ><MultiSelect
                    v-model="bulkLanguages"
                    :options="languageOptions"
                    option-label="name"
                    option-value="code"
                    display="chip"
                    :selection-limit="3"
                    :aria-label="$t('auto_target_languages')"
                    :disabled="busy"
                    class="w-full" /><FieldHelp
                    :label="$t('auto_target_languages')"
                    field="bulkLanguages"
                    kind="MultiSelect"
            /></span>
            <template #footer
                ><Button
                    :label="$t('auto_translate_menu')"
                    icon="pi pi-language"
                    :loading="busy"
                    :disabled="busy || !bulkLanguages.length"
                    @click="translateMenu"
            /></template>
        </Dialog>
        <Dialog
            v-model:visible="published"
            modal
            :header="$t('auto_published')"
            :style="{ width: '42rem', maxWidth: 'calc(100vw - 2rem)' }"
        >
            <div v-if="current && publishedContent" class="space-y-5">
                <p class="text-xs leading-relaxed text-slate-500">{{ $t('auto_manual_hint') }}</p>
                <p v-if="publishedError" role="alert" class="rounded-xl bg-red-50 p-4 text-sm text-red-700">
                    {{ publishedError }}
                </p>
                <label
                    v-for="field in ['allergens', 'may_contain', 'labels'] as const"
                    :key="field"
                    class="grid gap-2 text-sm"
                    ><span class="field-label"
                        >{{ $t('mc_' + field) }}<FieldHelp :label="$t('mc_' + field)" field="mc_" /></span
                    ><MultiSelect
                        v-model="publishedContent[field]"
                        :aria-label="$t('mc_' + field)"
                        :options="field === 'labels' ? foodOptions : allergenOptions"
                        option-label="name"
                        option-value="code"
                        display="chip"
                        :disabled="!current.writable || busy"
                /></label>
                <ProductFoodData
                    :product="{
                        ...current.product,
                        content: { ...publishedContent, allergens: [], may_contain: [], labels: [] },
                    }"
                />
                <div class="border-t border-slate-200 pt-5 dark:border-slate-700">
                    <h3 class="mb-3 font-semibold">{{ $t('mc_translations') }}</h3>
                    <div v-if="current.writable" class="mb-4 flex gap-2">
                        <span class="field-control-help"
                            ><select
                                v-model="manualLanguage"
                                :aria-label="$t('mc_language')"
                                class="min-w-0 flex-1 rounded-lg border border-slate-200 bg-transparent p-2"
                            >
                                <option v-for="(name, code) in props.languages" :key="code" :value="code">
                                    {{ name }}
                                </option></select
                            ><FieldHelp :label="$t('mc_language')" field="manualLanguage" kind="select" /></span
                        ><Button
                            :label="$t('mc_add_language')"
                            icon="pi pi-plus"
                            outlined
                            size="small"
                            :disabled="busy || !!publishedContent.translations[manualLanguage]"
                            @click="addManualLanguage"
                        />
                    </div>
                    <details
                        v-for="(row, language) in publishedContent.translations"
                        :key="language"
                        class="mb-3 rounded-xl border border-slate-200 p-4 dark:border-slate-700"
                    >
                        <summary class="cursor-pointer text-sm font-semibold">
                            {{ props.languages[language] ?? language }} · {{ row.name }}
                        </summary>
                        <div class="mt-4 grid gap-3">
                            <label
                                v-for="field in [
                                    'name',
                                    'description',
                                    'ingredients_text',
                                    'portion_size',
                                    'category_name',
                                    'menu_name',
                                ] as const"
                                :key="field"
                                class="grid gap-1 text-xs text-slate-500"
                                ><span class="field-label"
                                    >{{ $t('mc_translation_' + field)
                                    }}<FieldHelp :label="$t('mc_translation_' + field)" field="mc_translation_" /></span
                                ><Textarea
                                    v-if="field === 'description' || field === 'ingredients_text'"
                                    v-model="row[field]"
                                    :aria-label="$t('mc_translation_' + field)"
                                    rows="3"
                                    :disabled="!current.writable || busy" /><InputText
                                    v-else
                                    v-model="row[field]"
                                    :aria-label="$t('mc_translation_' + field)"
                                    :disabled="!current.writable || busy" /></label
                            ><label v-for="(_, id) in row.variants" :key="id" class="grid gap-1 text-xs"
                                ><span class="field-label"
                                    >{{ $t('variants') }}<FieldHelp :label="$t('variants')" field="variants" /></span
                                ><InputText
                                    v-model="row.variants[id]"
                                    :aria-label="$t('variants')"
                                    :disabled="!current.writable || busy"
                            /></label>
                            <div v-for="(group, id) in row.groups" :key="id" class="grid gap-2">
                                <span class="field-control-help"
                                    ><InputText
                                        v-model="group.name"
                                        :aria-label="$t('mc_translation_group')"
                                        :disabled="!current.writable || busy" /><FieldHelp
                                        :label="$t('mc_translation_group')"
                                        field="name"
                                        kind="InputText" /></span
                                ><span v-for="(_, option) in group.options" :key="option" class="field-control-help"
                                    ><InputText
                                        v-model="group.options[option]"
                                        :aria-label="$t('mc_translation_group')"
                                        :disabled="!current.writable || busy" /><FieldHelp
                                        :label="$t('mc_translation_group')"
                                        field="optionsoption"
                                        kind="InputText"
                                /></span>
                            </div>
                        </div>
                    </details>
                </div>
            </div>
            <template #footer
                ><Button :label="$t('close')" text :disabled="busy" @click="published = false" /><Button
                    v-if="current?.writable"
                    :label="$t('mc_save_content')"
                    icon="pi pi-check"
                    :loading="busy"
                    :disabled="busy"
                    @click="savePublished"
            /></template>
        </Dialog>
        <MenuItemEditor
            v-if="current"
            v-model:visible="editor"
            :location-id="location.public_id"
            :product="current.product"
            :categories="menus.flatMap((menu) => menu.categories)"
            :groups="groups"
            :ingredients="ingredients"
            @saved="savedProduct"
        />
    </RestaurantLayout>
</template>
