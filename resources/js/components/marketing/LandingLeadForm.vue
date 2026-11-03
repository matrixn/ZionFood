<script setup lang="ts">
import { nextTick, onMounted, reactive, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import ZFFormField from '@/components/ui/ZFFormField.vue';
import RegistrationPhoneField from '@/components/RegistrationPhoneField.vue';
import { csrfHeaders } from '@/lib/http';
const { locale } = useI18n();
const form = reactive({ name: '', email: '', phone: '', phone_country: 'RO', website: '' });
const errors = ref<Record<string, string>>({});
const failure = ref('');
const busy = ref(false);
const mounted = ref(false);
const sent = ref(false);
const confirmation = ref<HTMLElement | null>(null);
const phoneField = ref<InstanceType<typeof RegistrationPhoneField> | null>(null);
onMounted(() => (mounted.value = true));
async function submit() {
    if (busy.value || sent.value) return;
    if (!phoneField.value?.validate()) return;
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
            Object.assign(form, { name: '', email: '', phone: '', phone_country: 'RO', website: '' });
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
    <section id="contact" class="lead-section" aria-labelledby="lead-title">
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
                <h2 id="lead-title">{{ $t('lead_success_title') }}</h2>
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
                <h2 id="lead-title">{{ $t('lead_form_title') }}</h2>
                <p class="lead-no-account">{{ $t('lead_no_account') }}</p>
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
                <RegistrationPhoneField
                    id="lead-phone"
                    ref="phoneField"
                    v-model="form.phone"
                    v-model:country="form.phone_country"
                    :disabled="busy"
                    :error="errors.phone"
                    :country-error="errors.phone_country"
                    @change="
                        delete errors.phone;
                        delete errors.phone_country;
                    "
                />
                <input v-model="form.website" type="hidden" name="website" autocomplete="off" tabindex="-1" />
                <button type="submit" class="lead-submit" :disabled="busy || !mounted">
                    {{ $t(busy ? 'lead_sending' : 'lead_submit') }} <span aria-hidden="true">↗</span>
                </button>
                <p class="lead-privacy">{{ $t('lead_contact_notice_compact') }}</p>
                <noscript
                    ><p class="lead-error">{{ $t('lead_javascript') }}</p></noscript
                >
            </form>
        </div>
    </section>
</template>
<style scoped>
.lead-section {
    width: min(100%, 420px);
    min-width: 0;
    position: relative;
    z-index: 2;
    scroll-margin-top: 115px;
}
.lead-section p,
.lead-section h2,
.lead-section h3 {
    margin: 0;
}
.lead-card {
    border: 1px solid #fff9;
    background: rgb(255 255 255 / 0.88);
    backdrop-filter: blur(18px) saturate(120%);
    -webkit-backdrop-filter: blur(18px) saturate(120%);
    border-radius: 28px;
    padding: clamp(20px, 2vw, 24px);
    box-shadow:
        0 24px 64px -24px #17281e60,
        inset 0 1px 0 #fff;
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
    gap: 10px;
}
.lead-card .lead-card-kicker {
    color: #787b70;
    font-size: 11px;
    font-weight: 700;
    letter-spacing: 0.16em;
    text-transform: uppercase;
}
.lead-card h2,
.lead-card h3 {
    font-size: clamp(23px, 2.1vw, 28px);
    font-weight: 700;
    letter-spacing: -0.04em;
    line-height: 1.25;
}
.lead-card input:not([type='hidden']) {
    width: 100%;
    min-width: 0;
    border: 1px solid #dfe2d6;
    border-radius: 12px;
    padding: 10px 12px;
    background: #fafbf8;
    color: #22291c;
    font: inherit;
    font-size: 16px;
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
    padding: 14px 18px;
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
    color: #505d4f;
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
.lead-card .lead-no-account {
    font-size: 12px;
    color: #516044;
    margin-top: -5px;
    line-height: 1.5;
}
.lead-card :deep(.p-select),
.lead-card :deep(.phone-national) {
    background: rgb(255 255 255 / 0.85);
    color: #22291c;
    border-color: #c9d0c1;
    font-size: 16px;
    min-height: 44px;
}
.lead-card :deep(.phone-national) {
    padding: 10px 12px;
}
.lead-card :deep(.field) {
    gap: 5px;
}
.lead-card :deep(.field-label) {
    margin-bottom: 0;
}
.lead-card :deep(.registration-phone p) {
    color: #516044;
    line-height: 1.4;
}
@supports not (backdrop-filter: blur(1px)) {
    .lead-card {
        background: #f9faf6;
    }
}
@media (max-width: 700px) {
    .lead-card {
        border-radius: 22px;
    }
    .lead-card form {
        gap: 10px;
    }
    .lead-card .lead-privacy {
        font-size: 10px;
        line-height: 1.5;
    }
}
@media (prefers-reduced-motion: reduce) {
    .lead-card input,
    .lead-submit {
        transition: none;
    }
}
</style>
