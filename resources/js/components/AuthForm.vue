<script setup lang="ts">
import { computed } from 'vue';
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
}>();
const title = computed(
    () =>
        ({
            login: 'Welcome back',
            register: 'Start serving smarter',
            forgot: 'Reset your password',
            reset: 'Choose a new password',
        })[props.mode],
);
const form = useForm({
    name: '',
    email: props.email ?? '',
    password: '',
    password_confirmation: '',
    token: props.token ?? '',
    remember: false,
});
function submit() {
    const url = { login: '/login', register: '/register', forgot: '/forgot-password', reset: '/reset-password' }[
        props.mode
    ];
    form.post(url, {
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
                    ? 'Create your account. Your restaurant comes next.'
                    : 'One workspace for every order.'
            }}
        </p>
        <p v-if="status" role="status" class="mb-4 text-green-700">{{ status }}</p>
        <form class="grid gap-5" @submit.prevent="submit">
            <ZFFormField v-if="mode === 'register'" label="Full name" for="name" :error="form.errors.name"
                ><InputText id="name" v-model="form.name" autocomplete="name" required /></ZFFormField
            ><ZFFormField label="Email" for="email" :error="form.errors.email"
                ><InputText id="email" v-model="form.email" type="email" autocomplete="email" required /></ZFFormField
            ><ZFFormField v-if="mode !== 'forgot'" label="Password" for="password" :error="form.errors.password"
                ><InputText
                    id="password"
                    v-model="form.password"
                    type="password"
                    :autocomplete="mode === 'login' ? 'current-password' : 'new-password'"
                    :minlength="mode === 'login' ? undefined : 12"
                    required /></ZFFormField
            ><ZFFormField
                v-if="mode === 'register' || mode === 'reset'"
                label="Confirm password"
                for="password_confirmation"
                :error="form.errors.password_confirmation"
                ><InputText
                    id="password_confirmation"
                    v-model="form.password_confirmation"
                    type="password"
                    autocomplete="new-password"
                    required /></ZFFormField
            ><Link v-if="mode === 'login'" href="/forgot-password" class="text-sm text-brand">Forgot password?</Link
            ><Button
                type="submit"
                :label="
                    mode === 'login'
                        ? 'Sign in'
                        : mode === 'register'
                          ? 'Create account'
                          : mode === 'forgot'
                            ? 'Send reset link'
                            : 'Save password'
                "
                :loading="form.processing"
            />
            <p class="muted text-center">
                <Link :href="mode === 'login' ? '/register' : '/login'" class="text-brand">{{
                    mode === 'login' ? 'New here? Create an account' : 'Back to sign in'
                }}</Link>
            </p>
        </form></AuthLayout
    >
</template>
