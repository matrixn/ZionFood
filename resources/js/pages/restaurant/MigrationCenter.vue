<script setup lang="ts">
import { useLocationDates } from '@/lib/locationDates';
const dates = useLocationDates();
import { ref, computed, onUnmounted } from 'vue';
import { Head } from '@inertiajs/vue3';
import RestaurantLayout from '@/layouts/RestaurantLayout.vue';
import ZFFormField from '@/components/ui/ZFFormField.vue';
import ZFPageHeader from '@/components/ui/ZFPageHeader.vue';
import Button from 'primevue/button';
import Password from 'primevue/password';
import Select from 'primevue/select';
import InputNumber from 'primevue/inputnumber';
import Checkbox from 'primevue/checkbox';
import Dialog from 'primevue/dialog';
import { useToast } from 'primevue/usetoast';
import { tr as t } from '@/i18n';
import { api, money } from '@/lib/http';
import type { WorkspaceProps } from '@/types';
interface Batch {
    public_id: string;
    status: string;
    source_restaurant: string | null;
    created_at: string;
}
interface Group {
    name: string;
    min_choices: number;
    max_choices: number;
    is_required: boolean;
    allow_quantity: boolean;
    options: { name: string; price: number; is_default: boolean }[];
}
interface ImportedNutrition {
    basis: string;
    values: Record<string, number>;
}
interface ImportedProduct {
    key: string;
    name: string;
    description: string;
    price: number;
    order_types: string[] | null;
    content: { allergens: string[]; labels: string[]; nutrition: ImportedNutrition | null };
    groups: Group[];
    variants: {
        name: string;
        price: number;
        is_default: boolean;
        nutrition: ImportedNutrition | null;
        groups: Group[];
    }[];
}
interface Preview {
    batch_id: string;
    status: string;
    failure_code: string | null;
    review_hash: string | null;
    conflicts: string[];
    issues: { severity: string; code: string; path: string; message: string }[];
    report: {
        products: number;
        categories: number;
        blockers: number;
        currency: string;
        adapter_version: string;
        source_changes: { key: string; name: string; change: string }[];
        synthetic_baskets: {
            product: string;
            variant: string | null;
            unit_price: number;
            total: number;
            currency: string;
        }[];
        checksum: string;
    } | null;
    menu: { categories: { name: string; groups: Group[]; items: ImportedProduct[] }[] } | null;
}
const props = defineProps<WorkspaceProps & { batches: Batch[] }>();
const base = `/app/locations/${props.location.public_id}/migration-center`;
const batches = ref([...props.batches]);
const source = ref('api');
const key = ref('');
const file = ref<File>();
const authorized = ref(false);
const sizePrices = ref('base_plus_size');
const nutritionBasis = ref('per_100g');
const tax = ref(0);
const unresolved = computed(() => preview.value?.conflicts.some((id) => !resolutions.value[id]) ?? false);
const nutrientMappings = ref<Record<string, string>>({});
const unmappedNutrients = computed(() => [
    ...new Set(
        preview.value?.issues.filter((i) => i.code === 'nutrient_id').map((i) => i.path.split('.').at(-1)!) ?? [],
    ),
]);
const nutrientOptions = [
    'energy_kj',
    'energy_kcal',
    'fat_g',
    'saturates_g',
    'carbohydrate_g',
    'sugars_g',
    'protein_g',
    'salt_g',
    'fibre_g',
];
async function resolveFood() {
    busy.value = true;
    error.value = '';
    try {
        await api(`${base}/${selected.value}/resolve`, 'POST', { nutrition_map: nutrientMappings.value });
        acknowledged.value = false;
        await load(selected.value);
    } catch (e) {
        error.value = (e as Error).message;
    } finally {
        busy.value = false;
    }
}
const busy = ref(false);
const error = ref('');
const preview = ref<Preview | null>(null);
const selected = ref('');
const acknowledged = ref(false);
const resolutions = ref<Record<string, string>>({});
const confirm = ref(false);
const toast = useToast();
let timer: ReturnType<typeof setTimeout> | undefined;
onUnmounted(() => clearTimeout(timer));
async function load(id: string) {
    clearTimeout(timer);
    selected.value = id;
    error.value = '';
    try {
        const data = await api<Preview>(`${base}/${id}`);
        if (selected.value !== id) return;
        const wasQueued = preview.value?.status === 'queued';
        preview.value = data;
        if (data.status === 'queued') timer = setTimeout(() => void load(id), 3000);
        else if (wasQueued && data.status === 'ready')
            toast.add({ severity: 'success', summary: t('mig_ready'), detail: t('mig_ready_help'), life: 10000 });
        const row = batches.value.find((b) => b.public_id === id);
        if (row) row.status = data.status;
    } catch (e) {
        error.value = (e as Error).message;
    }
}
async function start() {
    busy.value = true;
    error.value = '';
    acknowledged.value = false;
    resolutions.value = {};
    try {
        const data = new FormData();
        data.append('source', source.value);
        if (source.value === 'api') data.append('key', key.value);
        else if (file.value) data.append('file', file.value);
        data.append('authorized', authorized.value ? '1' : '0');
        data.append('size_prices', sizePrices.value);
        data.append('nutrition_basis', nutritionBasis.value);
        data.append('tax_basis_points', String(tax.value));
        const result = await api<{ batch_id: string }>(base, 'POST', data);
        key.value = '';
        batches.value.unshift({
            public_id: result.batch_id,
            status: 'queued',
            source_restaurant: null,
            created_at: new Date().toISOString(),
        });
        preview.value = null;
        await load(result.batch_id);
    } catch (e) {
        error.value = (e as Error).message;
    } finally {
        busy.value = false;
        key.value = '';
    }
}
async function publish() {
    if (!preview.value) return;
    busy.value = true;
    error.value = '';
    try {
        await api(`${base}/${selected.value}/publish`, 'POST', {
            acknowledged: acknowledged.value,
            resolutions: resolutions.value,
            review_hash: preview.value.review_hash,
        });
        confirm.value = false;
        await load(selected.value);
        toast.add({ severity: 'success', summary: t('mig_published'), life: 7000 });
    } catch (e) {
        error.value = (e as Error).message;
        confirm.value = false;
    } finally {
        busy.value = false;
    }
}
async function revert() {
    busy.value = true;
    error.value = '';
    try {
        await api(`${base}/${selected.value}/revert`, 'POST');
        await load(selected.value);
    } catch (e) {
        error.value = (e as Error).message;
    } finally {
        busy.value = false;
    }
}
function open(id: string) {
    acknowledged.value = false;
    resolutions.value = {};
    preview.value = null;
    void load(id);
}
</script>
<template>
    <RestaurantLayout v-bind="$props"
        ><Head :title="$t('mig_title')" /><ZFPageHeader :title="$t('mig_title')" :description="$t('mig_intro')" />
        <div class="mb-6 grid gap-3 sm:grid-cols-3">
            <div
                v-for="(step, i) in ['mig_connect', 'mig_verify', 'mig_publish']"
                :key="step"
                class="flex items-center gap-3 rounded-2xl border border-violet-100 bg-white p-4 dark:bg-slate-900"
            >
                <span
                    class="flex size-9 items-center justify-center rounded-full bg-violet-100 font-semibold text-violet-700"
                    >{{ i + 1 }}</span
                ><strong>{{ $t(step) }}</strong>
            </div>
        </div>
        <p v-if="error" role="alert" class="mb-4 rounded-xl bg-red-50 p-4 text-red-800">{{ error }}</p>
        <div class="grid grid-cols-1 items-start gap-6 xl:grid-cols-[22rem_minmax(0,1fr)]">
            <aside class="panel grid min-w-0 gap-4">
                <h2 class="text-xl font-semibold">GloriaFood → ZionFood</h2>
                <p class="text-sm text-slate-500">{{ $t('mig_key_help') }}</p>
                <ZFFormField :label="$t('mig_source')"
                    ><Select
                        v-model="source"
                        :options="[
                            { label: $t('mig_api'), value: 'api' },
                            { label: $t('mig_file'), value: 'file' },
                        ]"
                        option-label="label"
                        option-value="value"
                        :aria-label="$t('mig_source')"
                /></ZFFormField>
                <ZFFormField v-if="source === 'api'" :label="$t('mig_key')" :help="$t('mig_key_help')"
                    ><Password
                        v-model="key"
                        :feedback="false"
                        toggle-mask
                        autocomplete="off"
                        :aria-label="$t('mig_key')"
                /></ZFFormField>
                <ZFFormField v-else :label="$t('mig_file')"
                    ><input
                        type="file"
                        accept="application/json,.json"
                        :aria-label="$t('mig_file')"
                        @change="file = ($event.target as HTMLInputElement).files?.[0]"
                /></ZFFormField>
                <ZFFormField :label="$t('mig_price_mode')" :help="$t('mig_price_help')"
                    ><Select
                        v-model="sizePrices"
                        :options="[
                            { label: $t('mig_additive'), value: 'base_plus_size' },
                            { label: $t('mig_absolute'), value: 'absolute' },
                        ]"
                        option-label="label"
                        option-value="value"
                        :aria-label="$t('mig_price_mode')"
                /></ZFFormField>
                <ZFFormField :label="$t('mig_basis')" :help="$t('mig_basis_help')"
                    ><Select
                        v-model="nutritionBasis"
                        :options="['per_100g', 'per_100ml', 'per_serving']"
                        :aria-label="$t('mig_basis')"
                /></ZFFormField>
                <ZFFormField :label="$t('mig_tax')"
                    ><InputNumber v-model="tax" :min="0" :max="10000" :aria-label="$t('mig_tax')"
                /></ZFFormField>
                <label class="flex items-start gap-3 text-sm"
                    ><Checkbox v-model="authorized" binary :aria-label="$t('mig_authorized')" /><span
                        >{{ $t('mig_authorized')
                        }}<FieldHelp :label="$t('mig_authorized')" :text="$t('mig_authorized_help')" /></span
                ></label>
                <Button
                    :label="$t('mig_scan')"
                    icon="pi pi-cloud-download"
                    :loading="busy"
                    :disabled="busy || !authorized || (source === 'api' ? !key.trim() : !file)"
                    @click="start"
                />
                <p class="text-xs leading-relaxed text-slate-500">{{ $t('mig_staging_help') }}</p>
                <hr class="border-slate-100" />
                <details>
                    <summary class="cursor-pointer font-semibold">{{ $t('mig_history') }}</summary>
                    <div class="mt-3 grid min-w-0 gap-2">
                        <Button
                            v-for="b in batches"
                            :key="b.public_id"
                            text
                            class="w-full min-w-0 !justify-between [&_.p-button-label]:whitespace-normal [&_.p-button-label]:break-words"
                            :label="`${dates.dateTime(b.created_at)} · ${$t('mig_status_' + b.status)}`"
                            @click="open(b.public_id)"
                        />
                    </div>
                </details>
            </aside>
            <section class="min-w-0 space-y-5">
                <div v-if="!preview" class="panel py-20 text-center">
                    <i class="pi pi-file-import mb-4 text-4xl text-violet-400" />
                    <h2 class="text-xl font-semibold">{{ $t('mig_empty') }}</h2>
                    <p class="muted mt-2">{{ $t('mig_staging_help') }}</p>
                </div>
                <template v-else
                    ><div class="panel">
                        <div class="flex flex-wrap items-center justify-between gap-3">
                            <h2 class="text-xl font-semibold">{{ $t('mig_status_' + preview.status) }}</h2>
                            <a
                                v-if="preview.report"
                                :href="`${base}/${selected}/print`"
                                target="_blank"
                                rel="noopener"
                                class="text-brand underline"
                                >PDF / Print</a
                            >
                            <a
                                v-if="preview.report"
                                :href="`${base}/${selected}/report`"
                                class="text-brand underline"
                                >{{ $t('mig_report') }}</a
                            >
                        </div>
                        <p v-if="preview.status === 'queued'" role="status" class="muted mt-3">{{ $t('mig_wait') }}</p>
                        <p v-if="preview.status === 'failed'" role="alert" class="mt-3 text-red-700">
                            {{
                                $t(
                                    preview.failure_code === 'import_complexity_limit'
                                        ? 'mig_complexity_limit'
                                        : preview.failure_code === 'source_validation_failed'
                                          ? 'mig_validation_failure'
                                          : 'mig_failure',
                                )
                            }}
                        </p>
                        <div v-if="preview.report" class="mt-5 grid grid-cols-3 gap-3">
                            <div
                                v-for="(value, name) in {
                                    mig_products: preview.report.products,
                                    mig_categories: preview.report.categories,
                                    mig_blockers: preview.report.blockers,
                                }"
                                :key="name"
                                class="rounded-xl bg-slate-50 p-3 dark:bg-slate-800"
                            >
                                <strong class="text-2xl">{{ value }}</strong>
                                <p class="text-xs">{{ $t(name) }}</p>
                            </div>
                        </div>
                    </div>
                    <details v-if="preview.report" class="panel">
                        <summary class="cursor-pointer font-semibold">{{ $t('mig_diff') }}</summary>
                        <p class="muted my-3">{{ $t('mig_diff_help') }}</p>
                        <ul class="max-h-64 overflow-auto text-sm">
                            <li v-for="change in preview.report.source_changes" :key="change.key">
                                {{ $t('mig_change_' + change.change) }} · {{ change.name }} ({{ change.key }})
                            </li>
                        </ul>
                        <h3 class="my-3 font-semibold">{{ $t('mig_baskets') }}</h3>
                        <ul class="max-h-64 overflow-auto text-sm">
                            <li v-for="(basket, i) in preview.report.synthetic_baskets" :key="i">
                                {{ basket.product }} · {{ basket.variant }} · {{ money(basket.total, basket.currency) }}
                            </li>
                        </ul>
                        <p class="mt-2 text-xs text-slate-500">{{ $t('mig_baskets_help') }}</p>
                    </details>
                    <section v-if="preview.issues.length" class="panel">
                        <h3 class="mb-3 font-semibold">{{ $t('mig_verification') }}</h3>
                        <ul class="space-y-3">
                            <li
                                v-for="(issue, i) in preview.issues"
                                :key="i"
                                class="rounded-xl border-l-4 p-3 text-sm"
                                :class="
                                    issue.severity === 'blocker'
                                        ? 'border-red-500 bg-red-50 text-red-900'
                                        : 'border-amber-400 bg-amber-50 text-amber-900'
                                "
                            >
                                <strong>{{ $t('mig_issue_' + issue.code) }}</strong> · {{ issue.message
                                }}<small class="mt-1 block break-all opacity-70">{{ issue.path }}</small>
                            </li>
                        </ul>
                    </section>
                    <section v-if="preview.status === 'ready' && unmappedNutrients.length" class="panel grid gap-3">
                        <h3 class="font-semibold">{{ $t('mig_map_nutrients') }}</h3>
                        <p class="muted">{{ $t('mig_map_help') }}</p>
                        <ZFFormField
                            v-for="id in unmappedNutrients"
                            :key="id"
                            :label="`ID ${id}`"
                            :help="$t('mig_map_help')"
                            ><Select
                                v-model="nutrientMappings[id]"
                                :options="
                                    nutrientOptions.map((value) => ({ value, label: $t('mc_nutrient_' + value) }))
                                "
                                option-label="label"
                                option-value="value"
                                :aria-label="`ID ${id}`" /></ZFFormField
                        ><Button
                            :label="$t('mig_revalidate')"
                            :loading="busy"
                            :disabled="busy || unmappedNutrients.some((id) => !nutrientMappings[id])"
                            @click="resolveFood"
                        />
                    </section>
                    <section v-if="preview.conflicts.length" class="panel grid gap-3">
                        <h3 class="font-semibold">{{ $t('mig_conflicts') }}</h3>
                        <ZFFormField
                            v-for="id in preview.conflicts"
                            :key="id"
                            :label="`${$t('mig_product_id')} ${id}`"
                            :help="$t('mig_conflict_help')"
                            ><Select
                                v-model="resolutions[id]"
                                :options="[
                                    { label: $t('mig_keep'), value: 'keep_local' },
                                    { label: $t('mig_replace'), value: 'use_source' },
                                ]"
                                option-label="label"
                                option-value="value"
                                :aria-label="`${$t('mig_product_id')} ${id}`"
                        /></ZFFormField>
                    </section>
                    <div v-for="(category, ci) in preview.menu?.categories" :key="ci" class="panel">
                        <h3 class="mb-3 text-lg font-semibold">{{ category.name }}</h3>
                        <p v-if="category.groups.length" class="muted mb-4">
                            {{ $t('mig_category_groups') }}: {{ category.groups.map((g) => g.name).join(', ') }}
                        </p>
                        <details v-for="item in category.items" :key="item.key" class="border-t border-slate-100 py-3">
                            <summary class="cursor-pointer font-medium">
                                {{ item.name }}
                                <span class="float-right">{{ money(item.price, preview?.report?.currency) }}</span>
                            </summary>
                            <p class="my-2 text-sm text-slate-500">{{ item.description }}</p>
                            <p class="text-xs">{{ item.order_types?.join(' · ') ?? $t('mig_all_services') }}</p>
                            <p class="text-xs">
                                {{ item.content.allergens.join(', ') }} · {{ item.content.labels.join(', ') }}
                            </p>
                            <p v-if="item.content.nutrition" class="my-2 text-xs">
                                {{ item.content.nutrition.basis }} ·
                                {{
                                    Object.entries(item.content.nutrition.values)
                                        .map(([name, value]) => `${$t('mc_nutrient_' + name)}: ${value}`)
                                        .join(' · ')
                                }}
                            </p>
                            <div
                                v-for="(part, vi) in [
                                    { name: $t('mig_product_groups'), groups: [...category.groups, ...item.groups] },
                                    ...item.variants,
                                ]"
                                :key="vi"
                                class="mt-3 rounded-xl bg-slate-50 p-3 text-sm dark:bg-slate-800"
                            >
                                <strong>{{ part.name }}</strong
                                ><span v-if="'price' in part">
                                    · {{ money(part.price, preview?.report?.currency) }}
                                    {{ part.is_default ? '✓' : '' }}</span
                                >
                                <p v-if="'nutrition' in part && part.nutrition" class="text-xs">
                                    {{ part.nutrition?.basis }} ·
                                    {{
                                        Object.entries(part.nutrition?.values ?? {})
                                            .map(([name, value]) => `${$t('mc_nutrient_' + name)}: ${value}`)
                                            .join(' · ')
                                    }}
                                </p>
                                <p v-for="(g, gi) in part.groups" :key="gi" class="mt-2 text-xs">
                                    {{ g.name }} · {{ g.min_choices }}–{{ g.max_choices }} ·
                                    {{ $t(g.is_required ? 'mig_required' : 'mig_optional') }} ·
                                    {{ $t(g.allow_quantity ? 'mig_multiple' : 'mig_single')
                                    }}<span class="block text-slate-500">{{
                                        g.options
                                            .map(
                                                (o) =>
                                                    `${o.is_default ? '✓ ' : ''}${o.name} +${money(o.price, preview?.report?.currency)}`,
                                            )
                                            .join(' / ')
                                    }}</span>
                                </p>
                            </div>
                        </details>
                    </div>
                    <div
                        v-if="preview.status === 'ready'"
                        class="panel sticky bottom-3 z-10 border border-violet-200 shadow-lg"
                    >
                        <label class="mb-3 flex items-start gap-3 text-sm"
                            ><Checkbox v-model="acknowledged" binary :aria-label="$t('mig_reviewed')" /><span
                                >{{ $t('mig_reviewed')
                                }}<FieldHelp
                                    :label="$t('mig_reviewed')"
                                    :text="$t('mig_reviewed_help')" /></span></label
                        ><Button
                            :label="$t('mig_publish')"
                            icon="pi pi-check-circle"
                            :disabled="busy || !acknowledged || !!preview.report?.blockers || unresolved"
                            @click="confirm = true"
                        />
                    </div>
                    <div v-if="preview.status === 'published'" class="panel">
                        <p class="muted mb-3">{{ $t('mig_revert_help') }}</p>
                        <Button
                            :label="$t('mig_revert')"
                            outlined
                            severity="danger"
                            :loading="busy"
                            @click="confirm = true"
                        />
                    </div>
                </template>
            </section>
        </div>
        <Dialog v-model:visible="confirm" modal :header="$t('mig_confirm')" class="w-full max-w-lg"
            ><p>{{ $t(preview?.status === 'published' ? 'mig_revert_help' : 'mig_publish_help') }}</p>
            <template #footer
                ><Button :label="$t('cancel')" text @click="confirm = false" /><Button
                    :label="$t(preview?.status === 'published' ? 'mig_revert' : 'mig_publish')"
                    :loading="busy"
                    @click="preview?.status === 'published' ? ((confirm = false), revert()) : publish()" /></template
        ></Dialog>
    </RestaurantLayout>
</template>

<style scoped>
aside {
    grid-template-columns: minmax(0, 1fr);
}
aside :deep(.field),
aside :deep(.p-select),
aside :deep(.p-inputnumber),
aside :deep(.p-password) {
    min-width: 0;
    width: 100%;
    max-width: 100%;
}
aside :deep(.p-select-label) {
    min-width: 0;
    overflow: hidden;
    text-overflow: ellipsis;
}
aside :deep(.p-button-label) {
    white-space: normal;
    overflow-wrap: anywhere;
}
aside > * {
    min-width: 0;
}
</style>
