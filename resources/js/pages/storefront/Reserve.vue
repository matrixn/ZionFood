<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import StorefrontLayout from '@/layouts/StorefrontLayout.vue';
import Button from 'primevue/button';
import InputText from 'primevue/inputtext';
import InputNumber from 'primevue/inputnumber';
import Select from 'primevue/select';
import Textarea from 'primevue/textarea';
import { api } from '@/lib/http';
import type { RestaurantBrand } from '@/types';

const props = defineProps<{
    location: { public_id: string; name: string; timezone: string };
    restaurant: RestaurantBrand;
    rules: {
        min_party_size?: number;
        max_party_size?: number;
        guest_notes?: string;
        confirmation?: string;
        duration_minutes?: number;
    };
    today: string;
}>();
const date = ref(props.today);
const party = ref(Math.min(props.rules.max_party_size ?? 8, Math.max(2, props.rules.min_party_size ?? 1)));
const slot = ref<string | null>(null);
const slots = ref<string[]>([]);
const guest = ref({ guest_name: '', guest_phone: '', guest_email: '', notes: '' });
const error = ref('');
const busy = ref(false);
const loading = ref(false);
let key = crypto.randomUUID();
let signature = '';
let search = 0;
const options = computed(() => slots.value.map((time) => ({ label: time.slice(11), value: time })));
watch([date, party], () => {
    search++;
    loading.value = false;
    slots.value = [];
    slot.value = null;
});
async function load() {
    const request = ++search;
    loading.value = true;
    error.value = '';
    slot.value = null;
    try {
        const result = await api<{ slots: string[] }>(
            `/api/v1/public/locations/${props.location.public_id}/reservation-slots?${new URLSearchParams({ date: date.value, party_size: String(party.value) })}`,
        );
        if (request === search) slots.value = result.slots;
    } catch (e) {
        if (request === search) error.value = (e as Error).message;
    } finally {
        if (request === search) loading.value = false;
    }
}
async function submit() {
    error.value = '';
    busy.value = true;
    const payload = { ...guest.value, party_size: party.value, starts_at: slot.value };
    const current = JSON.stringify(payload);
    if (signature && current !== signature) key = crypto.randomUUID();
    signature = current;
    try {
        const result = await api<{ reservation_id: string; token: string }>(
            `/api/v1/public/locations/${props.location.public_id}/reservations`,
            'POST',
            payload,
            { 'Idempotency-Key': key },
        );
        sessionStorage.setItem(`zf-reservation-${result.reservation_id}`, result.token);
        router.visit(`/reservations/${result.reservation_id}`);
    } catch (e) {
        error.value = (e as Error).message;
    } finally {
        busy.value = false;
    }
}
onMounted(load);
</script>
<template>
    <StorefrontLayout :name="restaurant.name" :brand="restaurant"
        ><Head :title="$t('booking_title')" />
        <div class="mx-auto max-w-2xl space-y-6">
            <div>
                <h1 class="text-3xl font-semibold">{{ $t('booking_title') }}</h1>
                <p class="muted mt-3">
                    {{ location.name }} · {{ location.timezone }} · {{ rules.duration_minutes ?? 90 }} {{ $t('min') }}
                </p>
            </div>
            <p v-if="error" role="alert" class="rounded-xl bg-red-50 p-4 text-red-700">{{ error }}</p>
            <section class="panel space-y-5">
                <div class="grid gap-4 sm:grid-cols-2">
                    <label class="field"
                        ><span class="field-label"
                            >{{ $t('booking_date')
                            }}<FieldHelp :label="$t('booking_date')" field="booking_date" /></span
                        ><input
                            v-model="date"
                            :aria-label="$t('booking_date')"
                            type="date"
                            :min="today"
                            required
                            class="rounded-lg border border-slate-300 p-3" /></label
                    ><label class="field"
                        ><span class="field-label"
                            >{{ $t('booking_guests')
                            }}<FieldHelp :label="$t('booking_guests')" field="booking_guests" /></span
                        ><InputNumber
                            v-model="party"
                            :aria-label="$t('booking_guests')"
                            fluid
                            :min="rules.min_party_size ?? 1"
                            :max="rules.max_party_size ?? 8"
                    /></label>
                </div>
                <Button :label="$t('booking_find_slots')" :loading="loading" @click="load" /><label class="field"
                    ><span class="field-label"
                        >{{ $t('booking_time') }}<FieldHelp :label="$t('booking_time')" field="booking_time" /></span
                    ><Select
                        v-model="slot"
                        :options="options"
                        option-label="label"
                        option-value="value"
                        :aria-label="$t('booking_time')"
                        :empty-message="$t('booking_no_slots')"
                /></label>
                <p class="muted text-sm">{{ $t('booking_availability_hint') }}</p>
            </section>
            <form class="panel space-y-5" @submit.prevent="submit">
                <label class="field"
                    ><span class="field-label"
                        >{{ $t('your_name') }}<FieldHelp :label="$t('your_name')" field="your_name" /></span
                    ><InputText
                        v-model="guest.guest_name"
                        :aria-label="$t('your_name')"
                        required
                        maxlength="150"
                        autocomplete="name" /></label
                ><label class="field"
                    ><span class="field-label"
                        >{{ $t('phone_number') }}<FieldHelp :label="$t('phone_number')" field="phone_number" /></span
                    ><InputText
                        v-model="guest.guest_phone"
                        :aria-label="$t('phone_number')"
                        required
                        type="tel"
                        maxlength="35"
                        autocomplete="tel" /></label
                ><label class="field"
                    ><span class="field-label">{{ $t('email') }}<FieldHelp :label="$t('email')" field="email" /></span
                    ><InputText
                        v-model="guest.guest_email"
                        :aria-label="$t('email')"
                        type="email"
                        maxlength="254"
                        autocomplete="email" /></label
                ><label class="field"
                    ><span class="field-label"
                        >{{ $t('order_notes') }}<FieldHelp :label="$t('order_notes')" field="order_notes" /></span
                    ><Textarea v-model="guest.notes" :aria-label="$t('order_notes')" rows="3" maxlength="1000"
                /></label>
                <p v-if="rules.guest_notes" class="rounded-xl bg-slate-50 p-4 text-sm">{{ rules.guest_notes }}</p>
                <p class="muted">
                    {{ rules.confirmation === 'automatic' ? $t('booking_automatic_hint') : $t('booking_manual_hint') }}
                </p>
                <Button type="submit" :label="$t('booking_submit')" :disabled="!slot" :loading="busy" />
            </form>
        </div>
    </StorefrontLayout>
</template>
