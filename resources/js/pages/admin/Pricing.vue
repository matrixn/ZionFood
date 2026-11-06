<script setup lang="ts">
import { ref } from 'vue';
import { Head } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import { useToast } from 'primevue/usetoast';
import Toast from 'primevue/toast';
import InputText from 'primevue/inputtext';
import ToggleSwitch from 'primevue/toggleswitch';
import Button from 'primevue/button';
import AdminLayout from '@/layouts/AdminLayout.vue';
import ZFFormField from '@/components/ui/ZFFormField.vue';
import { api } from '@/lib/http';
import type { PricingPlan } from '@/lib/pricing';
interface AdminPricing {
    plans: PricingPlan[];
    settings: {
        version: number;
        weekly_enabled: boolean;
        last_attempt_at: string | null;
        last_success_at: string | null;
        last_error: string | null;
    };
    rates: { currency: string; rate_scaled: number; rate_date: string; fetched_at: string }[];
}
interface GeoIpStatus {
    ready: boolean;
    built_at: string | null;
    stale: boolean;
}
const props = defineProps<{ pricing: AdminPricing; geoip: GeoIpStatus }>();
const geoip = ref(props.geoip);
const { t } = useI18n();
const toast = useToast();
const state = ref(props.pricing);
const plans = ref(
    props.pricing.plans.map((p) => ({ key: p.key, name: p.name, price_ron: (p.price_minor / 100).toFixed(2) })),
);
const weekly = ref(!!props.pricing.settings.weekly_enabled);
const busy = ref(false);
const error = ref('');
async function save() {
    busy.value = true;
    error.value = '';
    try {
        const normalized = plans.value.map((p) => ({ ...p, price_ron: p.price_ron.replace(',', '.') }));
        state.value = await api<AdminPricing>('/admin/pricing', 'PUT', {
            plans: normalized,
            weekly_enabled: weekly.value,
            version: state.value.settings.version,
        });
        toast.add({ severity: 'success', summary: t('pricing_saved'), life: 4000 });
    } catch (e) {
        error.value = (e as Error).message;
    } finally {
        busy.value = false;
    }
}
async function sync() {
    busy.value = true;
    error.value = '';
    try {
        const result = await api<{ synced: boolean; pricing: AdminPricing }>('/admin/pricing/rates', 'POST');
        state.value = {
            ...result.pricing,
            settings: { ...result.pricing.settings, version: state.value.settings.version },
        };
        toast.add({
            severity: result.synced ? 'success' : 'warn',
            summary: t(result.synced ? 'pricing_sync_done' : 'pricing_sync_failed'),
            life: 6000,
        });
    } catch (e) {
        error.value = (e as Error).message;
    } finally {
        busy.value = false;
    }
}
async function updateGeoIp() {
    busy.value = true;
    error.value = '';
    try {
        const result = await api<{ updated: boolean; geoip: GeoIpStatus }>('/admin/pricing/geoip', 'POST');
        geoip.value = result.geoip;
        toast.add({
            severity: result.updated ? 'success' : 'warn',
            summary: t(result.updated ? 'geoip_updated' : 'geoip_update_failed'),
            life: 6000,
        });
    } catch (e) {
        error.value = (e as Error).message;
    } finally {
        busy.value = false;
    }
}
</script>
<template>
    <AdminLayout
        ><Head :title="$t('pa_pricing')" /><Toast />
        <div class="mx-auto max-w-5xl space-y-6">
            <div>
                <p class="text-xs font-semibold uppercase tracking-widest text-brand">RON · EUR · GBP</p>
                <h1 class="mt-2 text-3xl font-semibold">{{ $t('pa_pricing') }}</h1>
                <p class="muted mt-3 max-w-3xl">{{ $t('pricing_admin_intro') }}</p>
            </div>
            <form class="panel space-y-6" @submit.prevent="save">
                <div class="grid gap-6 md:grid-cols-3">
                    <section
                        v-for="plan in plans"
                        :key="plan.key"
                        class="rounded-2xl border border-slate-200 p-5 dark:border-slate-700"
                        :data-plan-editor="plan.key"
                    >
                        <p class="mb-5 text-xs font-semibold uppercase tracking-wider text-slate-500">{{ plan.key }}</p>
                        <ZFFormField
                            :for="'plan-name-' + plan.key"
                            :label="$t('pricing_plan_name')"
                            :help="$t('pricing_name_help')"
                            ><InputText
                                :id="'plan-name-' + plan.key"
                                v-model="plan.name"
                                :aria-label="$t('pricing_plan_name') + ' ' + plan.key"
                                required
                                maxlength="80"
                                class="w-full"
                        /></ZFFormField>
                        <ZFFormField
                            :for="'plan-price-' + plan.key"
                            :label="$t('pricing_base_price')"
                            :help="$t('pricing_price_help')"
                            ><InputText
                                :id="'plan-price-' + plan.key"
                                v-model="plan.price_ron"
                                :aria-label="$t('pricing_base_price') + ' ' + plan.key"
                                inputmode="decimal"
                                required
                                pattern="(?:0|[1-9][0-9]{0,5})(?:[.,][0-9]{1,2})?"
                                class="w-full"
                        /></ZFFormField>
                    </section>
                </div>
                <label class="flex items-center gap-3"
                    ><ToggleSwitch v-model="weekly" :aria-label="$t('pricing_weekly')" /><span>{{
                        $t('pricing_weekly')
                    }}</span
                    ><FieldHelp :label="$t('pricing_weekly')" :text="$t('pricing_weekly_help')"
                /></label>
                <p v-if="error" role="alert" class="rounded-xl bg-red-50 p-3 text-sm text-red-800">{{ error }}</p>
                <Button type="submit" :label="$t('save')" icon="pi pi-check" :loading="busy" :disabled="busy" />
            </form>
            <section class="panel space-y-5">
                <div class="flex flex-wrap items-center justify-between gap-4">
                    <div>
                        <h2 class="text-xl font-semibold">{{ $t('pricing_exchange_rates') }}</h2>
                        <p class="muted mt-2">
                            {{ $t('pricing_provider_note') }}
                            <a
                                href="https://frankfurter.dev/"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="text-brand underline"
                                >Frankfurter</a
                            >
                        </p>
                    </div>
                    <Button
                        :label="$t('pricing_sync')"
                        icon="pi pi-refresh"
                        :loading="busy"
                        :disabled="busy"
                        @click="sync"
                    />
                </div>
                <p
                    v-if="state.settings.last_error"
                    role="status"
                    class="rounded-xl bg-amber-50 p-3 text-sm text-amber-900"
                >
                    {{ $t('pricing_sync_failed') }}
                </p>
                <p v-if="!state.rates.length" class="muted">{{ $t('pricing_no_rates') }}</p>
                <div class="grid gap-4 sm:grid-cols-2">
                    <article
                        v-for="rate in state.rates"
                        :key="rate.currency"
                        class="rounded-xl bg-slate-50 p-4 dark:bg-slate-800"
                    >
                        <strong>1 RON = {{ (rate.rate_scaled / 100000000).toFixed(8) }} {{ rate.currency }}</strong>
                        <p class="muted mt-2">{{ $t('pricing_rate_date', { date: rate.rate_date }) }}</p>
                    </article>
                </div>
                <p class="text-xs text-slate-500">
                    {{ $t('pricing_last_sync') }}: {{ state.settings.last_success_at ?? '—' }} UTC
                </p>
            </section>
            <section class="panel space-y-4">
                <div class="flex flex-wrap items-center justify-between gap-4">
                    <h2 class="text-xl font-semibold">{{ $t('geoip_heading') }}</h2>
                    <Button
                        :label="$t('geoip_update')"
                        icon="pi pi-globe"
                        :loading="busy"
                        :disabled="busy"
                        @click="updateGeoIp"
                    />
                </div>
                <p class="muted">{{ $t('geoip_admin_help') }}</p>
                <p role="status" :class="geoip.ready && !geoip.stale ? 'text-emerald-700' : 'text-amber-700'">
                    {{ $t(geoip.ready ? 'geoip_ready' : 'geoip_missing') }}
                    <span v-if="geoip.built_at">{{ geoip.built_at }} UTC</span
                    ><span v-if="geoip.stale"> · {{ $t('geoip_stale') }}</span>
                </p>
                <a
                    href="https://db-ip.com"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="text-xs text-brand underline"
                    >IP Geolocation by DB-IP</a
                >
            </section>
        </div>
    </AdminLayout>
</template>
