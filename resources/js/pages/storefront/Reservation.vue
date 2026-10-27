<script setup lang="ts">
import { onBeforeUnmount, onMounted, ref } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import StorefrontLayout from '@/layouts/StorefrontLayout.vue';
import Button from 'primevue/button';
import { api } from '@/lib/http';
import { useLocationDates } from '@/lib/locationDates';
import type { DisplaySettings } from '@/types';

const props = defineProps<{ reservationId: string }>();
const booking = ref<{
    menu_url: string;
    display_settings: DisplaySettings;
    public_id: string;
    status: string;
    version: number;
    starts_at: string;
    ends_at: string;
    party_size: number;
    timezone: string;
    restaurant: string;
    can_cancel: boolean;
    hold_expires_at: string | null;
} | null>(null);
const dates = useLocationDates(() => booking.value ?? undefined);
const token = ref('');
const error = ref('');
const busy = ref(false);
let timer: ReturnType<typeof setInterval> | null = null;
async function load() {
    if (!token.value) {
        error.value = 'booking_missing_token';
        return;
    }
    try {
        const current = await api<NonNullable<typeof booking.value>>(
            `/api/v1/public/reservations/${props.reservationId}`,
            'GET',
            undefined,
            {
                Authorization: `Bearer ${token.value}`,
            },
        );
        if (!booking.value || current.version >= booking.value.version) booking.value = current;
        error.value = '';
    } catch {
        error.value = 'booking_unavailable';
    }
}
async function cancel() {
    if (!booking.value) return;
    busy.value = true;
    try {
        booking.value = await api(
            `/api/v1/public/reservations/${props.reservationId}/cancel`,
            'POST',
            { version: booking.value.version },
            { Authorization: `Bearer ${token.value}` },
        );
    } catch (e) {
        error.value = (e as Error).message;
    } finally {
        busy.value = false;
    }
}
onMounted(() => {
    const fragment = new URLSearchParams(window.location.hash.slice(1)).get('token');
    if (fragment?.length === 64) sessionStorage.setItem(`zf-reservation-${props.reservationId}`, fragment);
    history.replaceState(null, '', window.location.pathname);
    token.value = sessionStorage.getItem(`zf-reservation-${props.reservationId}`) ?? '';
    void load();
    timer = setInterval(load, 15000);
});
onBeforeUnmount(() => {
    if (timer) clearInterval(timer);
});
</script>
<template>
    <StorefrontLayout :name="booking?.restaurant ?? 'ZionFood'"
        ><Head :title="$t('booking_details')" />
        <section class="panel mx-auto max-w-xl space-y-5">
            <h1 class="text-2xl font-semibold">{{ $t('booking_details') }}</h1>
            <p v-if="error" role="alert" class="text-red-700">{{ error.startsWith('booking_') ? $t(error) : error }}</p>
            <template v-if="booking"
                ><p class="rounded-xl bg-violet-50 p-4 font-semibold text-brand">
                    {{ $t(`reservation_status_${booking.status}`) }}
                </p>
                <p>
                    {{ dates.dateTime(booking.starts_at) }} ·
                    {{ booking.timezone }}
                </p>
                <p>{{ $t('booking_guests') }}: {{ booking.party_size }}</p>
                <p v-if="booking.status === 'pending' && booking.hold_expires_at" class="muted">
                    {{ $t('booking_hold_until') }}
                    {{ dates.time(booking.hold_expires_at) }}
                </p>
                <Link
                    :href="booking.menu_url"
                    class="inline-flex items-center gap-2 rounded-xl bg-brand px-5 py-3 font-semibold text-white"
                    ><i class="pi pi-arrow-left" aria-hidden="true" />{{ $t('booking_back_to_menu') }}</Link
                >
                <Button
                    v-if="booking.can_cancel"
                    :label="$t('booking_cancel')"
                    severity="danger"
                    outlined
                    :loading="busy"
                    @click="cancel"
            /></template></section
    ></StorefrontLayout>
</template>
