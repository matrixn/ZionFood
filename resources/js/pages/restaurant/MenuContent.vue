<script setup lang="ts">
import { computed, ref } from 'vue';
import { Head, router, Link } from '@inertiajs/vue3';
import RestaurantLayout from '@/layouts/RestaurantLayout.vue';
import Button from 'primevue/button';
import InputText from 'primevue/inputtext';
import InputNumber from 'primevue/inputnumber';
import Textarea from 'primevue/textarea';
import Select from 'primevue/select';
import MultiSelect from 'primevue/multiselect';
import { tr as t } from '@/i18n';
import { api } from '@/lib/http';
import { normalizeMenuContent } from '@/lib/menuContent';
import { useSetupEditorGuard } from '@/lib/useSetupEditorGuard';
import type { WorkspaceProps, MenuContent, MenuTranslation } from '@/types';
interface Draft {
    public_id: string;
    kind: string;
    status: string;
    source_hash: string;
    error_code: string | null;
    proposal: Partial<MenuContent> & { description?: string };
    report: Record<string, unknown> | null;
}
const props = defineProps<
    WorkspaceProps & {
        items: { public_id: string; name: string }[];
        current: {
            public_id: string;
            name: string;
            content: MenuContent;
            hash: string;
            translationHash: string;
            source: {
                variants: { id: number; name: string }[];
                groups: { id: number; name: string; options: { id: number; name: string }[] }[];
            };
        } | null;
        contentWritable: boolean;
        languages: Record<string, string>;
        allergenCodes: string[];
        labelCodes: string[];
        nutrientCodes: string[];
        drafts: Draft[];
        aiReady: boolean;
    }
>();
function clone<T>(value: T): T {
    return JSON.parse(JSON.stringify(value));
}
const selected = ref(props.current?.public_id);
const content = ref<MenuContent>(
    normalizeMenuContent(
        props.current?.content ?? {
            allergens: [],
            may_contain: [],
            labels: [],
            nutrition: null,
            translations: {},
            recipe: [],
            yield_grams: null,
            servings: null,
        },
    ),
);
const initial = ref(JSON.stringify(content.value));
const editingHash = ref(props.current?.hash);
const dirty = computed(() => JSON.stringify(content.value) !== initial.value);
const targetLanguages = ref<string[]>([]);
const language = ref('en');
const busy = ref(false);
const error = ref('');
const importItems = ref<{ id: number; name: string; tags?: string[]; extras?: unknown; sizes?: unknown[] }[]>([]);
const importId = ref<number>();
const base = `/app/locations/${props.location.public_id}/menu-content`;
const languageOptions = computed(() => Object.entries(props.languages).map(([code, name]) => ({ code, name })));
const allergenOptions = computed(() =>
    props.allergenCodes.map((code, i) => ({ code, name: t('allergen_group_' + (i + 1)) })),
);
const labelOptions = computed(() => props.labelCodes.map((code) => ({ code, name: t('mc_label_' + code) })));
async function run(task: () => Promise<unknown>) {
    busy.value = true;
    error.value = '';
    try {
        await task();
        initial.value = JSON.stringify(content.value);
        router.reload({ onSuccess: sync });
    } catch (e) {
        error.value = (e as Error).message;
    } finally {
        busy.value = false;
    }
}
function sync() {
    if (props.current) {
        content.value = normalizeMenuContent(props.current.content);
        initial.value = JSON.stringify(content.value);
        editingHash.value = props.current.hash;
    }
}
function refresh() {
    if (dirty.value && !confirm(t('mc_discard'))) return;
    router.reload({ onSuccess: sync });
}
useSetupEditorGuard(
    () => true,
    () => dirty.value && !busy.value,
);
function choose(id: string) {
    if (dirty.value && !confirm(t('mc_discard'))) return;
    router.get(base, { item: id });
}
function save() {
    if (props.current)
        void run(() =>
            api(`${base}/items/${props.current!.public_id}`, 'PUT', {
                content: content.value,
                hash: editingHash.value,
            }),
        );
}
function generate(kind: string, all = false) {
    if (dirty.value) {
        error.value = t('mc_save_first');
        return;
    }
    void run(() =>
        api(`${base}/generate`, 'POST', {
            item: all ? null : props.current?.public_id,
            kind,
            languages: targetLanguages.value,
        }),
    );
}
function addLanguage() {
    if (content.value.translations[language.value]) return;
    content.value.translations[language.value] = {
        name: '',
        description: '',
        ingredients_text: '',
        portion_size: '',
        category_name: '',
        menu_name: '',
        variants: Object.fromEntries((props.current?.source.variants ?? []).map((v) => [v.id, v.name])),
        groups: Object.fromEntries(
            (props.current?.source.groups ?? []).map((g) => [
                g.id,
                { name: g.name, options: Object.fromEntries(g.options.map((o) => [o.id, o.name])) },
            ]),
        ),
    };
}
function addIngredient() {
    content.value.recipe.push({
        name: '',
        grams: 0,
        reference: '',
        values: Object.fromEntries(props.nutrientCodes.map((key) => [key, null])),
    });
}
async function readFile(event: Event) {
    error.value = '';
    importItems.value = [];
    importId.value = undefined;
    const file = (event.target as HTMLInputElement).files?.[0];
    if (!file) return;
    if (file.size > 2 * 1024 * 1024) {
        error.value = t('mc_invalid_file');
        return;
    }
    try {
        const source = JSON.parse(await file.text());
        const rows = Array.isArray(source.categories)
            ? source.categories.flatMap((c: { items?: unknown[] }) => c.items ?? [])
            : [source];
        importItems.value = rows
            .map((row: Record<string, unknown>) => ({ ...row, source_restaurant_id: source.restaurant_id ?? null }))
            .filter((v: { id?: number; name?: string }) => Number.isInteger(v.id) && typeof v.name === 'string');
        if (!importItems.value.length || importItems.value.length > 1000) throw new Error();
        importId.value = importItems.value[0].id;
    } catch {
        error.value = t('mc_invalid_file');
    }
}
function importContent() {
    if (dirty.value) {
        error.value = t('mc_save_first');
        return;
    }
    const source = importItems.value.find((i) => i.id === importId.value);
    if (source && props.current)
        void run(() => api(`${base}/items/${props.current!.public_id}/gloriafood`, 'POST', { source_item: source }));
}
function review(draft: Draft, action: string) {
    if (dirty.value) {
        error.value = t('mc_save_first');
        return;
    }
    void run(() => api(`${base}/drafts/${draft.public_id}`, 'POST', { action }));
}
function loadDraft(draft: Draft) {
    if (dirty.value && !confirm(t('mc_discard'))) return;
    const proposal = clone(draft.proposal);
    if (proposal.description !== undefined) {
        error.value = t('mc_rewrite_review');
        return;
    }
    content.value = {
        ...content.value,
        ...proposal,
        translations: { ...content.value.translations, ...proposal.translations },
    };
}
function download(draft: Draft) {
    const url = URL.createObjectURL(
        new Blob(
            [
                JSON.stringify(
                    { kind: draft.kind, status: draft.status, proposal: draft.proposal, report: draft.report },
                    null,
                    2,
                ),
            ],
            { type: 'application/json' },
        ),
    );
    const a = document.createElement('a');
    a.href = url;
    a.download = `menu-content-${draft.public_id}.json`;
    a.click();
    URL.revokeObjectURL(url);
}
function translated(draft: Draft): Record<string, MenuTranslation> {
    return draft.proposal?.translations ?? {};
}
</script>
<template>
    <RestaurantLayout v-bind="$props"
        ><Head :title="$t('mc_title')" />
        <div class="mb-6 flex flex-wrap items-start justify-between gap-4">
            <div>
                <Link :href="`/app/locations/${location.public_id}/menu`" class="text-sm text-brand"
                    >← {{ $t('menu_studio') }}</Link
                >
                <h1 class="mt-3 text-3xl font-semibold">{{ $t('mc_title') }}</h1>
                <p class="muted mt-2">{{ $t('mc_intro') }}</p>
            </div>
            <Button :label="$t('refresh')" icon="pi pi-refresh" outlined @click="refresh" />
        </div>
        <p v-if="error" role="alert" class="mb-5 rounded-xl bg-red-50 p-4 text-red-800">{{ error }}</p>
        <label class="field mb-6 max-w-xl"
            >{{ $t('mc_product')
            }}<Select
                :model-value="selected"
                :options="items"
                option-label="name"
                option-value="public_id"
                :aria-label="$t('mc_product')"
                filter
                @update:model-value="choose"
        /></label>
        <template v-if="current">
            <div class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_minmax(18rem,24rem)]">
                <form id="menu-content-form" class="min-w-0 space-y-6" @submit.prevent="save">
                    <fieldset :disabled="!contentWritable || busy" class="panel min-w-0 space-y-5">
                        <legend class="px-2 text-lg font-semibold">{{ $t('mc_food_data') }}</legend>
                        <p class="muted">{{ $t('mc_food_hint') }}</p>
                        <label class="field"
                            >{{ $t('mc_allergens')
                            }}<MultiSelect v-model="content.allergens"
                                :disabled="!contentWritable || busy"
                                :options="allergenOptions"
                                option-label="name"
                                option-value="code"
                                :aria-label="$t('mc_allergens')"
                                class="w-full"
                        /></label>
                        <label class="field"
                            >{{ $t('mc_may_contain')
                            }}<MultiSelect v-model="content.may_contain"
                                :disabled="!contentWritable || busy"
                                :options="allergenOptions"
                                option-label="name"
                                option-value="code"
                                :aria-label="$t('mc_may_contain')"
                                class="w-full"
                        /></label>
                        <label class="field"
                            >{{ $t('mc_labels')
                            }}<MultiSelect v-model="content.labels"
                                :disabled="!contentWritable || busy"
                                :options="labelOptions"
                                option-label="name"
                                option-value="code"
                                :aria-label="$t('mc_labels')"
                                class="w-full"
                        /></label>
                    </fieldset>
                    <fieldset :disabled="!contentWritable || busy" class="panel min-w-0 space-y-5">
                        <legend class="px-2 text-lg font-semibold">{{ $t('mc_recipe') }}</legend>
                        <p class="muted">{{ $t('mc_recipe_hint') }}</p>
                        <div class="grid gap-4 sm:grid-cols-2">
                            <label class="field"
                                >{{ $t('mc_yield')
                                }}<InputNumber v-model="content.yield_grams"
                                    :disabled="!contentWritable || busy"
                                    class="w-full min-w-0"
                                    input-class="w-full min-w-0"
                                    :min="1"
                                    :max="100000"
                                    :max-fraction-digits="3" /></label
                            ><label class="field"
                                >{{ $t('mc_servings')
                                }}<InputNumber v-model="content.servings"
                                    :disabled="!contentWritable || busy"
                                    class="w-full min-w-0"
                                    input-class="w-full min-w-0"
                                    :min="1"
                                    :max="1000"
                                    :max-fraction-digits="3"
                            /></label>
                        </div>
                        <details
                            v-for="(row, index) in content.recipe"
                            :key="index"
                            class="rounded-xl border border-slate-200 p-4"
                            open
                        >
                            <summary class="cursor-pointer font-medium">
                                {{ row.name || $t('mc_ingredient') }} · {{ row.grams }} g
                            </summary>
                            <div class="mt-4 grid min-w-0 gap-4 sm:grid-cols-2">
                                <label class="field min-w-0"
                                    >{{ $t('name')
                                    }}<InputText v-model="row.name" :disabled="!contentWritable || busy" required class="w-full min-w-0" /></label
                                ><label class="field"
                                    >{{ $t('mc_grams')
                                    }}<InputNumber v-model="row.grams"
                                        :disabled="!contentWritable || busy"
                                        class="w-full min-w-0"
                                        input-class="w-full min-w-0"
                                        :min="0.001"
                                        :max="100000"
                                        :max-fraction-digits="3" /></label
                                ><label class="field min-w-0 sm:col-span-2"
                                    >{{ $t('mc_reference')
                                    }}<InputText v-model="row.reference" :disabled="!contentWritable || busy" required class="w-full min-w-0" /></label
                                ><label v-for="key in nutrientCodes" :key="key" class="field min-w-0"
                                    >{{ $t('mc_nutrient_' + key)
                                    }}<InputNumber v-model="row.values[key]"
                                        :disabled="!contentWritable || busy"
                                        class="w-full min-w-0"
                                        input-class="w-full min-w-0"
                                        :min="0"
                                        :max="key.startsWith('energy_') ? 10000 : 100"
                                        :max-fraction-digits="3"
                                /></label>
                            </div>
                            <Button
                                :label="$t('remove')"
                                severity="danger"
                                text
                                class="mt-3"
                                @click="content.recipe.splice(index, 1)"
                            />
                        </details>
                        <Button :label="$t('mc_add_ingredient')" icon="pi pi-plus" outlined @click="addIngredient" />
                    </fieldset>
                    <fieldset :disabled="!contentWritable || busy" class="panel min-w-0 space-y-5">
                        <legend class="px-2 text-lg font-semibold">{{ $t('mc_nutrition') }}</legend>
                        <p class="muted">{{ $t('mc_nutrition_hint') }}</p>
                        <div class="flex flex-wrap gap-3">
                            <Button :label="$t('mc_calculate')" outlined @click="generate('calculation')" /><Button
                                v-if="!content.nutrition"
                                :label="$t('mc_declared_values')"
                                text
                                @click="
                                    content.nutrition = {
                                        basis: 'per_100g',
                                        values: {},
                                        reference: '',
                                        estimated: false,
                                    }
                                "
                            />
                        </div>
                        <template v-if="content.nutrition"
                            ><label class="field"
                                >{{ $t('mc_basis')
                                }}<Select v-model="content.nutrition.basis"
                                    :disabled="!contentWritable || busy"
                                    :options="
                                        ['per_100g', 'per_100ml', 'per_serving'].map((code) => ({
                                            code,
                                            name: $t('mc_' + code),
                                        }))
                                    "
                                    option-label="name"
                                    option-value="code" /></label
                            ><label class="field"
                                >{{ $t('mc_reference')
                                }}<InputText v-model="content.nutrition.reference" :disabled="!contentWritable || busy" class="w-full min-w-0"
                            /></label>
                            <div class="grid gap-4 sm:grid-cols-2">
                                <label v-for="key in nutrientCodes" :key="key" class="field min-w-0"
                                    >{{ $t('mc_nutrient_' + key)
                                    }}<InputNumber v-model="content.nutrition.values[key]"
                                        :disabled="!contentWritable || busy"
                                        class="w-full min-w-0"
                                        input-class="w-full min-w-0"
                                        :min="0"
                                        :max-fraction-digits="3"
                                /></label>
                            </div>
                            <Button :label="$t('remove')" severity="danger" text @click="content.nutrition = null"
                        /></template>
                    </fieldset>
                    <fieldset :disabled="!contentWritable || busy" class="panel min-w-0 space-y-5">
                        <legend class="px-2 text-lg font-semibold">{{ $t('mc_translations') }}</legend>
                        <p class="muted">{{ $t('mc_translation_hint') }}</p>
                        <div class="flex flex-wrap gap-3">
                            <Select v-model="language"
                                :disabled="!contentWritable || busy"
                                :options="languageOptions"
                                option-label="name"
                                option-value="code"
                                :aria-label="$t('mc_language')"
                            /><Button :label="$t('mc_add_language')" outlined @click="addLanguage" />
                        </div>
                        <details
                            v-for="(row, code) in content.translations"
                            :key="code"
                            open
                            class="rounded-xl border border-slate-200 p-4"
                        >
                            <summary class="font-semibold">
                                {{ props.languages[code] }}
                                <span
                                    v-if="row.source_hash && row.source_hash !== current.translationHash"
                                    class="text-sm text-amber-700"
                                    >· {{ $t('mc_stale_translation') }}</span
                                >
                            </summary>
                            <div class="mt-4 grid min-w-0 gap-4">
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
                                    class="field min-w-0"
                                    >{{ $t('mc_translation_' + field)
                                    }}<Textarea v-model="row[field]"
                                        :disabled="!contentWritable || busy"
                                        :rows="field === 'description' || field === 'ingredients_text' ? 3 : 1"
                                        class="w-full min-w-0"
                                /></label>
                                <label v-for="(_, id) in row.variants" :key="id" class="field"
                                    >{{ current.source.variants.find((v) => String(v.id) === String(id))?.name
                                    }}<InputText v-model="row.variants[id]" :disabled="!contentWritable || busy" class="w-full min-w-0"
                                /></label>
                                <div
                                    v-for="(group, id) in row.groups"
                                    :key="id"
                                    class="grid gap-3 rounded-lg bg-slate-50 p-3"
                                >
                                    <label class="field"
                                        >{{ $t('mc_translation_group')
                                        }}<InputText v-model="group.name" :disabled="!contentWritable || busy" class="w-full min-w-0" /></label
                                    ><label v-for="(_, option) in group.options" :key="option" class="field"
                                        >{{
                                            current.source.groups
                                                .find((g) => String(g.id) === String(id))
                                                ?.options.find((o) => String(o.id) === String(option))?.name
                                        }}<InputText v-model="group.options[option]" :disabled="!contentWritable || busy" class="w-full min-w-0"
                                    /></label>
                                </div>
                            </div>
                            <Button
                                :label="$t('remove')"
                                severity="danger"
                                text
                                class="mt-3"
                                @click="delete content.translations[code]"
                            />
                        </details>
                    </fieldset>
                    <div
                        v-if="contentWritable"
                        class="sticky bottom-4 z-10 flex items-center justify-between gap-4 rounded-2xl border border-violet-100 bg-white/95 p-4 shadow-xl backdrop-blur dark:bg-slate-900"
                    >
                        <span class="text-sm">{{ dirty ? $t('mc_unsaved') : $t('mc_saved_content') }}</span
                        ><Button
                            type="submit"
                            :label="$t('mc_save_content')"
                            :loading="busy"
                            :disabled="busy || !dirty"
                        />
                    </div>
                </form>
                <aside class="min-w-0 space-y-6">
                    <section v-if="contentWritable" class="panel space-y-4">
                        <h2 class="text-lg font-semibold">{{ $t('mc_ai_assistant') }}</h2>
                        <p class="muted">{{ $t('mc_ai_hint') }}</p>
                        <p v-if="!aiReady" class="text-sm text-amber-700">{{ $t('mc_ai_unconfigured') }}</p>
                        <MultiSelect
                            v-model="targetLanguages"
                            :options="languageOptions"
                            option-label="name"
                            option-value="code"
                            :aria-label="$t('mc_target_languages')"
                            class="w-full"
                        />
                        <p class="muted">{{ $t('mc_three_languages') }}</p>
                        <div class="grid gap-3">
                            <Button
                                :label="$t('mc_translate_product')"
                                :disabled="busy || !aiReady || !targetLanguages.length || targetLanguages.length > 3"
                                @click="generate('translate')"
                            /><Button
                                :label="$t('mc_translate_menu')"
                                outlined
                                :disabled="busy || !aiReady || !targetLanguages.length || targetLanguages.length > 3"
                                @click="generate('translate', true)"
                            /><Button
                                :label="$t('mc_suggest')"
                                outlined
                                :disabled="busy || !aiReady"
                                @click="generate('suggest')"
                            /><Button
                                :label="$t('mc_rewrite')"
                                text
                                :disabled="busy || !aiReady"
                                @click="generate('rewrite')"
                            />
                        </div>
                        <p class="muted">{{ $t('mc_data_sent') }}</p>
                    </section>
                    <section v-if="contentWritable" class="panel space-y-4">
                        <h2 class="text-lg font-semibold">{{ $t('mc_gloriafood') }}</h2>
                        <p class="muted">{{ $t('mc_gloria_hint') }}</p>
                        <input
                            type="file"
                            accept=".json,application/json"
                            class="w-full min-w-0 text-sm"
                            :aria-label="$t('mc_gloriafood')"
                            @change="readFile"
                        /><Select
                            v-if="importItems.length"
                            v-model="importId"
                            :options="importItems"
                            option-label="name"
                            option-value="id"
                            filter
                            class="w-full"
                            :aria-label="$t('mc_source_product')"
                        />
                        <p v-if="importId" class="muted">GloriaFood #{{ importId }} → {{ current.name }}</p>
                        <Button
                            :label="$t('mc_import_preview')"
                            outlined
                            :disabled="busy || !importId"
                            @click="importContent"
                        />
                    </section>
                    <section class="space-y-4">
                        <h2 class="text-lg font-semibold">{{ $t('mc_drafts') }}</h2>
                        <p v-if="!drafts.length" class="muted">{{ $t('mc_no_drafts') }}</p>
                        <article v-for="draft in drafts" :key="draft.public_id" class="panel min-w-0 space-y-3">
                            <div class="flex flex-wrap justify-between gap-2">
                                <strong>{{ $t('mc_kind_' + draft.kind) }}</strong
                                ><span class="text-sm text-brand">{{ $t('mc_status_' + draft.status) }}</span>
                            </div>
                            <p v-if="draft.status === 'queued' || draft.status === 'processing'" class="muted">
                                {{ $t('mc_queue_hint') }}
                            </p>
                            <p v-if="draft.error_code" class="text-sm text-red-700">
                                {{ $t('mc_error_' + draft.error_code) }}
                            </p>
                            <p
                                v-if="draft.source_hash !== current.hash && draft.status === 'ready'"
                                class="text-sm text-amber-700"
                            >
                                {{ $t('mc_stale_draft') }}
                            </p>
                            <template v-if="draft.proposal"
                                ><div
                                    v-for="(row, code) in translated(draft)"
                                    :key="code"
                                    class="rounded-xl bg-violet-50 p-3 text-sm"
                                >
                                    <strong>{{ props.languages[code] }} · {{ row.name }}</strong>
                                    <p class="mt-2 whitespace-pre-wrap">{{ row.description }}</p>
                                    <p class="muted mt-2">({{ row.ingredients_text }})</p>
                                </div>
                                <p v-if="draft.proposal.description" class="whitespace-pre-wrap text-sm">
                                    {{ draft.proposal.description }}
                                </p>
                                <p v-if="draft.proposal.allergens" class="text-sm">
                                    {{ $t('mc_allergens') }}:
                                    {{
                                        draft.proposal.allergens
                                            .map((code) => allergenOptions.find((a) => a.code === code)?.name)
                                            .join(', ') || '—'
                                    }}
                                </p>
                                <p v-if="draft.proposal.labels" class="text-sm">
                                    {{ $t('mc_labels') }}:
                                    {{ draft.proposal.labels.map((code) => $t('mc_label_' + code)).join(', ') || '—' }}
                                </p>
                                <div v-if="draft.proposal.nutrition" class="text-sm">
                                    <strong>{{ $t('mc_' + draft.proposal.nutrition.basis) }}</strong>
                                    <p v-for="(value, key) in draft.proposal.nutrition.values" :key="key">
                                        {{ $t('mc_nutrient_' + key) }}: {{ value ?? '—' }}
                                    </p>
                                </div></template
                            >
                            <p v-if="draft.report?.notes" class="muted">{{ draft.report.notes }}</p>
                            <p v-if="draft.kind === 'gloriafood'" class="text-sm text-amber-700">
                                {{ $t('mc_import_review') }}
                            </p>
                            <Button :label="$t('mc_download_report')" text size="small" @click="download(draft)" />
                            <div v-if="contentWritable && draft.status === 'ready'" class="grid gap-2">
                                <Button
                                    :label="$t('mc_approve')"
                                    :disabled="busy || draft.source_hash !== current.hash"
                                    @click="review(draft, 'approve')"
                                /><Button
                                    :label="$t('mc_edit_proposal')"
                                    outlined
                                    :disabled="busy || draft.source_hash !== current.hash"
                                    @click="loadDraft(draft)"
                                /><Button
                                    :label="$t('mc_reject')"
                                    severity="secondary"
                                    text
                                    :disabled="busy"
                                    @click="review(draft, 'reject')"
                                />
                            </div>
                        </article>
                    </section>
                </aside>
            </div>
        </template>
        <p v-else class="panel muted">{{ $t('mc_no_products') }}</p>
    </RestaurantLayout>
</template>
