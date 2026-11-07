<script setup lang="ts">
import { computed, reactive, watch } from 'vue';
import Button from 'primevue/button';
import MultiSelect from 'primevue/multiselect';
import Select from 'primevue/select';
import ZFFormField from '@/components/ui/ZFFormField.vue';
import { tr as t } from '@/i18n';
import type { RewardCampaign, RewardMode } from '@/lib/rewards';

const props = defineProps<{
    campaign?: RewardCampaign;
    products: { public_id: string; name: string }[];
    busy: boolean;
}>();
const emit = defineEmits<{ save: [values: Record<string, unknown>] }>();
const modes = computed(() =>
    (['customer_qr', 'venue_qr', 'online'] as const).map((mode) => ({ label: t('rewards_mode_' + mode), value: mode })),
);
const form = reactive({
    name: '',
    mode: 'customer_qr' as RewardMode,
    buy_products: [] as string[],
    buy_quantity: 5,
    reward_product: '',
    reward_quantity: 1,
    starts_on: '',
    ends_on: '',
    active: true,
});
watch(
    () => props.campaign,
    (campaign) => {
        form.name = campaign?.name ?? '';
        form.starts_on = campaign?.starts_on ?? '';
        form.ends_on = campaign?.ends_on ?? '';
        form.active = campaign?.active ?? true;
    },
    { immediate: true },
);
const valid = computed(
    () =>
        form.name.trim() &&
        (!form.starts_on || !form.ends_on || form.starts_on <= form.ends_on) &&
        (props.campaign ||
            (form.buy_products.length > 0 &&
                form.reward_product &&
                form.buy_quantity >= 1 &&
                form.buy_quantity <= 100 &&
                form.reward_quantity >= 1 &&
                form.reward_quantity <= 50)),
);
const reward = computed(() => props.products.find((product) => product.public_id === form.reward_product)?.name);
</script>

<template>
    <form class="grid gap-4" @submit.prevent="valid && emit('save', { ...form })">
        <ZFFormField for="reward-name" :label="$t('rewards_name')" :help="$t('rewards_help_name')">
            <input id="reward-name" v-model="form.name" class="reward-input w-full" maxlength="100" required />
        </ZFFormField>
        <template v-if="!campaign">
            <ZFFormField for="reward-mode" :label="$t('rewards_method')" :help="$t('rewards_help_method')">
                <Select
                    v-model="form.mode"
                    input-id="reward-mode"
                    :aria-label="$t('rewards_method')"
                    :options="modes"
                    option-label="label"
                    option-value="value"
                />
                <small class="muted">{{ $t('rewards_desc_' + form.mode) }}</small>
            </ZFFormField>
            <div class="grid items-start gap-4 sm:grid-cols-[1fr_8rem]">
                <ZFFormField
                    for="reward-buy-products"
                    :label="$t('rewards_eligible')"
                    :help="$t('rewards_help_eligible')"
                >
                    <MultiSelect
                        v-model="form.buy_products"
                        input-id="reward-buy-products"
                        :aria-label="$t('rewards_eligible')"
                        :options="products"
                        option-label="name"
                        option-value="public_id"
                        filter
                        :filter-input-props="{ 'aria-label': $t('rewards_eligible') }"
                        :selection-limit="50"
                        :max-selected-labels="2"
                    />
                </ZFFormField>
                <ZFFormField
                    for="reward-buy-quantity"
                    :label="$t('rewards_buy_quantity')"
                    :help="$t('rewards_help_buy_quantity')"
                >
                    <input
                        id="reward-buy-quantity"
                        v-model.number="form.buy_quantity"
                        type="number"
                        min="1"
                        max="100"
                        class="reward-input w-full"
                        required
                    />
                </ZFFormField>
            </div>
            <div class="grid items-start gap-4 sm:grid-cols-[1fr_8rem]">
                <ZFFormField for="reward-product" :label="$t('rewards_product')" :help="$t('rewards_help_product')">
                    <Select
                        v-model="form.reward_product"
                        input-id="reward-product"
                        :aria-label="$t('rewards_product')"
                        :options="products"
                        option-label="name"
                        option-value="public_id"
                        filter
                        :filter-input-props="{ 'aria-label': $t('rewards_product') }"
                    />
                </ZFFormField>
                <ZFFormField
                    for="reward-quantity"
                    :label="$t('rewards_free_quantity')"
                    :help="$t('rewards_help_free_quantity')"
                >
                    <input
                        id="reward-quantity"
                        v-model.number="form.reward_quantity"
                        type="number"
                        min="1"
                        max="50"
                        class="reward-input w-full"
                        required
                    />
                </ZFFormField>
            </div>
        </template>
        <div class="grid gap-4 sm:grid-cols-2">
            <ZFFormField for="reward-start" :label="$t('rewards_start')" :help="$t('rewards_help_start')">
                <input id="reward-start" v-model="form.starts_on" type="date" class="reward-input w-full" />
            </ZFFormField>
            <ZFFormField for="reward-end" :label="$t('rewards_end')" :help="$t('rewards_help_end')">
                <input
                    id="reward-end"
                    v-model="form.ends_on"
                    type="date"
                    :min="form.starts_on || undefined"
                    class="reward-input w-full"
                />
            </ZFFormField>
        </div>
        <ZFFormField for="reward-active" :label="$t('rewards_enabled')" :help="$t('rewards_help_enabled')">
            <input id="reward-active" v-model="form.active" type="checkbox" class="size-5 accent-brand" />
        </ZFFormField>
        <p v-if="reward" class="rounded-xl bg-green-50 p-3 font-medium text-green-900">
            {{ $t('rewards_rule', { buy: form.buy_quantity, quantity: form.reward_quantity, reward }) }}
        </p>
        <p class="muted text-sm">{{ $t('rewards_fixed_rules') }}</p>
        <div>
            <Button type="submit" :disabled="!valid" :loading="busy" icon="pi pi-check" :label="$t('rewards_save')" />
        </div>
    </form>
</template>
