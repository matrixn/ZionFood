<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { onBeforeUnmount, onMounted, ref, watch } from 'vue';
import Select from 'primevue/select';
import { api } from '@/lib/http';
import { defaultPricing, visitorPreferences, type Currency, type PricingData } from '@/lib/pricing';
import { i18n, localeTag, localePath } from '@/i18n';

const props = defineProps<{
    signedIn: boolean;
    pricing?: PricingData;
    visitorDefaults?: { detectLocale: boolean; detectCurrency: boolean };
}>();
const state = ref<PricingData>(props.pricing ?? defaultPricing);
const selection = ref<Currency>(state.value.currency);
const busy = ref(false);
const error = ref('');
let manualLanguage = false;
let disposed = false;
const languageChosen = () => {
    manualLanguage = true;
};
onBeforeUnmount(() => {
    disposed = true;
    window.removeEventListener('zf-language-choice', languageChosen);
});
watch(
    () => props.pricing,
    (value) => {
        if (value) {
            state.value = value;
            selection.value = value.currency;
        }
    },
);
const currencies = ['RON', 'EUR', 'GBP'];
const amount = (minor: number) =>
    new Intl.NumberFormat(localeTag(), { minimumFractionDigits: minor % 100 ? 2 : 0, maximumFractionDigits: 2 }).format(
        minor / 100,
    );
async function change(currency: Currency) {
    if (busy.value) return;
    busy.value = true;
    error.value = '';
    try {
        state.value = await api<PricingData>('/pricing/preference', 'POST', { currency });
        selection.value = state.value.currency;
    } catch {
        selection.value = state.value.currency;
        error.value = i18n.global.t('request_failed');
    } finally {
        busy.value = false;
    }
}
onMounted(async () => {
    window.addEventListener('zf-language-choice', languageChosen);
    const detect = props.visitorDefaults;
    if (!detect?.detectLocale && !detect?.detectCurrency) return;
    const preference = visitorPreferences(navigator.languages, Intl.DateTimeFormat().resolvedOptions().timeZone);
    if (detect.detectCurrency) await change(preference.currency);
    if (detect.detectLocale && !manualLanguage && !disposed && preference.locale !== i18n.global.locale.value) {
        try {
            await api('/locale', 'POST', { locale: preference.locale });
            window.location.replace(localePath(preference.locale) + window.location.search + window.location.hash);
        } catch {
            /* Keep the readable explicit URL when preferences cannot be saved. */
        }
    }
});
</script>

<template>
    <section id="pricing" class="pricing-section section-wrap" aria-labelledby="pricing-title">
        <div class="pricing-intro">
            <p class="pricing-eyebrow"><span aria-hidden="true" />{{ $t('pricing_eyebrow') }}</p>
            <h2 id="pricing-title">
                {{ $t('pricing_title') }} <em>{{ $t('pricing_title_accent') }}</em>
            </h2>
            <p class="pricing-description">{{ $t('pricing_description') }}</p>
            <div class="pricing-currency-control">
                <label for="pricing-currency"
                    >{{ $t('pricing_display_currency') }}
                    <FieldHelp :label="$t('pricing_display_currency')" :text="$t('pricing_currency_help')"
                /></label>
                <Select
                    v-model="selection"
                    input-id="pricing-currency"
                    :options="currencies"
                    :aria-label="$t('pricing_display_currency')"
                    :disabled="busy"
                    :loading="busy"
                    data-testid="currency-selector"
                    @update:model-value="change"
                />
            </div>
            <p class="pricing-quote-note" role="status">
                {{ $t('pricing_invoice_note') }}
                <span v-if="state.rate_date">{{ $t('pricing_rate_date', { date: state.rate_date }) }}</span>
                <span v-if="state.requested_currency !== state.currency">{{ $t('pricing_rates_missing') }}</span>
                <span v-if="state.stale">{{ $t('pricing_rates_stale') }}</span>
            </p>
            <p v-if="error" role="alert" class="text-sm text-red-700">{{ error }}</p>
        </div>
        <div class="pricing-grid">
            <article
                v-for="plan in state.plans"
                :key="plan.key"
                class="pricing-card"
                :class="{ 'pricing-card-popular': plan.key === 'orders' }"
                :aria-labelledby="'pricing-' + plan.key"
                :data-plan="plan.key"
            >
                <span v-if="plan.key === 'orders'" class="pricing-popular"
                    ><span aria-hidden="true">✧</span>{{ $t('pricing_popular') }}</span
                >
                <div class="pricing-plan-heading">
                    <span class="pricing-icon" aria-hidden="true"><i :class="['pi', plan.icon]" /></span>
                    <h3 :id="'pricing-' + plan.key">{{ plan.name }}</h3>
                </div>
                <p class="pricing-amount">
                    <data :value="plan.display_minor / 100">{{ amount(plan.display_minor) }}</data>
                    <span>{{ state.currency === 'RON' ? $t('pricing_currency') : state.currency }}</span>
                </p>
                <p class="pricing-period">{{ $t('pricing_period') }}</p>
                <p class="pricing-positioning">{{ $t('pricing_' + plan.key + '_positioning') }}</p>
                <ul class="pricing-features">
                    <li v-for="feature in plan.features" :key="feature">
                        <span class="pricing-check" aria-hidden="true">✓</span
                        ><span>{{ $t('pricing_feature_' + feature) }}</span>
                    </li>
                </ul>
            </article>
        </div>
        <div class="pricing-footer">
            <Link :href="signedIn ? '/app' : '/register'" class="pricing-cta">
                {{ $t(signedIn ? 'landing_dashboard' : 'pricing_trial') }}<span aria-hidden="true">↗</span>
            </Link>
            <p class="pricing-migration"><span aria-hidden="true">✓</span>{{ $t('pricing_migration_included') }}</p>
        </div>
    </section>
</template>

<style scoped>
.pricing-currency-control {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: center;
    gap: 10px;
    margin-top: 22px;
    font-size: 13px;
    color: #59654e;
}
.pricing-quote-note {
    margin: 12px auto 0;
    max-width: 65ch;
    font-size: 12px;
    line-height: 1.6;
    color: #657064;
}
.pricing-quote-note span {
    display: block;
}
.pricing-section {
    padding-block: 48px 68px;
    scroll-margin-top: 110px;
    position: relative;
}
.pricing-intro {
    max-width: 820px;
    margin: 0 auto 54px;
    text-align: center;
}
.pricing-eyebrow {
    display: inline-flex;
    align-items: center;
    gap: 9px;
    font-size: 11px;
    font-weight: 750;
    color: #58644d;
    letter-spacing: 0.16em;
    text-transform: uppercase;
}
.pricing-eyebrow > span {
    width: 7px;
    height: 7px;
    border-radius: 50%;
    background: #94b94a;
    box-shadow: 0 0 0 5px #94b94a18;
}
.pricing-intro h2 {
    margin: 18px 0 0;
    color: #1c2723;
    font-size: clamp(34px, 3.8vw, 54px);
    line-height: 1.12;
    font-weight: 750;
    letter-spacing: -0.045em;
    text-wrap: balance;
}
.pricing-intro h2 em {
    display: inline;
    color: #635bff;
    font-family: Georgia, 'Times New Roman', serif;
    font-weight: 400;
    letter-spacing: -0.04em;
}
.pricing-intro .pricing-description {
    margin: 20px auto 0;
    max-width: 56ch;
    font-size: 16px;
    line-height: 1.7;
    color: #657064;
}
.pricing-grid {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    align-items: stretch;
    gap: 24px;
}
.pricing-card {
    position: relative;
    min-width: 0;
    padding: clamp(24px, 2.6vw, 36px);
    border: 1px solid #e4e6dc;
    border-radius: 28px;
    background: linear-gradient(150deg, #fff, #fcfbf7);
    box-shadow: 0 16px 40px -24px #38412930;
    transition:
        transform 0.2s,
        box-shadow 0.2s;
}
.pricing-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 22px 48px -24px #38412940;
}
.pricing-card-popular {
    border: 2px solid #7661f5;
    background: linear-gradient(155deg, #f7f3ff, #f4eeff 65%, #fbf9ff);
    box-shadow: 0 18px 52px -24px #7965e96b;
}
.pricing-popular {
    position: absolute;
    top: 0;
    left: 50%;
    transform: translate(-50%, -50%);
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 9px;
    white-space: nowrap;
    padding: 9px 18px;
    border-radius: 100px;
    background: #d7f378;
    color: #28371a;
    font-size: 11px;
    font-weight: 800;
    letter-spacing: 0.045em;
    text-transform: uppercase;
    box-shadow: 0 5px 16px #b7cf7433;
}
.pricing-popular > span {
    font-size: 24px;
    line-height: 1;
}
.pricing-plan-heading {
    display: flex;
    align-items: center;
    gap: 15px;
}
.pricing-plan-heading h3 {
    overflow-wrap: anywhere;
    font-size: clamp(24px, 2.2vw, 32px);
    font-weight: 750;
    letter-spacing: -0.04em;
}
.pricing-icon {
    display: grid;
    place-items: center;
    width: 54px;
    height: 54px;
    border-radius: 17px;
    background: linear-gradient(135deg, #f2edff, #eae3ff);
    color: #7352ed;
    font-size: 24px;
    flex-shrink: 0;
}
.pricing-card .pricing-amount {
    display: flex;
    flex-wrap: wrap;
    align-items: baseline;
    gap: 8px;
    margin-top: 30px;
    color: #172920;
    font-size: clamp(40px, 4vw, 58px);
    font-weight: 800;
    line-height: 1;
    letter-spacing: -0.06em;
    white-space: normal;
}
.pricing-amount data {
    min-width: 0;
    overflow-wrap: anywhere;
}
.pricing-amount > span {
    font-size: 0.62em;
    letter-spacing: -0.04em;
}
.pricing-card .pricing-period {
    margin-top: 9px;
    font-size: 13px;
    color: #657064;
}
.pricing-card .pricing-positioning {
    display: inline-block;
    margin-top: 26px;
    padding: 7px 14px;
    border-radius: 100px;
    color: #314522;
    background: #e5f8b9;
    font-size: 12px;
    font-weight: 700;
}
.pricing-features {
    display: grid;
    gap: 17px;
    list-style: none;
    padding: 0;
    margin: 28px 0 0;
}
.pricing-features li {
    display: flex;
    gap: 11px;
    align-items: flex-start;
    font-size: 14px;
    line-height: 1.5;
    color: #344136;
}
.pricing-check {
    color: #83a936;
    font-size: 19px;
    font-weight: 800;
    line-height: 1.1;
    flex-shrink: 0;
}
.pricing-footer {
    display: grid;
    justify-items: center;
    gap: 18px;
    margin-top: 34px;
    text-align: center;
}
.pricing-cta {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 32px;
    padding: 18px 30px;
    min-height: 56px;
    border-radius: 16px;
    color: #fff;
    background: linear-gradient(125deg, #7965ff, #5f4aef);
    box-shadow: 0 12px 32px -15px #6650ed80;
    font-size: 16px;
    font-weight: 650;
    transition:
        transform 0.2s,
        box-shadow 0.2s;
}
.pricing-cta:hover {
    transform: translateY(-2px);
    box-shadow: 0 15px 35px -15px #6650edaa;
}
.pricing-cta > span {
    font-size: 24px;
    line-height: 1;
}
.pricing-migration {
    display: flex;
    align-items: flex-start;
    justify-content: center;
    gap: 9px;
    color: #59654e;
    font-size: 13px;
    line-height: 1.6;
}
.pricing-migration > span {
    color: #7c9e35;
    font-weight: 800;
}
@media (max-width: 1000px) {
    .pricing-grid {
        gap: 16px;
    }
    .pricing-card {
        padding: 26px 22px;
    }
    .pricing-plan-heading {
        gap: 11px;
    }
    .pricing-icon {
        width: 44px;
        height: 44px;
        border-radius: 14px;
        font-size: 21px;
    }
}
@media (max-width: 800px) {
    .pricing-section {
        padding-block: 32px 48px;
    }
    .pricing-intro {
        margin-bottom: 38px;
    }
    .pricing-intro h2 {
        font-size: 36px;
    }
    .pricing-intro .pricing-description {
        font-size: 14px;
    }
    .pricing-grid {
        grid-template-columns: minmax(0, 1fr);
        gap: 32px;
        max-width: 520px;
        margin-inline: auto;
    }
    .pricing-card {
        padding: 28px;
        border-radius: 24px;
    }
    .pricing-plan-heading h3 {
        font-size: 29px;
    }
    .pricing-card .pricing-amount {
        margin-top: 24px;
        font-size: 50px;
    }
    .pricing-features {
        gap: 13px;
        margin-top: 22px;
    }
    .pricing-footer {
        margin-top: 28px;
    }
    .pricing-cta {
        width: 100%;
        max-width: 360px;
        padding-inline: 20px;
        font-size: 15px;
        gap: 20px;
    }
}
@media (prefers-reduced-motion: reduce) {
    .pricing-card,
    .pricing-cta {
        transition: none;
    }
    .pricing-card:hover,
    .pricing-cta:hover {
        transform: none;
    }
}
</style>
