<script setup lang="ts">
import { tr as t } from '@/i18n';

import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { fieldHelp } from '@/lib/fieldHelp';
import RegistrationCaptcha from '@/components/RegistrationCaptcha.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import AuthLayout from '@/layouts/AuthLayout.vue';
import InputText from 'primevue/inputtext';
import Button from 'primevue/button';
import ZFFormField from '@/components/ui/ZFFormField.vue';
const props = defineProps<{
    mode: 'login' | 'register' | 'forgot' | 'reset';
    token?: string;
    email?: string;
    status?: string;
    invited?: boolean;
}>();
const title = computed(
    () =>
        ({
            login: t('welcome_back'),
            register: props.invited ? t('invitation_register_title') : t('start_serving_smarter'),
            forgot: t('reset_your_password'),
            reset: t('choose_a_new_password'),
        })[props.mode],
);
const form = useForm({
    name: '',
    email: props.email ?? '',
    password: '',
    password_confirmation: '',
    token: props.token ?? '',
    remember: false,
    captcha_proof: '',
});
const captcha = ref<InstanceType<typeof RegistrationCaptcha> | null>(null);
type PasswordField = 'password' | 'password_confirmation';
const revealed = ref<PasswordField | null>(null);
const passwordHelp = computed(() => fieldHelp('', t('password')) + ' ' + t('password_reveal_help'));
function hidePasswords() {
    revealed.value = null;
}
function revealWithPointer(event: PointerEvent, field: PasswordField) {
    if (event.button !== 0) return;
    event.preventDefault();
    revealed.value = field;
}
function revealWithKeyboard(event: KeyboardEvent, field: PasswordField) {
    if (
        event.key === 'F2' ||
        (event.currentTarget instanceof HTMLButtonElement && [' ', 'Enter'].includes(event.key))
    ) {
        event.preventDefault();
        revealed.value = field;
    }
    if (event.key === 'Escape') hidePasswords();
}
function releaseKey(event: KeyboardEvent) {
    if (['F2', ' ', 'Enter'].includes(event.key)) hidePasswords();
}
onMounted(() => {
    window.addEventListener('pointerup', hidePasswords, true);
    window.addEventListener('pointercancel', hidePasswords, true);
    window.addEventListener('keyup', releaseKey, true);
    window.addEventListener('blur', hidePasswords);
    document.addEventListener('visibilitychange', hidePasswords);
});
onBeforeUnmount(() => {
    hidePasswords();
    window.removeEventListener('pointerup', hidePasswords, true);
    window.removeEventListener('pointercancel', hidePasswords, true);
    window.removeEventListener('keyup', releaseKey, true);
    window.removeEventListener('blur', hidePasswords);
    document.removeEventListener('visibilitychange', hidePasswords);
});
function submit() {
    hidePasswords();
    const url = { login: '/login', register: '/register', forgot: '/forgot-password', reset: '/reset-password' }[
        props.mode
    ];
    form.post(url, {
        onError: (errors) => {
            if (errors.captcha_proof) void captcha.value?.renew();
        },
        onSuccess: () => {
            if (props.mode === 'register') form.reset('password', 'password_confirmation');
        },
        onFinish: () => {
            hidePasswords();
            if (props.mode !== 'register') form.reset('password', 'password_confirmation');
        },
    });
}
</script>
<template>
    <AuthLayout
        ><Head :title="title" />
        <h1 class="text-3xl font-semibold tracking-tight">{{ title }}</h1>
        <p class="muted mb-8 mt-3">
            {{
                mode === 'register'
                    ? invited
                        ? $t('invitation_register_hint')
                        : $t('create_your_account_your_restaurant_comes_next')
                    : $t('one_workspace_for_every_order')
            }}
        </p>
        <p v-if="status" role="status" class="mb-4 text-green-700">{{ status }}</p>
        <form class="grid gap-5" @submit.prevent="submit">
            <ZFFormField
                v-if="mode === 'register'"
                :label="$t('full_name')"
                for="name"
                :error="form.errors.name"
                :help-tabbable="false"
                ><InputText
                    id="name"
                    v-model="form.name"
                    name="name"
                    aria-describedby="name-help"
                    autocomplete="name"
                    required /></ZFFormField
            ><ZFFormField :label="$t('email')" for="email" :error="form.errors.email" :help-tabbable="false"
                ><InputText
                    id="email"
                    v-model="form.email"
                    name="email"
                    aria-describedby="email-help"
                    :readonly="invited"
                    type="email"
                    autocomplete="email"
                    required /></ZFFormField
            ><ZFFormField
                v-if="mode !== 'forgot'"
                :label="$t('password')"
                for="password"
                :error="form.errors.password"
                :help="passwordHelp"
                :help-tabbable="false"
                ><div class="auth-password-control">
                    <InputText
                        id="password"
                        v-model="form.password"
                        name="password"
                        aria-describedby="password-help"
                        aria-keyshortcuts="F2"
                        :type="revealed === 'password' ? 'text' : 'password'"
                        :autocomplete="mode === 'login' ? 'current-password' : 'new-password'"
                        :minlength="mode === 'login' ? undefined : 12"
                        required
                        @keydown="revealWithKeyboard($event, 'password')"
                        @blur="hidePasswords"
                    /><button
                        type="button"
                        class="auth-password-hold"
                        tabindex="-1"
                        :aria-label="$t('hold_to_reveal_password')"
                        :aria-pressed="revealed === 'password'"
                        aria-controls="password"
                        :title="$t('password_reveal_help')"
                        @pointerdown="revealWithPointer($event, 'password')"
                        @pointerleave="hidePasswords"
                        @pointercancel="hidePasswords"
                        @blur="hidePasswords"
                        @click.prevent
                        @keydown="revealWithKeyboard($event, 'password')"
                    >
                        <i :class="revealed === 'password' ? 'pi pi-eye-slash' : 'pi pi-eye'" aria-hidden="true" />
                    </button></div></ZFFormField
            ><ZFFormField
                v-if="mode === 'register' || mode === 'reset'"
                :label="$t('confirm_password')"
                for="password_confirmation"
                :error="form.errors.password_confirmation"
                :help="passwordHelp"
                :help-tabbable="false"
                ><div class="auth-password-control">
                    <InputText
                        id="password_confirmation"
                        v-model="form.password_confirmation"
                        name="password_confirmation"
                        aria-describedby="password_confirmation-help"
                        aria-keyshortcuts="F2"
                        :type="revealed === 'password_confirmation' ? 'text' : 'password'"
                        autocomplete="new-password"
                        required
                        @keydown="revealWithKeyboard($event, 'password_confirmation')"
                        @blur="hidePasswords"
                    /><button
                        type="button"
                        class="auth-password-hold"
                        tabindex="-1"
                        :aria-label="$t('hold_to_reveal_confirmation')"
                        :aria-pressed="revealed === 'password_confirmation'"
                        aria-controls="password_confirmation"
                        :title="$t('password_reveal_help')"
                        @pointerdown="revealWithPointer($event, 'password_confirmation')"
                        @pointerleave="hidePasswords"
                        @pointercancel="hidePasswords"
                        @blur="hidePasswords"
                        @click.prevent
                        @keydown="revealWithKeyboard($event, 'password_confirmation')"
                    >
                        <i
                            :class="revealed === 'password_confirmation' ? 'pi pi-eye-slash' : 'pi pi-eye'"
                            aria-hidden="true"
                        />
                    </button></div></ZFFormField
            ><RegistrationCaptcha
                v-if="mode === 'register' && !invited"
                ref="captcha"
                v-model="form.captcha_proof"
                :error="form.errors.captcha_proof"
                @verified="form.clearErrors('captcha_proof')"
            />
            <Link v-if="mode === 'login'" href="/forgot-password" class="text-sm text-brand">{{
                $t('forgot_password')
            }}</Link
            ><Button
                type="submit"
                :label="
                    mode === 'login'
                        ? $t('sign_in')
                        : mode === 'register'
                          ? $t('create_account')
                          : mode === 'forgot'
                            ? $t('send_reset_link')
                            : $t('save_password')
                "
                :loading="form.processing"
                :disabled="mode === 'register' && !invited && !form.captcha_proof"
            />
            <p class="muted text-center">
                <Link :href="mode === 'login' ? '/register' : '/login'" class="text-brand">{{
                    mode === 'login' ? $t('new_here_create_an_account') : $t('back_to_sign_in')
                }}</Link>
            </p>
        </form></AuthLayout
    >
</template>
<style scoped>
.auth-password-control {
    display: flex;
    width: 100%;
}
.auth-password-control :deep(input) {
    flex: 1;
    min-width: 0;
    width: 0;
    border-top-right-radius: 0;
    border-bottom-right-radius: 0;
    border-right-width: 0;
}
.auth-password-hold {
    display: grid;
    place-items: center;
    flex: 0 0 44px;
    min-height: 44px;
    border: 1px solid var(--p-inputtext-border-color, #cbd5e1);
    border-radius: 0 var(--p-inputtext-border-radius, 0.5rem) var(--p-inputtext-border-radius, 0.5rem) 0;
    background: var(--p-inputtext-background, white);
    color: var(--p-inputtext-color, #334155);
    cursor: pointer;
    touch-action: none;
    user-select: none;
}
.auth-password-hold:hover,
.auth-password-hold[aria-pressed='true'] {
    color: var(--p-primary-color, #6558f5);
    background: var(--p-highlight-background, #f1efff);
}
.auth-password-hold:focus-visible {
    outline: 2px solid var(--p-primary-color, #6558f5);
    outline-offset: 2px;
}
</style>
