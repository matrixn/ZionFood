<script setup lang="ts">
import { ref } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import RestaurantLayout from '@/layouts/RestaurantLayout.vue';
import Button from 'primevue/button';
import { localeTag } from '@/i18n';
import type { WorkspaceProps } from '@/types';
interface Booking {
    public_id: string;
    guest_name: string;
    guest_phone: string;
    guest_email: string | null;
    notes: string | null;
    starts_at: string;
    ends_at: string;
    party_size: number;
    status: string;
    version: number;
    hold_expires_at: string | null;
    table: { code: string; area: string } | null;
}
const props = defineProps<
    WorkspaceProps & {
        reservations: { data: Booking[]; next_page_url: string | null; prev_page_url: string | null };
        date: string;
    }
>();
const date = ref(props.date);
const form = useForm({ status: '', version: 1 });
function actions(booking: Booking) {
    if (
        booking.status === 'pending' &&
        booking.hold_expires_at &&
        new Date(booking.hold_expires_at).getTime() < Date.now()
    )
        return [];
    return (
        (
            {
                pending: ['confirmed', 'rejected', 'cancelled'],
                confirmed: ['seated', 'cancelled', 'no_show'],
                seated: ['completed', 'cancelled'],
            } as Record<string, string[]>
        )[booking.status] ?? []
    );
}
function transition(booking: Booking, status: string) {
    form.status = status;
    form.version = booking.version;
    form.post(`/app/reservations/${booking.public_id}/status`, { preserveScroll: true });
}
</script>
<template>
    <RestaurantLayout v-bind="$props"
        ><Head :title="$t('booking_manage')" />
        <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
            <h1 class="text-3xl font-semibold">{{ $t('booking_manage') }}</h1>
            <div class="flex gap-3">
                <input
                    v-model="date"
                    type="date"
                    :aria-label="$t('booking_date')"
                    class="rounded-lg border p-3"
                    @change="router.get(`/app/locations/${location.public_id}/reservations`, { date })"
                /><Button :label="$t('booking_refresh')" icon="pi pi-refresh" outlined @click="router.reload()" />
            </div>
        </div>
        <p v-for="(message, key) in form.errors" :key="key" role="alert" class="mb-4 text-red-700">{{ message }}</p>
        <p v-if="!reservations.data.length" class="panel muted">{{ $t('booking_empty') }}</p>
        <div class="grid gap-4 lg:grid-cols-2">
            <article v-for="booking in reservations.data" :key="booking.public_id" class="panel space-y-3">
                <div class="flex flex-wrap justify-between gap-3">
                    <strong>{{ booking.guest_name }} · {{ booking.party_size }} {{ $t('booking_guests') }}</strong
                    ><span class="font-medium text-brand">{{
                        $t(
                            `reservation_status_${booking.status === 'pending' && booking.hold_expires_at && new Date(booking.hold_expires_at).getTime() < Date.now() ? 'expired' : booking.status}`,
                        )
                    }}</span>
                </div>
                <p>
                    {{
                        new Date(booking.starts_at).toLocaleTimeString(localeTag(), {
                            timeZone: location.timezone,
                            hour: '2-digit',
                            minute: '2-digit',
                        })
                    }}–{{
                        new Date(booking.ends_at).toLocaleTimeString(localeTag(), {
                            timeZone: location.timezone,
                            hour: '2-digit',
                            minute: '2-digit',
                        })
                    }}<span v-if="booking.table"> · {{ booking.table.area }} / {{ booking.table.code }}</span>
                </p>
                <p class="muted">{{ booking.guest_phone }} {{ booking.guest_email }}</p>
                <p v-if="booking.notes" class="rounded-lg bg-amber-50 p-3 text-sm">{{ booking.notes }}</p>
                <div class="flex flex-wrap gap-2">
                    <Button
                        v-for="status in permissions.reservations === 'manage' ? actions(booking) : []"
                        :key="status"
                        :label="$t(`booking_action_${status}`)"
                        :loading="form.processing"
                        size="small"
                        outlined
                        @click="transition(booking, status)"
                    />
                </div>
            </article>
        </div>
        <div class="mt-6 flex gap-6">
            <Link v-if="reservations.prev_page_url" :href="reservations.prev_page_url">{{ $t('previous') }}</Link
            ><Link v-if="reservations.next_page_url" :href="reservations.next_page_url">{{ $t('next') }}</Link>
        </div></RestaurantLayout
    >
</template>
