<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import Button from 'primevue/button';
import StorefrontLayout from '@/layouts/StorefrontLayout.vue';
import RewardQr from '@/components/rewards/RewardQr.vue';
import { api } from '@/lib/http';
import { tr as t } from '@/i18n';
import { readRewardCard, saveRewardCard, type RewardCampaign, type RewardCode } from '@/lib/rewards';

const props = defineProps<{
    locationId: string;
    locationName: string;
    restaurant: string;
    menuPath: string;
    campaigns: RewardCampaign[];
}>();
const campaigns = ref(props.campaigns);
const token = ref('');
const code = ref<RewardCode>();
const joinCode = ref('');
const busy = ref(false);
const error = ref('');
const notice = ref('');
const ephemeral = ref(false);
const available = computed(() => campaigns.value.reduce((sum, campaign) => sum + campaign.available, 0));
const base = '/rewards/' + props.locationId;

async function request(action: string, task: () => Promise<unknown>) {
    if (busy.value) return;
    busy.value = true;
    error.value = '';
    if (action !== 'view') notice.value = '';
    try {
        await task();
    } catch (exception) {
        error.value = (exception as Error).message;
    } finally {
        busy.value = false;
    }
}

async function sync(action = 'view', extra: Record<string, string> = {}) {
    const result = await api<{ campaigns: RewardCampaign[]; reference: number | null }>(base + '/' + action, 'POST', {
        token: token.value,
        ...extra,
    });
    campaigns.value = result.campaigns;
    return result.reference;
}

function openCard() {
    void request('create', async () => {
        const result = await api<{ token: string }>(base + '/create', 'POST', {});
        token.value = result.token;
        ephemeral.value = !saveRewardCard(props.locationId, token.value);
        await sync();
    });
}

function showCode() {
    void request('code', async () => {
        code.value = undefined;
        code.value = await api<RewardCode>(base + '/code', 'POST', { token: token.value });
    });
}

function join() {
    void request('join', async () => {
        const reference = await sync('join', { code: joinCode.value });
        joinCode.value = '';
        window.history.replaceState(window.history.state, '', window.location.pathname + window.location.search);
        if (reference !== null) notice.value = t('rewards_join_sent', { reference });
    });
}

onMounted(() => {
    const match = window.location.hash.match(/^#join=([A-Z0-9]{12})$/);
    if (match) joinCode.value = match[1];
    token.value = readRewardCard(props.locationId);
    if (token.value) void request('view', sync);
});
</script>

<template>
    <Head :title="$t('rewards_title') + ' · ' + restaurant" />
    <StorefrontLayout :name="restaurant">
        <template #actions>
            <Link :href="menuPath" class="text-sm font-semibold text-brand">{{ $t('rewards_back_menu') }}</Link>
        </template>
        <div class="overflow-hidden rounded-3xl bg-[#172920] px-6 py-9 text-white md:px-10">
            <p class="text-sm font-semibold tracking-widest text-lime-200 uppercase">{{ locationName }}</p>
            <h1 class="mt-2 text-4xl font-semibold tracking-tight md:text-5xl">{{ $t('rewards_title') }}</h1>
            <p class="mt-3 max-w-xl text-sm leading-relaxed text-green-100 md:text-base">
                {{ $t('rewards_wallet_intro') }}
            </p>
            <p
                v-if="token"
                class="mt-5 inline-flex gap-2 rounded-full bg-lime-200 px-4 py-2 text-sm font-bold text-[#172920]"
            >
                <i class="pi pi-gift" aria-hidden="true" />{{ $t('rewards_ready', { count: available }) }}
            </p>
        </div>
        <div class="my-5 grid gap-3" aria-live="polite">
            <p v-if="error" role="alert" class="rounded-xl bg-red-50 p-3 text-red-800">{{ error }}</p>
            <p v-if="notice" class="rounded-xl bg-green-50 p-3 text-green-900">{{ notice }}</p>
            <p v-if="ephemeral" class="rounded-xl bg-amber-50 p-3 text-amber-900">{{ $t('rewards_storage_error') }}</p>
        </div>
        <div class="grid items-start gap-5 lg:grid-cols-[1fr_300px]">
            <div class="grid gap-4">
                <article v-for="campaign in campaigns" :key="campaign.public_id" class="panel grid gap-3">
                    <div class="flex flex-wrap items-baseline justify-between gap-2">
                        <h2 class="text-xl font-semibold">{{ campaign.name }}</h2>
                        <span class="muted text-sm">{{ $t('rewards_mode_' + campaign.mode) }}</span>
                    </div>
                    <p class="text-lg font-semibold text-brand">
                        {{
                            $t('rewards_rule', {
                                buy: campaign.buy_quantity,
                                quantity: campaign.reward_quantity,
                                reward: campaign.reward_name,
                            })
                        }}
                    </p>
                    <p v-if="campaign.buy_products.length" class="muted text-sm">
                        {{ campaign.buy_products.join(' · ') }}
                    </p>
                    <template v-if="token">
                        <label :for="'progress-' + campaign.public_id" class="text-sm">{{
                            $t('rewards_progress', { count: campaign.progress, total: campaign.buy_quantity })
                        }}</label>
                        <progress
                            :id="'progress-' + campaign.public_id"
                            class="h-3 w-full accent-green-700"
                            :value="campaign.progress"
                            :max="campaign.buy_quantity"
                        />
                        <p v-if="campaign.available" class="font-semibold text-brand">
                            {{ $t('rewards_ready', { count: campaign.available }) }}
                        </p>
                    </template>
                    <p v-if="!campaign.current" class="muted text-sm">{{ $t('rewards_inactive_wallet') }}</p>
                    <p v-else-if="campaign.ends_on" class="muted text-sm">
                        {{ $t('rewards_until', { date: campaign.ends_on }) }}
                    </p>
                </article>
                <p v-if="!campaigns.length" class="panel muted">{{ $t('rewards_empty') }}</p>
            </div>
            <aside class="panel grid gap-3">
                <h2 class="text-xl font-semibold">{{ $t('rewards_card') }}</h2>
                <template v-if="token">
                    <p class="muted text-sm">{{ $t('rewards_card_help') }}</p>
                    <Button
                        v-if="joinCode"
                        :loading="busy"
                        :label="$t('rewards_join')"
                        icon="pi pi-check"
                        @click="join"
                    />
                    <Button :loading="busy" :label="$t('rewards_show_qr')" icon="pi pi-qrcode" @click="showCode" />
                    <RewardQr v-if="code" :ticket="code" :payload="'ZFR:' + code.code" />
                    <Button
                        :disabled="busy"
                        :label="$t('rewards_refresh')"
                        icon="pi pi-refresh"
                        text
                        @click="request('view', sync)"
                    />
                </template>
                <template v-else>
                    <p class="muted text-sm">{{ $t('rewards_card_private') }}</p>
                    <Button
                        v-if="campaigns.length"
                        :loading="busy"
                        :label="$t('rewards_create_card')"
                        icon="pi pi-plus"
                        @click="openCard"
                    />
                </template>
            </aside>
        </div>
    </StorefrontLayout>
</template>
