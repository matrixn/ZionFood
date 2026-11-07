<script setup lang="ts">
import { computed, ref } from 'vue';
import { Head } from '@inertiajs/vue3';
import Button from 'primevue/button';
import ZFShell from '@/components/ui/ZFShell.vue';
import RewardCampaignForm from '@/components/rewards/RewardCampaignForm.vue';
import RewardCounter from '@/components/rewards/RewardCounter.vue';
import { api } from '@/lib/http';
import { tr as t } from '@/i18n';
import type { RewardCampaign } from '@/lib/rewards';
import type { WorkspaceProps } from '@/types';

const props = defineProps<
    WorkspaceProps & { campaigns: RewardCampaign[]; walletUrl: string; products: { public_id: string; name: string }[] }
>();
const base = '/app/locations/' + props.location.public_id + '/rewards';
const campaigns = ref(props.campaigns);
const tab = ref<'counter' | 'campaigns'>(props.canManage ? 'campaigns' : 'counter');
const editor = ref<'new' | RewardCampaign>();
const busy = ref(false);
const error = ref('');
const notice = ref('');
const live = computed(() => campaigns.value.filter((campaign) => campaign.current).length);
async function save(values: Record<string, unknown>) {
    if (!editor.value || busy.value) return;
    busy.value = true;
    error.value = '';
    notice.value = '';
    try {
        const id = editor.value === 'new' ? '' : '/' + editor.value.public_id;
        const result = await api<{ campaigns: RewardCampaign[] }>(base + '/campaigns' + id, 'POST', values);
        campaigns.value = result.campaigns;
        editor.value = undefined;
        notice.value = t('rewards_saved');
    } catch (exception) {
        error.value = (exception as Error).message;
    } finally {
        busy.value = false;
    }
}
</script>

<template>
    <ZFShell :location="location" :locations="locations" :can-manage="canManage" :permissions="permissions">
        <Head :title="$t('rewards_title')" />
        <header class="rounded-3xl bg-[#172920] px-6 py-7 text-white md:px-9">
            <p class="text-sm font-semibold tracking-widest text-lime-200 uppercase">{{ location.name }}</p>
            <h1 class="mt-2 text-4xl font-semibold tracking-tight">{{ $t('rewards_title') }}</h1>
            <p class="mt-2 max-w-2xl text-sm leading-relaxed text-green-100">{{ $t('rewards_admin_intro') }}</p>
            <div class="mt-5 flex flex-wrap items-center gap-3">
                <span class="rounded-full bg-lime-200 px-4 py-2 text-sm font-bold text-[#172920]">{{
                    $t('rewards_live_count', { count: live })
                }}</span>
                <a
                    :href="walletUrl"
                    class="rounded-full border border-green-100/30 px-4 py-2 text-sm text-white hover:bg-white/10"
                >
                    {{ $t('rewards_view_wallet') }} <i class="pi pi-arrow-up-right ml-1" aria-hidden="true" />
                </a>
            </div>
        </header>
        <div v-if="canManage" class="my-5 flex flex-wrap gap-2" :aria-label="$t('rewards_title')" role="group">
            <Button
                :outlined="tab !== 'campaigns'"
                :aria-pressed="tab === 'campaigns'"
                icon="pi pi-list"
                :label="$t('rewards_campaigns')"
                @click="tab = 'campaigns'"
            />
            <Button
                :outlined="tab !== 'counter'"
                :aria-pressed="tab === 'counter'"
                icon="pi pi-qrcode"
                :label="$t('rewards_counter')"
                @click="tab = 'counter'"
            />
        </div>
        <RewardCounter
            v-if="tab === 'counter'"
            :base="base"
            :wallet-url="walletUrl"
            :campaigns="campaigns"
            class="mt-5"
        />
        <section v-else class="grid items-start gap-5" :class="editor ? 'xl:grid-cols-2' : ''">
            <div class="grid gap-4">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <h2 class="text-xl font-semibold">{{ $t('rewards_campaigns') }}</h2>
                    <Button
                        :disabled="!products.length || busy"
                        :label="$t('rewards_new')"
                        icon="pi pi-plus"
                        @click="
                            editor = 'new';
                            error = '';
                            notice = '';
                        "
                    />
                </div>
                <p v-if="notice" role="status" class="rounded-xl bg-green-50 p-3 text-green-900">{{ notice }}</p>
                <p v-if="!products.length" class="muted text-sm">{{ $t('rewards_need_products') }}</p>
                <p v-if="!campaigns.length" class="panel muted">{{ $t('rewards_empty_admin') }}</p>
                <div class="grid gap-4" :class="editor ? '' : 'md:grid-cols-2 2xl:grid-cols-3'">
                    <article v-for="item in campaigns" :key="item.public_id" class="panel grid gap-3">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <h3 class="text-lg font-semibold">{{ item.name }}</h3>
                            <span
                                class="rounded-full px-3 py-1 text-xs font-semibold"
                                :class="item.current ? 'bg-green-100 text-green-900' : 'bg-slate-100 text-slate-700'"
                            >
                                {{
                                    $t(
                                        item.current
                                            ? 'rewards_live'
                                            : item.active
                                              ? 'rewards_outside_dates'
                                              : 'rewards_paused',
                                    )
                                }}
                            </span>
                        </div>
                        <p class="font-semibold text-brand">
                            {{
                                $t('rewards_rule', {
                                    buy: item.buy_quantity,
                                    quantity: item.reward_quantity,
                                    reward: item.reward_name,
                                })
                            }}
                        </p>
                        <p class="muted text-sm">{{ item.buy_products.join(' · ') }}</p>
                        <p class="muted text-sm">
                            {{ $t('rewards_mode_' + item.mode)
                            }}<template v-if="item.ends_on">
                                · {{ $t('rewards_until', { date: item.ends_on }) }}</template
                            >
                        </p>
                        <div>
                            <Button
                                size="small"
                                outlined
                                :disabled="busy"
                                :label="$t('rewards_edit')"
                                @click="
                                    editor = item;
                                    error = '';
                                    notice = '';
                                "
                            />
                        </div>
                    </article>
                </div>
            </div>
            <div v-if="editor" class="panel grid gap-4 max-xl:row-start-1">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <h2 class="text-xl font-semibold">{{ $t(editor === 'new' ? 'rewards_new' : 'rewards_edit') }}</h2>
                    <Button
                        size="small"
                        text
                        icon="pi pi-times"
                        :label="$t('rewards_close_editor')"
                        :disabled="busy"
                        @click="
                            editor = undefined;
                            error = '';
                        "
                    />
                </div>
                <p v-if="error" role="alert" class="rounded-xl bg-red-50 p-3 text-red-800">{{ error }}</p>
                <RewardCampaignForm
                    :key="editor === 'new' ? 'new' : editor.public_id"
                    :campaign="editor === 'new' ? undefined : editor"
                    :products="products"
                    :busy="busy"
                    @save="save"
                />
            </div>
        </section>
    </ZFShell>
</template>
