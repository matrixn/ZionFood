<script setup lang="ts">
import { tr as t } from '@/i18n';

import { computed, ref } from 'vue';
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
function submit() {
    const url = { login: '/login', register: '/register', forgot: '/forgot-password', reset: '/reset-password' }[
        props.mode
    ];
    form.post(url, {
        onError: (errors) => {
            if (errors.captcha_proof) void captcha.value?.renew();
        },
        onFinish: () => {
            form.reset('password', 'password_confirmation');
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
            <ZFFormField v-if="mode === 'register'" :label="$t('full_name')" for="name" :error="form.errors.name"
                ><InputText id="name" v-model="form.name" autocomplete="name" required /></ZFFormField
            ><ZFFormField :label="$t('email')" for="email" :error="form.errors.email"
                ><InputText
                    id="email"
                    v-model="form.email"
                    :readonly="invited"
                    type="email"
                    autocomplete="email"
                    required /></ZFFormField
            ><ZFFormField v-if="mode !== 'forgot'" :label="$t('password')" for="password" :error="form.errors.password"
                ><InputText
                    id="password"
                    v-model="form.password"
                    type="password"
                    :autocomplete="mode === 'login' ? 'current-password' : 'new-password'"
                    :minlength="mode === 'login' ? undefined : 12"
                    required /></ZFFormField
            ><ZFFormField
                v-if="mode === 'register' || mode === 'reset'"
                :label="$t('confirm_password')"
                for="password_confirmation"
                :error="form.errors.password_confirmation"
                ><InputText
                    id="password_confirmation"
                    v-model="form.password_confirmation"
                    type="password"
                    autocomplete="new-password"
                    required /></ZFFormField
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
