<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import AuthLayout from '@/layouts/AuthLayout.vue';
import ZFFormField from '@/components/ui/ZFFormField.vue';
import InputText from 'primevue/inputtext';
import Button from 'primevue/button';
const form = useForm({
    organization_name: '',
    restaurant_name: '',
    slug: '',
    location_name: 'Main location',
    address: '',
});
const fields = [
    { key: 'organization_name', label: 'Organization name' },
    { key: 'restaurant_name', label: 'Restaurant name' },
    { key: 'slug', label: 'Storefront address (slug)' },
    { key: 'location_name', label: 'Location name' },
    { key: 'address', label: 'Street address' },
] as const;
</script>
<template>
    <AuthLayout
        ><Head title="Create your restaurant" /><span class="text-sm font-medium text-brand">LET’S GET YOU OPEN</span>
        <h1 class="mb-3 mt-4 text-3xl font-semibold">Your next chapter starts here.</h1>
        <p class="muted mb-7">One organization, one restaurant, ready to make it yours.</p>
        <form class="grid gap-4" @submit.prevent="form.post('/onboarding')">
            <ZFFormField
                v-for="field in fields"
                :key="field.key"
                :label="field.label"
                :for="field.key"
                :error="form.errors[field.key]"
                ><InputText :id="field.key" v-model="form[field.key]" required /></ZFFormField
            ><Button type="submit" label="Create workspace" icon="pi pi-arrow-right" :loading="form.processing" /></form
    ></AuthLayout>
</template>
