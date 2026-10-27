<script setup lang="ts">
import { useSetupEditorGuard } from '@/lib/useSetupEditorGuard';
import { api } from '@/lib/http';
import { tr as t } from '@/i18n';

import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import DeliveryMap from '@/components/maps/DeliveryMap.vue';
import type { MapConfig, DeliveryPolygon } from '@/lib/deliveryGeometry';
import { Head, useForm } from '@inertiajs/vue3';
import RestaurantLayout from '@/layouts/RestaurantLayout.vue';
import ZFPageHeader from '@/components/ui/ZFPageHeader.vue';
import InputText from 'primevue/inputtext';
import InputNumber from 'primevue/inputnumber';
import ToggleSwitch from 'primevue/toggleswitch';
import Select from 'primevue/select';
import Button from 'primevue/button';
import type { WorkspaceProps } from '@/types';
const props = defineProps<
    WorkspaceProps & {
        editorSection?: 'hours' | 'delivery';
        mapCenter: number[] | null;
        mapConfig: MapConfig;
        mapAddress: { address: string; city: string; country: string; postal_code: string };
    }
>();
const selectedZone = ref(0);
const restaurantCenter = ref(props.mapCenter);
const locating = ref(false);
const mapMessage = ref('');
const mapError = ref('');
const addressMatches = ref<{ latitude: number; longitude: number; label: string }[]>([]);
let disposed = false;
onBeforeUnmount(() => {
    disposed = true;
});
onMounted(async () => {
    if (props.editorSection === 'hours' || restaurantCenter.value) return;
    if (
        props.mapAddress.address.trim().length < 5 ||
        props.mapAddress.city.trim().length < 2 ||
        !/^[A-Z]{2}$/.test(props.mapAddress.country)
    ) {
        mapMessage.value = 'delivery_address_missing';
        return;
    }
    locating.value = true;
    try {
        const response = await api<{ results: typeof addressMatches.value }>(
            `/app/locations/${props.location.public_id}/setup/geocode`,
            'POST',
            props.mapAddress,
        );
        if (disposed) return;
        if (response.results.length === 1) {
            restaurantCenter.value = [response.results[0].latitude, response.results[0].longitude];
            mapMessage.value = 'delivery_address_centered';
        } else if (response.results.length > 1) {
            addressMatches.value = response.results;
            mapMessage.value = 'delivery_address_choose';
        } else mapMessage.value = 'restaurant_location_not_found';
    } catch (error) {
        if (!disposed) mapError.value = (error as Error).message;
    } finally {
        if (!disposed) locating.value = false;
    }
});
const days = computed(() => [
    t('monday'),
    t('tuesday'),
    t('wednesday'),
    t('thursday'),
    t('friday'),
    t('saturday'),
    t('sunday'),
]);
const form = useForm({
    name: props.location.name,
    address: props.location.address,
    accepting_orders: props.location.accepting_orders,
    pickup_enabled: props.location.fulfillment.pickup_enabled,
    delivery_enabled: props.location.fulfillment.delivery_enabled,
    dine_in_cutoff_minutes: props.location.fulfillment.dine_in_cutoff_minutes ?? 30,
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
        geometry: z.geometry ?? (null as DeliveryPolygon | null),
        priority: z.priority ?? 0,
    })),
});
useSetupEditorGuard(
    () => !!props.embedded,
    () => form.isDirty,
);
</script>
<template>
    <RestaurantLayout v-bind="$props"
        ><Head v-if="!embedded" :title="$t('location_settings')" /><ZFPageHeader
            :title="$t('set_the_pace')"
            :description="$t('opening_hours_fulfillment_and_your_location_details')" />
        <form
            class="grid gap-6"
            @submit.prevent="
                form.put('/app/locations/' + location.public_id + '/settings', {
                    preserveScroll: true,
                    onSuccess: () => form.defaults(),
                })
            "
        >
            <div v-if="Object.keys(form.errors).length" role="alert" class="panel text-red-600">
                <p v-for="(message, key) in form.errors" :key="key">{{ message }}</p>
            </div>
            <section v-if="!editorSection" class="panel grid gap-5">
                <h2 class="text-xl font-semibold">{{ $t('location') }}</h2>
                <label class="field"
                    ><span class="field-label">{{ $t('name') }}<FieldHelp :label="$t('name')" field="name" /></span
                    ><InputText v-model="form.name" :aria-label="$t('name')" required /></label
                ><label class="field"
                    ><span class="field-label"
                        >{{ $t('address') }}<FieldHelp :label="$t('address')" field="address" /></span
                    ><InputText v-model="form.address" :aria-label="$t('address')" required /></label
                ><label class="flex items-center gap-3"
                    ><ToggleSwitch v-model="form.accepting_orders" :aria-label="$t('accepting_online_orders')" /><span
                        class="field-label"
                        >{{ $t('accepting_online_orders')
                        }}<FieldHelp :label="$t('accepting_online_orders')" field="accepting_online_orders" /></span
                ></label>
                <div class="flex flex-wrap gap-8">
                    <label class="flex items-center gap-3"
                        ><ToggleSwitch v-model="form.pickup_enabled" :aria-label="$t('pickup')" /><span
                            class="field-label"
                            >{{ $t('pickup') }}<FieldHelp :label="$t('pickup')" field="pickup" /></span></label
                    ><label class="flex items-center gap-3"
                        ><ToggleSwitch v-model="form.delivery_enabled" :aria-label="$t('delivery')" /><span
                            class="field-label"
                            >{{ $t('delivery') }}<FieldHelp :label="$t('delivery')" field="delivery" /></span
                    ></label>
                </div>
            </section>
            <section v-if="!editorSection || editorSection === 'hours'" class="panel">
                <h2 class="mb-5 text-xl font-semibold">{{ $t('opening_hours') }}</h2>
                <label v-if="permissions.tables !== 'none'" class="field mb-4"
                    ><span class="field-label"
                        >{{ $t('dining_cutoff_minutes')
                        }}<FieldHelp :label="$t('dining_cutoff_minutes')" field="dining_cutoff_minutes" /></span
                    ><InputNumber
                        v-model="form.dine_in_cutoff_minutes"
                        :aria-label="$t('dining_cutoff_minutes')"
                        :disabled="permissions.tables !== 'manage'"
                        fluid
                        :min="0"
                        :max="240"
                        :step="15"
                /></label>
                <p class="muted mb-4 text-sm">{{ $t('dining_cutoff_hint') }}</p>
                <p class="muted mb-4">
                    {{ location.timezone }} {{ $t('omit_closed_days_overnight_opening_is_not_supported') }}
                </p>
                <div v-for="(h, i) in form.hours" :key="i" class="mb-3 flex flex-wrap items-center gap-3">
                    <span class="field-control-help"
                        ><Select
                            v-model="h.weekday"
                            :options="days.map((name, n) => ({ name, id: n + 1 }))"
                            option-label="name"
                            option-value="id"
                            :aria-label="$t('weekday')"
                            class="w-44" /><FieldHelp :label="$t('weekday')" field="weekday" kind="Select" /></span
                    ><span class="field-control-help"
                        ><input v-model="h.opens_at" type="time" :aria-label="$t('opening_time')" required /><FieldHelp
                            :label="$t('opening_time')"
                            field="opens_at"
                            kind="input" /></span
                    ><span>{{ $t('to') }}</span
                    ><span class="field-control-help"
                        ><input v-model="h.closes_at" type="time" :aria-label="$t('closing_time')" required /><FieldHelp
                            :label="$t('closing_time')"
                            field="closes_at"
                            kind="input" /></span
                    ><Button
                        icon="pi pi-times"
                        text
                        :aria-label="$t('remove_opening_day')"
                        @click="form.hours.splice(i, 1)"
                    />
                </div>
                <Button
                    :label="$t('add_day')"
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
            <section v-if="!editorSection || editorSection === 'delivery'" class="panel">
                <h2 class="mb-2 text-xl font-semibold">{{ $t('delivery_zones') }}</h2>
                <p class="muted mb-4">{{ $t('map_delivery_policy') }}</p>
                <div v-if="form.zones.length" class="mb-6 space-y-4">
                    <label class="field"
                        ><span class="field-label"
                            >{{ $t('map_selected_zone')
                            }}<FieldHelp :label="$t('map_selected_zone')" field="map_selected_zone" /></span
                        ><Select
                            v-model="selectedZone"
                            :options="
                                form.zones.map((zone, index) => ({
                                    name: zone.name || `${$t('delivery_zone')} ${index + 1}`,
                                    id: index,
                                }))
                            "
                            option-label="name"
                            option-value="id"
                            :aria-label="$t('map_selected_zone')"
                    /></label>
                    <p v-if="locating" role="status" class="my-3 text-sm text-slate-500">
                        {{ $t('delivery_address_locating') }}
                    </p>
                    <p v-if="mapMessage" role="status" class="my-3 text-sm text-slate-500">{{ $t(mapMessage) }}</p>
                    <p v-if="mapError" role="alert" class="my-3 text-sm text-red-700">{{ mapError }}</p>
                    <div v-if="addressMatches.length" class="my-3 grid gap-2">
                        <Button
                            v-for="match in addressMatches"
                            :key="match.label"
                            type="button"
                            outlined
                            :label="match.label"
                            @click="
                                restaurantCenter = [match.latitude, match.longitude];
                                addressMatches = [];
                                mapMessage = 'delivery_address_centered';
                            "
                        />
                    </div>
                    <DeliveryMap
                        v-if="form.zones[selectedZone]"
                        :key="selectedZone"
                        v-model:geometry="form.zones[selectedZone].geometry"
                        mode="draw"
                        :map-config="mapConfig"
                        :center="restaurantCenter ?? undefined"
                    />
                </div>
                <div
                    v-for="(z, i) in form.zones"
                    :key="i"
                    class="mb-5 grid min-w-0 items-end gap-3"
                    :class="embedded ? 'md:grid-cols-2' : 'lg:grid-cols-5'"
                >
                    <label class="field"
                        ><span class="field-label">{{ $t('name') }}<FieldHelp :label="$t('name')" field="name" /></span
                        ><InputText v-model="z.name" :aria-label="$t('name')" required /></label
                    ><label class="field"
                        ><span class="field-label"
                            >{{ $t('postal_prefix')
                            }}<FieldHelp :label="$t('postal_prefix')" field="postal_prefix" /></span
                        ><InputText
                            v-model="z.postal_prefix"
                            :aria-label="$t('postal_prefix')"
                            :required="!z.geometry" /></label
                    ><label class="field"
                        ><span class="field-label"
                            >{{ $t('fee_bani') }}<FieldHelp :label="$t('fee_bani')" field="fee_bani" /></span
                        ><InputNumber v-model="z.fee" :aria-label="$t('fee_bani')" fluid :min="0" /></label
                    ><label class="field"
                        ><span class="field-label"
                            >{{ $t('minimum_bani')
                            }}<FieldHelp :label="$t('minimum_bani')" field="minimum_bani" /></span
                        ><InputNumber
                            v-model="z.minimum_order"
                            :aria-label="$t('minimum_bani')"
                            fluid
                            :min="0" /></label
                    ><label class="field"
                        ><span class="field-label"
                            >{{ $t('map_priority')
                            }}<FieldHelp :label="$t('map_priority')" field="map_priority" /></span
                        ><InputNumber
                            v-model="z.priority"
                            :aria-label="$t('map_priority')"
                            fluid
                            :min="0"
                            :max="1000" /></label
                    ><Button
                        :label="$t('remove')"
                        text
                        severity="danger"
                        @click="
                            form.zones.splice(i, 1);
                            selectedZone = 0;
                        "
                    /><span class="text-xs font-medium text-brand">{{
                        z.geometry ? $t('map_polygon_saved') : $t('map_legacy_zone')
                    }}</span>
                </div>
                <Button
                    :label="$t('add_zone')"
                    icon="pi pi-plus"
                    text
                    @click="
                        form.zones.push({
                            name: '',
                            postal_prefix: '',
                            fee: 0,
                            minimum_order: 0,
                            geometry: null,
                            priority: form.zones.length,
                        });
                        selectedZone = form.zones.length - 1;
                    "
                />
            </section>
            <Button
                type="submit"
                :label="$t('save_settings')"
                :loading="form.processing"
                class="justify-self-start"
            /></form
    ></RestaurantLayout>
</template>
