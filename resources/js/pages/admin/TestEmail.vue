<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/layouts/AdminLayout.vue';
import ZFPageHeader from '@/components/ui/ZFPageHeader.vue';
import Button from 'primevue/button';

defineProps<{ recipient: string | null }>();
const form = useForm({ email: '' });
</script>

<template>
    <AdminLayout>
        <Head :title="$t('email_test_title')" />
        <ZFPageHeader :title="$t('email_test_title')" :description="$t('email_test_description')" />
        <section class="panel mt-6 max-w-xl space-y-5">
            <p class="break-all font-medium">{{ $t('email_test_recipient') }}: {{ recipient || '—' }}</p>
            <p class="text-sm text-slate-600 dark:text-slate-300">{{ $t('email_test_quota') }}</p>
            <form @submit.prevent="form.post('/test-email')">
                <p v-if="form.errors.email" role="alert" class="mb-4 text-red-600">{{ form.errors.email }}</p>
                <Button type="submit" icon="pi pi-send" :label="$t('email_test_send')" :loading="form.processing" :disabled="!recipient" />
            </form>
        </section>
    </AdminLayout>
</template>
