<script setup lang="ts">
import { nextTick, onMounted, reactive, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import ZFFormField from '@/components/ui/ZFFormField.vue';
import { csrfHeaders } from '@/lib/http';
const { locale } = useI18n();
const form = reactive({ name: '', email: '', phone: '', website: '' });
const errors = ref<Record<string, string>>({});
const failure = ref('');
const busy = ref(false);
const mounted = ref(false);
const sent = ref(false);
const confirmation = ref<HTMLElement | null>(null);
onMounted(() => (mounted.value = true));
async function submit() {
    if (busy.value || sent.value) return;
    busy.value = true;
    failure.value = '';
    errors.value = {};
    try {
        const response = await fetch('/interest', {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json', Accept: 'application/json', ...csrfHeaders() },
            body: JSON.stringify({ ...form, locale: locale.value }),
        });
        if (response.status === 422) {
            const data = await response.json();
            errors.value = Object.fromEntries(
                Object.entries(data.errors ?? {}).map(([key, value]) => [key, (value as string[])[0]]),
            );
            failure.value = 'lead_validation';
        } else if (!response.ok) {
            failure.value = response.status === 429 ? 'lead_rate_limit' : 'lead_failure';
        } else {
            const result = await response.json();
            if (result.status !== 'received') throw new Error('Invalid lead confirmation');
            sent.value = true;
            Object.assign(form, { name: '', email: '', phone: '', website: '' });
            await nextTick();
            confirmation.value?.focus();
        }
    } catch {
        failure.value = 'lead_failure';
    } finally {
        busy.value = false;
    }
}
</script>
<template>
    <section id="contact" class="lead-section section-wrap" data-reveal aria-labelledby="lead-title">
        <div class="lead-story">
            <p class="lead-eyebrow"><span aria-hidden="true">↗</span> {{ $t('lead_eyebrow') }}</p>
            <h2 id="lead-title">
                {{ $t('lead_title') }} <em>{{ $t('lead_title_accent') }}</em>
            </h2>
            <p class="lead-description">{{ $t('lead_description') }}</p>
            <ol class="lead-steps">
                <li v-for="(step, index) in ['contact', 'discover', 'prepare']" :key="step">
                    <span>0{{ index + 1 }}</span>
                    <div>
                        <strong>{{ $t('lead_step_' + step) }}</strong>
                        <p>{{ $t('lead_step_' + step + '_description') }}</p>
                    </div>
                </li>
            </ol>
            <p class="lead-story-note">{{ $t('lead_no_account') }}</p>
        </div>
        <div class="lead-card">
            <div
                v-if="sent"
                ref="confirmation"
                role="status"
                tabindex="-1"
                class="lead-confirmation"
                data-testid="lead-confirmation"
            >
                <span class="lead-check" aria-hidden="true">✓</span>
                <h3>{{ $t('lead_success_title') }}</h3>
                <p>{{ $t('lead_success_description') }}</p>
                <a href="#platform" class="lead-submit">{{ $t('lead_back') }} <span aria-hidden="true">↗</span></a>
            </div>
            <form
                v-else
                method="post"
                action="/interest"
                :aria-label="$t('lead_form_title')"
                :aria-busy="busy"
                @submit.prevent="submit"
            >
                <p class="lead-card-kicker">{{ $t('lead_form_kicker') }}</p>
                <h3>{{ $t('lead_form_title') }}</h3>
                <p v-if="failure" role="alert" class="lead-error">{{ $t(failure) }}</p>
                <ZFFormField :label="$t('lead_name')" for="lead-name" :help="$t('lead_name_help')" :error="errors.name">
                    <input
                        id="lead-name"
                        v-model="form.name"
                        name="name"
                        autocomplete="name"
                        required
                        minlength="2"
                        maxlength="120"
                        :aria-label="$t('lead_name')"
                        :aria-invalid="!!errors.name"
                        :disabled="busy"
                        :placeholder="$t('lead_name_placeholder')"
                    />
                </ZFFormField>
                <ZFFormField
                    :label="$t('lead_email')"
                    for="lead-email"
                    :help="$t('lead_email_help')"
                    :error="errors.email"
                >
                    <input
                        id="lead-email"
                        v-model="form.email"
                        type="email"
                        name="email"
                        autocomplete="email"
                        required
                        maxlength="254"
                        :aria-label="$t('lead_email')"
                        :aria-invalid="!!errors.email"
                        :disabled="busy"
                        :placeholder="$t('lead_email_placeholder')"
                    />
                </ZFFormField>
                <ZFFormField
                    :label="$t('lead_phone')"
                    for="lead-phone"
                    :help="$t('lead_phone_help')"
                    :error="errors.phone"
                >
                    <input
                        id="lead-phone"
                        v-model="form.phone"
                        type="tel"
                        name="phone"
                        autocomplete="tel"
                        required
                        minlength="7"
                        maxlength="30"
                        :aria-label="$t('lead_phone')"
                        :aria-invalid="!!errors.phone"
                        :disabled="busy"
                        :placeholder="$t('lead_phone_placeholder')"
                    />
                </ZFFormField>
                <input v-model="form.website" type="hidden" name="website" autocomplete="off" tabindex="-1" />
                <button type="submit" class="lead-submit" :disabled="busy || !mounted">
                    {{ $t(busy ? 'lead_sending' : 'lead_submit') }} <span aria-hidden="true">↗</span>
                </button>
                <p class="lead-privacy">{{ $t('lead_contact_notice') }}</p>
                <noscript
                    ><p class="lead-error">{{ $t('lead_javascript') }}</p></noscript
                >
            </form>
        </div>
    </section>
</template>
<style scoped>
.lead-section {
    display: grid;
    grid-template-columns: 1.05fr 1fr;
    gap: clamp(2rem, 6vw, 6rem);
    padding-block: 100px;
    scroll-margin-top: 100px;
    align-items: center;
}
.lead-section p,
.lead-section h2,
.lead-section h3 {
    margin: 0;
}
.lead-eyebrow {
    font-size: 12px;
    letter-spacing: 0.15em;
    text-transform: uppercase;
    font-weight: 750;
    color: #5d6181;
    display: flex;
    align-items: center;
    gap: 10px;
}
.lead-eyebrow > span {
    display: grid;
    place-items: center;
    background: #d7f378;
    color: #25291c;
    border-radius: 50%;
    width: 30px;
    height: 30px;
    font-size: 20px;
}
.lead-story h2 {
    margin-top: 22px;
    font-size: clamp(34px, 3.5vw, 51px);
    line-height: 1.12;
    letter-spacing: -0.045em;
    font-weight: 750;
    text-wrap: balance;
}
.lead-story h2 em {
    font-family: Georgia, serif;
    font-weight: 400;
    color: #635bff;
}
.lead-story .lead-description {
    margin-top: 24px;
    color: #64665b;
    line-height: 1.7;
    max-width: 46ch;
}
.lead-steps {
    list-style: none;
    padding: 0;
    margin: 32px 0;
    display: grid;
    gap: 22px;
}
.lead-steps li {
    display: flex;
    gap: 16px;
    align-items: flex-start;
}
.lead-steps li > span {
    border: 1px solid #daddce;
    border-radius: 12px;
    display: grid;
    place-items: center;
    min-width: 38px;
    height: 38px;
    font-size: 12px;
    color: #666b56;
}
.lead-steps strong {
    font-size: 14px;
    font-weight: 700;
}
.lead-steps p {
    font-size: 13px;
    line-height: 1.5;
    color: #717467;
    padding-top: 4px;
}
.lead-story-note {
    font-size: 13px;
    font-weight: 600;
    color: #596c3e;
}
.lead-card {
    border: 1px solid #dfe4d4;
    background: #fff;
    border-radius: 28px;
    padding: clamp(24px, 4vw, 44px);
    box-shadow: 0 20px 60px -35px #596c3e45;
    position: relative;
    min-width: 0;
}
.lead-card::before {
    content: '';
    position: absolute;
    top: -1px;
    left: 32px;
    right: 32px;
    height: 4px;
    background: linear-gradient(90deg, #d7f378, #635bff);
    border-radius: 4px;
}
.lead-card form {
    display: grid;
    gap: 20px;
}
.lead-card .lead-card-kicker {
    color: #787b70;
    font-size: 11px;
    font-weight: 700;
    letter-spacing: 0.16em;
    text-transform: uppercase;
}
.lead-card h3 {
    font-size: 25px;
    font-weight: 700;
    letter-spacing: -0.04em;
    line-height: 1.25;
}
.lead-card input:not([type='hidden']) {
    width: 100%;
    min-width: 0;
    border: 1px solid #dfe2d6;
    border-radius: 12px;
    padding: 14px 16px;
    background: #fafbf8;
    color: #22291c;
    font: inherit;
    font-size: 15px;
    transition:
        border-color 0.2s,
        box-shadow 0.2s;
}
.lead-card input::placeholder {
    color: #979b8e;
}
.lead-card input:focus {
    outline: 0;
    border-color: #8c84f6;
    box-shadow: 0 0 0 3px #635bff15;
}
.lead-card input[aria-invalid='true'] {
    border-color: #cb4a4a;
}
.lead-card :deep(.field-label) {
    font-size: 13px;
    color: #404836;
}
.lead-submit {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 20px;
    width: 100%;
    border: 0;
    border-radius: 12px;
    padding: 17px 20px;
    background: #635bff;
    color: #fff;
    font: inherit;
    font-size: 15px;
    font-weight: 650;
    cursor: pointer;
    text-decoration: none;
    transition: background 0.2s;
}
.lead-submit:hover {
    background: #5146df;
}
.lead-submit:disabled {
    opacity: 0.65;
    cursor: wait;
}
.lead-card .lead-privacy {
    font-size: 11px;
    line-height: 1.7;
    color: #7d8273;
}
.lead-error {
    padding: 12px;
    border: 1px solid #f1c5c5;
    border-radius: 10px;
    font-size: 13px;
    color: #a33131;
    background: #fff5f5;
    line-height: 1.6;
}
.lead-confirmation {
    display: grid;
    gap: 24px;
    padding-block: 28px;
}
.lead-confirmation p {
    color: #66705d;
    line-height: 1.7;
}
.lead-check {
    width: 56px;
    height: 56px;
    display: grid;
    place-items: center;
    border-radius: 50%;
    background: #edf8d6;
    color: #526f2d;
    font-size: 28px;
}
@media (max-width: 800px) {
    .lead-section {
        grid-template-columns: 1fr;
        gap: 32px;
        padding-block: 70px;
    }
    .lead-story h2 {
        max-width: 18ch;
    }
    .lead-steps {
        gap: 16px;
        margin-block: 24px;
    }
    .lead-card {
        border-radius: 22px;
    }
}
@media (prefers-reduced-motion: reduce) {
    .lead-card input,
    .lead-submit {
        transition: none;
    }
}
</style>
