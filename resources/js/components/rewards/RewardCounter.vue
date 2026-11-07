<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref } from 'vue';
import Button from 'primevue/button';
import Select from 'primevue/select';
import ZFFormField from '@/components/ui/ZFFormField.vue';
import RewardQr from '@/components/rewards/RewardQr.vue';
import RewardScanner from '@/components/rewards/RewardScanner.vue';
import { tr as t } from '@/i18n';
import { api } from '@/lib/http';
import type { RewardCampaign, RewardCode } from '@/lib/rewards';

interface Pending {
    id: number;
    campaign: string;
    name: string;
}
const props = defineProps<{ base: string; walletUrl: string; campaigns: RewardCampaign[] }>();
const endpoint = props.base + '/counter/';
const pending = ref<Pending[]>([]);
const selection = ref<Pending>();
const venueCampaign = ref('');
const venue = ref<RewardCode>();
const code = ref('');
const inspected = ref('');
const card = ref<RewardCampaign[]>([]);
const campaignId = ref('');
const quantity = ref(1);
const receipt = ref('');
const error = ref('');
const notice = ref('');
const busy = ref(false);
const venueCampaigns = computed(() =>
    props.campaigns.filter((campaign) => campaign.current && campaign.mode === 'venue_qr'),
);
const customerCampaigns = computed(() =>
    card.value.filter((campaign) => campaign.current && campaign.mode === 'customer_qr'),
);
const claimable = computed(() => card.value.filter((campaign) => campaign.available > 0));
const selectedCampaign = computed(() => props.campaigns.find((campaign) => campaign.public_id === campaignId.value));
const walletUrl = computed(() => props.walletUrl + '#join=' + venue.value?.code);
const mayConfirm = computed(
    () =>
        (selection.value || inspected.value) &&
        campaignId.value &&
        quantity.value >= 1 &&
        quantity.value <= 100 &&
        receipt.value.trim(),
);
let poll: ReturnType<typeof setInterval>;

async function loadPending() {
    const result = await api<{ pending: Pending[] }>(endpoint + 'pending', 'POST');
    pending.value = result.pending;
}

onMounted(() => {
    if (!venueCampaigns.value.length) return;
    void loadPending().catch(() => {});
    poll = setInterval(() => {
        if (!document.hidden && !busy.value) void loadPending().catch(() => {});
    }, 7000);
});
onUnmounted(() => clearInterval(poll));

async function run(task: () => Promise<void>) {
    if (busy.value) return;
    error.value = '';
    notice.value = '';
    busy.value = true;
    try {
        await task();
    } catch (exception) {
        error.value = (exception as Error).message;
    } finally {
        busy.value = false;
    }
}

function resetPurchase() {
    campaignId.value = '';
    quantity.value = 1;
    receipt.value = '';
}

function lookup(value = code.value) {
    const normalized = value.trim().toUpperCase();
    if (!/^[A-Z0-9]{12}$/.test(normalized)) return;
    code.value = normalized;
    inspected.value = '';
    card.value = [];
    selection.value = undefined;
    resetPurchase();
    void run(async () => {
        const result = await api<{ campaigns: RewardCampaign[] }>(endpoint + 'inspect', 'POST', { code: normalized });
        inspected.value = normalized;
        card.value = result.campaigns;
        if (customerCampaigns.value.length === 1) campaignId.value = customerCampaigns.value[0].public_id;
        if (!customerCampaigns.value.length && !claimable.value.length) notice.value = t('rewards_card_no_action');
    });
}

function choose(item: Pending) {
    selection.value = item;
    inspected.value = '';
    card.value = [];
    resetPurchase();
    campaignId.value = item.campaign;
}

function confirm() {
    if (!mayConfirm.value) return;
    void run(async () => {
        await api(endpoint + 'confirm', 'POST', {
            ...(selection.value ? { pending: selection.value.id } : { code: inspected.value }),
            campaign: campaignId.value,
            quantity: quantity.value,
            receipt: receipt.value,
        });
        pending.value = pending.value.filter((item) => item.id !== selection.value?.id);
        selection.value = undefined;
        inspected.value = '';
        code.value = '';
        card.value = [];
        resetPurchase();
        notice.value = t('rewards_purchase_saved');
    });
}

function redeem(campaign: RewardCampaign) {
    void run(async () => {
        await api(endpoint + 'redeem', 'POST', { code: inspected.value, campaign: campaign.public_id });
        inspected.value = '';
        code.value = '';
        card.value = [];
        resetPurchase();
        notice.value = t('rewards_redeemed', { quantity: campaign.reward_quantity, reward: campaign.reward_name });
    });
}
</script>

<template>
    <div class="grid gap-5 lg:grid-cols-2">
        <div v-if="error || notice" aria-live="polite" class="lg:col-span-2">
            <p v-if="error" role="alert" class="rounded-xl bg-red-50 p-3 text-red-800">{{ error }}</p>
            <p v-if="notice" class="rounded-xl bg-green-50 p-3 text-green-900">{{ notice }}</p>
        </div>
        <section class="panel grid content-start gap-4">
            <h2 class="text-xl font-semibold">
                <i class="pi pi-qrcode mr-2 text-brand" aria-hidden="true" />{{ $t('rewards_customer_scan') }}
            </h2>
            <p class="muted text-sm">{{ $t('rewards_customer_scan_help') }}</p>
            <RewardScanner @scan="lookup" />
            <form class="grid gap-2" @submit.prevent="lookup()">
                <ZFFormField for="rewards-card-code" :label="$t('rewards_code')" :help="$t('rewards_help_code')">
                    <input
                        id="rewards-card-code"
                        v-model="code"
                        class="reward-input w-full uppercase"
                        maxlength="12"
                        autocomplete="off"
                        :disabled="busy"
                        @input="
                            inspected = '';
                            card = [];
                            resetPurchase();
                            selection = undefined;
                        "
                    />
                </ZFFormField>
                <div>
                    <Button
                        type="submit"
                        :disabled="code.trim().length !== 12"
                        :loading="busy"
                        :label="$t('rewards_find_card')"
                        outlined
                    />
                </div>
            </form>
            <div v-if="claimable.length" class="grid gap-2 border-t border-slate-200 pt-4 dark:border-slate-700">
                <h3 class="font-semibold">{{ $t('rewards_handover') }}</h3>
                <article
                    v-for="item in claimable"
                    :key="item.public_id"
                    class="flex flex-wrap items-center justify-between gap-2"
                >
                    <div>
                        <p class="font-medium">{{ item.reward_quantity }} × {{ item.reward_name }}</p>
                        <small class="muted"
                            >{{ item.name }} · {{ $t('rewards_ready', { count: item.available }) }}</small
                        >
                    </div>
                    <Button
                        size="small"
                        :label="$t('rewards_redeem')"
                        :loading="busy"
                        severity="success"
                        @click="redeem(item)"
                    />
                </article>
            </div>
        </section>
        <section v-if="venueCampaigns.length" class="panel grid content-start gap-4">
            <h2 class="text-xl font-semibold">
                <i class="pi pi-map-marker mr-2 text-brand" aria-hidden="true" />{{ $t('rewards_venue_ticket') }}
            </h2>
            <p class="muted text-sm">{{ $t('rewards_desc_venue_qr') }}</p>
            <ZFFormField
                for="rewards-venue-campaign"
                :label="$t('rewards_campaign')"
                :help="$t('rewards_help_venue_campaign')"
            >
                <Select
                    v-model="venueCampaign"
                    input-id="rewards-venue-campaign"
                    :aria-label="$t('rewards_campaign')"
                    :options="venueCampaigns"
                    option-label="name"
                    option-value="public_id"
                    @change="venue = undefined"
                />
            </ZFFormField>
            <div>
                <Button
                    :disabled="!venueCampaign"
                    :loading="busy"
                    :label="$t('rewards_generate_qr')"
                    icon="pi pi-qrcode"
                    @click="
                        run(async () => {
                            venue = await api<RewardCode>(endpoint + 'venue', 'POST', { campaign: venueCampaign });
                        })
                    "
                />
            </div>
            <RewardQr v-if="venue" :ticket="venue" :payload="walletUrl" />
            <div class="grid gap-2 border-t border-slate-200 pt-4 dark:border-slate-700">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <h3 class="font-semibold">{{ $t('rewards_pending') }}</h3>
                    <Button
                        size="small"
                        text
                        icon="pi pi-refresh"
                        :label="$t('rewards_refresh')"
                        :disabled="busy"
                        @click="run(loadPending)"
                    />
                </div>
                <p v-if="!pending.length" class="muted text-sm">{{ $t('rewards_pending_empty') }}</p>
                <Button
                    v-for="item in pending"
                    :key="item.id"
                    :disabled="busy"
                    :label="item.name + ' · #' + item.id"
                    :outlined="item.id !== selection?.id"
                    size="small"
                    @click="choose(item)"
                />
            </div>
        </section>
        <section v-if="selection || customerCampaigns.length" class="panel grid gap-4 lg:col-span-2">
            <h2 class="text-xl font-semibold">
                {{ $t('rewards_confirm_purchase') }} <small v-if="selection" class="muted">#{{ selection.id }}</small>
            </h2>
            <form class="grid gap-4" @submit.prevent="confirm">
                <ZFFormField
                    v-if="!selection"
                    for="rewards-credit-campaign"
                    :label="$t('rewards_campaign')"
                    :help="$t('rewards_help_credit_campaign')"
                >
                    <Select
                        v-model="campaignId"
                        input-id="rewards-credit-campaign"
                        :aria-label="$t('rewards_campaign')"
                        :options="customerCampaigns"
                        option-label="name"
                        option-value="public_id"
                    />
                </ZFFormField>
                <p v-if="selectedCampaign" class="muted text-sm">
                    {{ selectedCampaign.name }} · {{ selectedCampaign.buy_products.join(' · ') }}
                </p>
                <div class="grid gap-4 sm:grid-cols-[10rem_1fr]">
                    <ZFFormField
                        for="rewards-credit-qty"
                        :label="$t('rewards_purchased')"
                        :help="$t('rewards_help_purchased')"
                    >
                        <input
                            id="rewards-credit-qty"
                            v-model.number="quantity"
                            class="reward-input w-full"
                            type="number"
                            min="1"
                            max="100"
                            required
                        />
                    </ZFFormField>
                    <ZFFormField
                        for="rewards-receipt"
                        :label="$t('rewards_receipt')"
                        :help="$t('rewards_help_receipt')"
                    >
                        <input
                            id="rewards-receipt"
                            v-model="receipt"
                            class="reward-input w-full"
                            maxlength="80"
                            required
                        />
                    </ZFFormField>
                </div>
                <div>
                    <Button
                        type="submit"
                        :disabled="!mayConfirm"
                        :loading="busy"
                        :label="$t('rewards_credit')"
                        icon="pi pi-check"
                    />
                </div>
            </form>
        </section>
    </div>
</template>
