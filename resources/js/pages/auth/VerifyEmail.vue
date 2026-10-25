<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import AuthLayout from '@/layouts/AuthLayout.vue';
import Button from 'primevue/button';
defineProps<{ status?: string }>();
</script>
<template>
    <AuthLayout
        ><Head :title="$t('verify_email')" /><i aria-hidden="true" class="pi pi-envelope mb-6 text-5xl text-brand" />
        <h1 class="text-3xl font-semibold">{{ $t('check_your_inbox') }}</h1>
        <p class="muted my-5">{{ $t('verify_your_email_address_to_set_up_your_restaurant') }}</p>
        <p v-if="status" role="status" class="my-4">{{ status }}</p>
        <div class="grid gap-3">
            <Button
                :label="$t('resend_verification_email')"
                @click="router.post('/email/verification-notification')"
            /><Button :label="$t('sign_out')" text @click="router.post('/logout')" /></div
    ></AuthLayout>
</template>
