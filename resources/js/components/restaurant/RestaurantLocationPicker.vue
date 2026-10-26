<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import Button from 'primevue/button';
import DeliveryMap from '@/components/maps/DeliveryMap.vue';
import { api } from '@/lib/http';
import { tr } from '@/i18n';
import type { DeliveryPoint, MapConfig } from '@/lib/deliveryGeometry';
const props = defineProps<{
    locationId: string;
    address: string;
    city: string;
    country: string;
    postalCode: string;
    mapConfig: MapConfig;
}>();
const point = defineModel<DeliveryPoint | null>({ required: true });
interface Result extends DeliveryPoint {
    label: string;
    approximate: boolean;
}
const results = ref<Result[]>([]);
const busy = ref(false);
const error = ref('');
const notice = ref('');
const selected = ref<number | null>(null);
let request = 0;
const missing = computed(() =>
    [
        props.address?.trim().length >= 5 ? '' : tr('street_address'),
        props.city?.trim().length >= 2 ? '' : tr('setup_city'),
        /^[A-Z]{2}$/.test(props.country ?? '') ? '' : tr('setup_country'),
    ].filter(Boolean),
);
watch(
    () => [props.address, props.city, props.country, props.postalCode],
    () => {
        request++;
        busy.value = false;
        results.value = [];
        selected.value = null;
        notice.value = '';
        error.value = '';
        point.value = null;
    },
);
function choose(index: number) {
    const result = results.value[index];
    selected.value = index;
    point.value = { latitude: result.latitude, longitude: result.longitude };
    notice.value = result.approximate ? 'restaurant_location_approximate' : 'restaurant_location_found';
}
async function locate() {
    if (missing.value.length) {
        error.value = tr('restaurant_location_missing', { fields: missing.value.join(', ') });
        return;
    }
    const current = ++request;
    busy.value = true;
    error.value = '';
    notice.value = '';
    results.value = [];
    try {
        const response = await api<{ results: Result[] }>(`/app/locations/${props.locationId}/setup/geocode`, 'POST', {
            address: props.address,
            city: props.city,
            country: props.country,
            postal_code: props.postalCode,
        });
        if (current !== request) return;
        results.value = response.results;
        if (results.value.length === 1) choose(0);
        else if (!results.value.length) error.value = tr('restaurant_location_not_found');
        else notice.value = 'restaurant_location_choose';
    } catch (e) {
        if (current === request) error.value = (e as Error).message;
    } finally {
        if (current === request) busy.value = false;
    }
}
function move(value: DeliveryPoint) {
    point.value = value;
    selected.value = null;
    notice.value = 'restaurant_location_adjusted';
}
</script>
<template>
    <section
        class="space-y-4 rounded-xl border border-slate-200 bg-slate-50/60 p-4 dark:border-slate-700 dark:bg-slate-900 md:col-span-2"
        aria-labelledby="restaurant-location-title"
    >
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div>
                <h3 id="restaurant-location-title" class="font-semibold">{{ $t('restaurant_map_title') }}</h3>
                <p class="mt-1 text-sm text-slate-500">{{ $t('restaurant_location_intro') }}</p>
            </div>
            <Button
                type="button"
                :label="$t('restaurant_locate')"
                icon="pi pi-map-marker"
                :loading="busy"
                @click="locate"
            />
        </div>
        <p v-if="error" role="alert" class="rounded-lg bg-red-50 p-3 text-sm text-red-700">{{ error }}</p>
        <p v-if="notice" role="status" class="rounded-lg bg-violet-50 p-3 text-sm text-brand">{{ $t(notice) }}</p>
        <div v-if="results.length > 1" class="grid gap-2">
            <button
                v-for="(result, index) in results"
                :key="index"
                type="button"
                :aria-pressed="selected === index"
                class="rounded-lg border bg-white p-3 text-left text-sm dark:bg-slate-800"
                :class="selected === index ? 'border-brand text-brand' : 'border-slate-200'"
                @click="choose(index)"
            >
                <i class="pi pi-map-marker mr-2" aria-hidden="true" />{{ result.label }}
            </button>
        </div>
        <DeliveryMap mode="pick" purpose="restaurant" :point="point" :map-config="mapConfig" @update:point="move" />
        <div class="flex flex-wrap items-center justify-between gap-3">
            <p class="max-w-xl text-xs leading-relaxed text-slate-500">{{ $t('restaurant_location_save_hint') }}</p>
            <Button
                v-if="point"
                type="button"
                :label="$t('restaurant_location_remove')"
                text
                size="small"
                @click="
                    point = null;
                    notice = '';
                    selected = null;
                "
            />
        </div>
        <p class="text-xs leading-relaxed text-slate-400">
            {{ $t('restaurant_location_provider') }}
            <a href="https://photon.komoot.io/" target="_blank" rel="noreferrer" class="underline">Photon</a> ·
            <a href="https://www.openstreetmap.org/copyright" target="_blank" rel="noreferrer" class="underline"
                >© OpenStreetMap contributors</a
            >
        </p>
    </section>
</template>
