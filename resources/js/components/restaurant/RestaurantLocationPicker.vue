<script setup lang="ts">
import { computed, nextTick, onBeforeUnmount, ref, watch } from 'vue';
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
interface PinAddress {
    address: string;
    city: string;
    country: string;
    postal_code: string;
    missing: string[];
}
const emit = defineEmits<{ address: [value: PinAddress] }>();
const point = defineModel<DeliveryPoint | null>({ required: true });
interface Result extends DeliveryPoint {
    label: string;
    approximate: boolean;
}
const results = ref<Result[]>([]);
const busy = ref(false);
const reversing = ref(false);
const incomplete = ref('');
let appliedAddress: string | null = null;
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
    (values) => {
        if (JSON.stringify(values) === appliedAddress) {
            appliedAddress = null;
            return;
        }
        request++;
        busy.value = false;
        reversing.value = false;
        incomplete.value = '';
        results.value = [];
        selected.value = null;
        notice.value = '';
        error.value = '';
        point.value = null;
    },
);
function choose(index: number) {
    request++;
    busy.value = false;
    reversing.value = false;
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
    incomplete.value = '';
    reversing.value = false;
    selected.value = null;
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
async function fromPin() {
    if (!point.value) return;
    const source = { ...point.value };
    const current = ++request;
    reversing.value = true;
    busy.value = false;
    results.value = [];
    selected.value = null;
    error.value = '';
    notice.value = '';
    incomplete.value = '';
    try {
        const response = await api<{ address: PinAddress | null }>(
            `/app/locations/${props.locationId}/setup/reverse-geocode`,
            'POST',
            source,
        );
        if (current !== request || JSON.stringify(source) !== JSON.stringify(point.value)) return;
        if (!response.address) {
            error.value = tr('restaurant_pin_not_found');
            return;
        }
        const address = response.address;
        appliedAddress = JSON.stringify([address.address, address.city, address.country, address.postal_code]);
        emit('address', address);
        await nextTick();
        appliedAddress = null;
        notice.value = 'restaurant_pin_applied';
        const fields: Record<string, string> = {
            address: 'street_address',
            house_number: 'restaurant_house_number',
            city: 'setup_city',
            country: 'setup_country',
            postal_code: 'setup_postal',
        };
        if (address.missing.length)
            incomplete.value = tr('restaurant_pin_incomplete', {
                fields: address.missing.map((key) => tr(fields[key] ?? key)).join(', '),
            });
    } catch (e) {
        if (current === request) error.value = (e as Error).message;
    } finally {
        if (current === request) reversing.value = false;
    }
}
function remove() {
    request++;
    busy.value = false;
    reversing.value = false;
    point.value = null;
    notice.value = '';
    incomplete.value = '';
    selected.value = null;
}
onBeforeUnmount(() => request++);
function move(value: DeliveryPoint) {
    request++;
    busy.value = false;
    reversing.value = false;
    error.value = '';
    incomplete.value = '';
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
        <p v-if="incomplete" role="status" class="rounded-lg bg-amber-50 p-3 text-sm text-amber-900">
            {{ incomplete }}
        </p>
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
        <Button
            type="button"
            :label="$t('restaurant_pin_address')"
            icon="pi pi-map-marker"
            :disabled="!point || busy"
            :loading="reversing"
            outlined
            @click="fromPin"
        />
        <div class="flex flex-wrap items-center justify-between gap-3">
            <p class="max-w-xl text-xs leading-relaxed text-slate-500">{{ $t('restaurant_location_save_hint') }}</p>
            <Button
                v-if="point"
                type="button"
                :label="$t('restaurant_location_remove')"
                text
                size="small"
                @click="remove"
            />
        </div>
        <p class="text-xs leading-relaxed text-slate-400">
            {{ $t('restaurant_location_provider') }}
            <a href="https://photon.komoot.io/" target="_blank" rel="noreferrer" class="underline">Photon</a> Â·
            <a href="https://www.openstreetmap.org/copyright" target="_blank" rel="noreferrer" class="underline"
                >© OpenStreetMap contributors</a
            >
        </p>
    </section>
</template>
