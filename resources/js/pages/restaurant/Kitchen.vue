<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';
import { Head } from '@inertiajs/vue3';
import KitchenLayout from '@/layouts/KitchenLayout.vue';
import ZFPageHeader from '@/components/ui/ZFPageHeader.vue';
import KitchenStatusColumns from '@/components/order/KitchenStatusColumns.vue';
import OrderDetailsDrawer from '@/components/order/OrderDetailsDrawer.vue';
import Button from 'primevue/button';
import Select from 'primevue/select';
import { useKitchen } from '@/stores/kitchen';
import { getEcho } from '@/lib/echo';
import type { Order, WorkspaceProps } from '@/types';
const props = defineProps<WorkspaceProps>();
const kitchen = useKitchen();
const now = ref(Date.now());
const selected = ref<Order | null>(null);
const drawer = ref(false);
const filter = ref('all');
const sound = ref(false);
let audio: AudioContext | null = null;
let timer: ReturnType<typeof setInterval>;
let ageTimer: ReturnType<typeof setInterval>;
const channel = `organization.${props.location.organization_id}.location.${props.location.id}.orders`;
const shown = computed(() => kitchen.orders.filter((o) => filter.value === 'all' || o.fulfillment === filter.value));
const sync = () => kitchen.sync(props.location.public_id);
function beep() {
    if (!sound.value || !audio) return;
    const oscillator = audio.createOscillator();
    const gain = audio.createGain();
    gain.gain.value = 0.08;
    oscillator.connect(gain);
    gain.connect(audio.destination);
    oscillator.frequency.value = 740;
    oscillator.start();
    oscillator.stop(audio.currentTime + 0.18);
}
async function toggleSound() {
    if (!audio) audio = new AudioContext();
    await audio.resume();
    sound.value = !sound.value;
    if (sound.value) beep();
}
watch(
    () => kitchen.orders.map((o) => o.public_id),
    (ids, previous) => {
        if (ids.some((id) => !previous.includes(id))) beep();
    },
);
function fullscreen() {
    void document.documentElement.requestFullscreen();
}
function visibility() {
    if (document.visibilityState === 'visible') void sync();
}
onMounted(() => {
    kitchen.reset();
    void sync();
    timer = setInterval(sync, 15000);
    ageTimer = setInterval(() => (now.value = Date.now()), 1000);
    window.addEventListener('online', sync);
    window.addEventListener('focus', sync);
    document.addEventListener('visibilitychange', visibility);
    const echo = getEcho();
    echo?.private(channel).listen('.order.changed', sync);
    echo?.connector.pusher.connection.bind('connected', sync);
});
onUnmounted(() => {
    clearInterval(timer);
    clearInterval(ageTimer);
    window.removeEventListener('online', sync);
    window.removeEventListener('focus', sync);
    document.removeEventListener('visibilitychange', visibility);
    const echo = getEcho();
    echo?.leave(channel);
    echo?.connector.pusher.connection.unbind('connected', sync);
    void audio?.close();
    kitchen.reset();
});
</script>
<template>
    <KitchenLayout v-bind="$props"
        ><Head title="Kitchen display" /><ZFPageHeader
            title="Keep the good food moving."
            :description="location.name + ' · Authoritative order queue'"
            ><div class="flex flex-wrap gap-2">
                <Button
                    :icon="sound ? 'pi pi-volume-up' : 'pi pi-volume-off'"
                    :label="sound ? 'Sound on' : 'Enable sound'"
                    outlined
                    @click="toggleSound"
                /><Button icon="pi pi-refresh" label="Sync" :loading="kitchen.loading" @click="sync" /><Button
                    icon="pi pi-window-maximize"
                    aria-label="Fullscreen kitchen"
                    outlined
                    @click="fullscreen"
                /></div
        ></ZFPageHeader>
        <div class="mb-5 flex flex-wrap items-center justify-between gap-3">
            <p class="muted" role="status">
                {{ kitchen.syncedAt ? 'Last synced ' + kitchen.syncedAt.toLocaleTimeString() : 'Connecting…' }} ·
                Automatic recovery every 15 seconds
            </p>
            <Select
                v-model="filter"
                :options="[
                    { label: 'All orders', value: 'all' },
                    { label: 'Pickup', value: 'pickup' },
                    { label: 'Delivery', value: 'delivery' },
                ]"
                option-label="label"
                option-value="value"
                aria-label="Fulfillment filter"
            />
        </div>
        <p v-if="kitchen.error" role="alert" class="mb-5 rounded-xl bg-red-50 p-4 text-red-700">
            Connection interrupted: {{ kitchen.error }}. Retrying automatically.
        </p>
        <KitchenStatusColumns
            :orders="shown"
            :now="now"
            @changed="sync"
            @details="
                selected = $event;
                drawer = true;
            " /><OrderDetailsDrawer v-model:visible="drawer" :order="selected"
    /></KitchenLayout>
</template>
