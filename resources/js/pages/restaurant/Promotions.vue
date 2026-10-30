<script setup lang="ts">
import { ref } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import RestaurantLayout from '@/layouts/RestaurantLayout.vue';
import InputText from 'primevue/inputtext';
import InputNumber from 'primevue/inputnumber';
import MultiSelect from 'primevue/multiselect';
import Select from 'primevue/select';
import ToggleSwitch from 'primevue/toggleswitch';
import Button from 'primevue/button';
import type { WorkspaceProps } from '@/types';
interface Promotion {
    public_id: string;
    name: string;
    code: string | null;
    kind: string;
    value: number;
    minimum_order: number;
    max_discount: number | null;
    fulfillments: string[];
    product_ids: string[] | null;
    buy_quantity: number;
    reward_quantity: number;
    starts_at: string | null;
    ends_at: string | null;
    usage_limit: number | null;
    used_count: number;
    active: boolean;
}
defineProps<WorkspaceProps & { promotions: Promotion[] }>();
const empty = {
    name: '',
    code: '',
    kind: 'percent',
    value: 1000,
    minimum_order: 0,
    max_discount: null as number | null,
    fulfillments: ['pickup', 'delivery'],
    product_ids: null as string[] | null,
    buy_quantity: 1,
    reward_quantity: 1,
    starts_at: '',
    ends_at: '',
    usage_limit: null as number | null,
    active: true,
};
const form = useForm({ ...empty });
const selected = ref<string | null>(null);
function edit(p: Promotion) {
    selected.value = p.public_id;
    Object.assign(form, { ...p, code: p.code ?? '', starts_at: p.starts_at ?? '', ends_at: p.ends_at ?? '' });
}
function reset() {
    selected.value = null;
    form.reset();
    form.clearErrors();
}
</script>
<template>
    <RestaurantLayout v-bind="$props"
        ><Head :title="$t('promotions_title')" />
        <h1 class="text-3xl font-semibold">{{ $t('promotions_title') }}</h1>
        <p class="muted my-3">{{ $t('promotions_intro') }}</p>
        <form
            class="panel my-5 grid gap-4 md:grid-cols-2"
            @submit.prevent="
                form.post(`/app/locations/${location.public_id}/promotions/${selected ?? ''}`, {
                    preserveScroll: true,
                    onSuccess: reset,
                })
            "
        >
            <label v-for="key in ['name', 'code'] as const" :key="key" class="field"
                ><span class="field-label"
                    >{{ $t('promo_' + key)
                    }}<FieldHelp :label="$t('promo_' + key)" :text="$t('promo_' + key + '_help')" /></span
                ><InputText
                    v-model="form[key]"
                    :aria-label="$t('promo_' + key)"
                    :required="key === 'name'"
                    :maxlength="key === 'code' ? 40 : 100"
            /></label>
            <label class="field"
                ><span class="field-label"
                    >{{ $t('promo_kind') }}<FieldHelp :label="$t('promo_kind')" :text="$t('promo_kind_help')" /></span
                ><Select
                    v-model="form.kind"
                    :aria-label="$t('promo_kind')"
                    :options="['percent', 'fixed', 'free_delivery', 'buy_x_get_y']"
            /></label>
            <label class="field"
                ><span class="field-label"
                    >{{ $t('promo_fulfillments')
                    }}<FieldHelp :label="$t('promo_fulfillments')" :text="$t('promo_fulfillments_help')" /></span
                ><MultiSelect
                    v-model="form.fulfillments"
                    :aria-label="$t('promo_fulfillments')"
                    :options="['pickup', 'delivery', 'dine_in']"
            /></label>
            <label
                v-for="key in [
                    'value',
                    'minimum_order',
                    'max_discount',
                    'buy_quantity',
                    'reward_quantity',
                    'usage_limit',
                ] as const"
                :key="key"
                class="field"
                ><span class="field-label"
                    >{{ $t('promo_' + key)
                    }}<FieldHelp :label="$t('promo_' + key)" :text="$t('promo_' + key + '_help')" /></span
                ><InputNumber
                    v-model="form[key]"
                    :aria-label="$t('promo_' + key)"
                    :use-grouping="false"
                    :min="['value', 'minimum_order'].includes(key) ? 0 : 1"
                    :max="['buy_quantity', 'reward_quantity'].includes(key) ? 20 : 100000000"
            /></label>
            <label v-for="key in ['starts_at', 'ends_at'] as const" :key="key" class="field"
                ><span class="field-label"
                    >{{ $t('promo_' + key)
                    }}<FieldHelp :label="$t('promo_' + key)" :text="$t('promo_time_help')" /></span
                ><InputText
                    v-model="form[key]"
                    :aria-label="$t('promo_' + key)"
                    placeholder="2026-11-01T10:00:00+02:00"
            /></label>
            <label class="flex items-center gap-3"
                ><ToggleSwitch v-model="form.active" :aria-label="$t('promo_active')" /><span>{{
                    $t('promo_active')
                }}</span
                ><FieldHelp :label="$t('promo_active')" :text="$t('promo_active_help')"
            /></label>
            <p v-for="(message, key) in form.errors" :key="key" role="alert" class="text-red-600">{{ message }}</p>
            <div class="flex gap-3 md:col-span-2">
                <Button type="submit" :label="$t('save')" :loading="form.processing" /><Button
                    v-if="selected"
                    :label="$t('cancel')"
                    outlined
                    @click="reset"
                />
            </div>
        </form>
        <section class="panel">
            <article
                v-for="promotion in promotions"
                :key="promotion.public_id"
                class="flex flex-wrap items-center justify-between gap-3 border-t border-slate-200 py-4"
            >
                <div>
                    <strong>{{ promotion.name }}</strong>
                    <p class="muted">
                        {{ promotion.kind }} · {{ promotion.code ?? $t('promo_automatic') }} ·
                        {{ promotion.used_count }}/{{ promotion.usage_limit ?? '∞' }}
                    </p>
                    <p>{{ $t(promotion.active ? 'wp_active' : 'wp_revoked') }}</p>
                </div>
                <Button :label="$t('edit')" outlined @click="edit(promotion)" />
            </article>
        </section>
    </RestaurantLayout>
</template>
