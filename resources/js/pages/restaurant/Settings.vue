<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import RestaurantLayout from '@/layouts/RestaurantLayout.vue';
import ZFPageHeader from '@/components/ui/ZFPageHeader.vue';
import InputText from 'primevue/inputtext';
import InputNumber from 'primevue/inputnumber';
import ToggleSwitch from 'primevue/toggleswitch';
import Select from 'primevue/select';
import Button from 'primevue/button';
import type { WorkspaceProps } from '@/types';
const props = defineProps<WorkspaceProps>();
const days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
const form = useForm({
    name: props.location.name,
    address: props.location.address,
    accepting_orders: props.location.accepting_orders,
    pickup_enabled: props.location.fulfillment.pickup_enabled,
    delivery_enabled: props.location.fulfillment.delivery_enabled,
    hours: props.location.hours.map((h) => ({
        weekday: h.weekday,
        opens_at: h.opens_at.slice(0, 5),
        closes_at: h.closes_at.slice(0, 5),
    })),
    zones: props.location.zones.map((z) => ({
        name: z.name,
        postal_prefix: z.postal_prefix,
        fee: z.fee,
        minimum_order: z.minimum_order,
    })),
});
</script>
<template>
    <RestaurantLayout v-bind="$props"
        ><Head title="Location settings" /><ZFPageHeader
            title="Set the pace."
            description="Opening hours, fulfillment and your location details." />
        <form class="grid gap-6" @submit.prevent="form.put('/app/locations/' + location.public_id + '/settings')">
            <div v-if="Object.keys(form.errors).length" role="alert" class="panel text-red-600">
                <p v-for="(message, key) in form.errors" :key="key">{{ message }}</p>
            </div>
            <section class="panel grid gap-5">
                <h2 class="text-xl font-semibold">Location</h2>
                <label class="field">Name<InputText v-model="form.name" required /></label
                ><label class="field">Address<InputText v-model="form.address" required /></label
                ><label class="flex items-center gap-3"
                    ><ToggleSwitch v-model="form.accepting_orders" />Accepting online orders</label
                >
                <div class="flex flex-wrap gap-8">
                    <label class="flex items-center gap-3"><ToggleSwitch v-model="form.pickup_enabled" />Pickup</label
                    ><label class="flex items-center gap-3"
                        ><ToggleSwitch v-model="form.delivery_enabled" />Delivery</label
                    >
                </div>
            </section>
            <section class="panel">
                <h2 class="mb-5 text-xl font-semibold">Opening hours</h2>
                <p class="muted mb-4">
                    {{ location.timezone }} · Omit closed days. Overnight opening is not supported.
                </p>
                <div v-for="(h, i) in form.hours" :key="i" class="mb-3 flex flex-wrap items-center gap-3">
                    <Select
                        v-model="h.weekday"
                        :options="days.map((name, n) => ({ name, id: n + 1 }))"
                        option-label="name"
                        option-value="id"
                        aria-label="Weekday"
                        class="w-44"
                    /><input v-model="h.opens_at" type="time" aria-label="Opening time" required /><span>to</span
                    ><input v-model="h.closes_at" type="time" aria-label="Closing time" required /><Button
                        icon="pi pi-times"
                        text
                        aria-label="Remove opening day"
                        @click="form.hours.splice(i, 1)"
                    />
                </div>
                <Button
                    label="Add day"
                    icon="pi pi-plus"
                    text
                    :disabled="form.hours.length >= 7"
                    @click="
                        form.hours.push({
                            weekday: days.findIndex((_, i) => !form.hours.some((h) => h.weekday === i + 1)) + 1,
                            opens_at: '10:00',
                            closes_at: '22:00',
                        })
                    "
                />
            </section>
            <section class="panel">
                <h2 class="mb-2 text-xl font-semibold">Delivery zones</h2>
                <p class="muted mb-4">Basic postal code matching. Amounts are in bani (100 bani = 1 RON).</p>
                <div v-for="(z, i) in form.zones" :key="i" class="mb-5 grid items-end gap-3 lg:grid-cols-5">
                    <label class="field">Name<InputText v-model="z.name" required /></label
                    ><label class="field">Postal prefix<InputText v-model="z.postal_prefix" required /></label
                    ><label class="field">Fee (bani)<InputNumber v-model="z.fee" :min="0" /></label
                    ><label class="field">Minimum (bani)<InputNumber v-model="z.minimum_order" :min="0" /></label
                    ><Button label="Remove" text severity="danger" @click="form.zones.splice(i, 1)" />
                </div>
                <Button
                    label="Add zone"
                    icon="pi pi-plus"
                    text
                    @click="form.zones.push({ name: '', postal_prefix: '', fee: 0, minimum_order: 0 })"
                />
            </section>
            <Button type="submit" label="Save settings" :loading="form.processing" class="justify-self-start" /></form
    ></RestaurantLayout>
</template>
