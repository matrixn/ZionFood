<script setup lang="ts">
import { tr as t } from '@/i18n';

import { computed } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import AuthLayout from '@/layouts/AuthLayout.vue';
import ZFFormField from '@/components/ui/ZFFormField.vue';
import InputText from 'primevue/inputtext';
import Button from 'primevue/button';
const form = useForm({
    organization_name: '',
    restaurant_name: '',
    slug: '',
    location_name: t('main_location'),
    address: '',
});
const fields = computed(
    () =>
        [
            { key: 'organization_name', label: t('organization_name') },
            { key: 'restaurant_name', label: t('restaurant_name') },
            { key: 'slug', label: t('storefront_address_slug') },
            { key: 'location_name', label: t('location_name') },
            { key: 'address', label: t('street_address') },
        ] as const,
);
</script>
<template>
    <AuthLayout
        ><Head :title="$t('create_your_restaurant')" /><span class="text-sm font-medium text-brand">{{
            $t('lets_get_you_open')
        }}</span>
        <h1 class="mb-3 mt-4 text-3xl font-semibold">{{ $t('your_next_chapter_starts_here') }}</h1>
        <p class="muted mb-7">{{ $t('one_organization_one_restaurant_ready_to_make_it_yours') }}</p>
        <form class="grid gap-4" @submit.prevent="form.post('/onboarding')">
            <ZFFormField
                v-for="field in fields"
                :key="field.key"
                :label="field.label"
                :for="field.key"
                :error="form.errors[field.key]"
                ><InputText :id="field.key" v-model="form[field.key]" required /></ZFFormField
            ><Button
                type="submit"
                :label="$t('create_workspace')"
                icon="pi pi-arrow-right"
                :loading="form.processing"
            /></form
    ></AuthLayout>
</template>
