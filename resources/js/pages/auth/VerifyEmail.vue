<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import AuthLayout from '@/layouts/AuthLayout.vue';
import Button from 'primevue/button';
defineProps<{ status?: string }>();
</script>
<template>
    <AuthLayout
        ><Head title="Verify email" /><i class="pi pi-envelope mb-6 text-5xl text-brand" />
        <h1 class="text-3xl font-semibold">Check your inbox</h1>
        <p class="muted my-5">Verify your email address to set up your restaurant.</p>
        <p v-if="status" role="status" class="my-4">{{ status }}</p>
        <div class="grid gap-3">
            <Button label="Resend verification email" @click="router.post('/email/verification-notification')" /><Button
                label="Sign out"
                text
                @click="router.post('/logout')"
            /></div
    ></AuthLayout>
</template>
