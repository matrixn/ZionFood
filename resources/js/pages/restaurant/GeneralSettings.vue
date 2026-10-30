<script setup lang="ts">
import { computed, watch } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import RestaurantLayout from '@/layouts/RestaurantLayout.vue';
import Button from 'primevue/button';
import InputNumber from 'primevue/inputnumber';
import Select from 'primevue/select';
import ToggleSwitch from 'primevue/toggleswitch';
import { dateFormats, dateTimeFormats, locationDate } from '@/lib/locationDates';
import { tr as t } from '@/i18n';
import type { WorkspaceProps, DisplaySettings } from '@/types';
const props = defineProps<WorkspaceProps & { embedded?: boolean }>();
const defaults: DisplaySettings = {
    date_format: 'd.m.Y',
    datetime_format: 'd.m.Y H:i',
    show_phone: true,
    show_email: true,
    show_website: true,
    show_address: true,
    show_description: true,
    show_product_images: true,
};
const form = useForm({
    ...defaults,
    ...props.location.display_settings,
    block_unavailable_online_orders: props.location.ordering_settings?.block_unavailable_online_orders ?? true,
    communication_logs_enabled: props.location.restaurant.communication_logs_enabled ?? false,
    scheduled_orders_enabled: props.location.commerce_settings?.scheduled_orders_enabled ?? false,
    reservation_preorders_enabled: props.location.commerce_settings?.reservation_preorders_enabled ?? false,
    scheduling_notice_minutes: props.location.commerce_settings?.scheduling_notice_minutes ?? 30,
    scheduling_days: props.location.commerce_settings?.scheduling_days ?? 7,
    preparation_minutes: props.location.commerce_settings?.preparation_minutes ?? 30,
    scheduled_orders_per_slot: props.location.commerce_settings?.scheduled_orders_per_slot ?? 10,
    revision: props.location.preferences_revision ?? 0,
});
watch(
    () => props.location,
    (location) => {
        form.revision = location.preferences_revision ?? 0;
    },
);
const example = '2026-10-27T13:05:09Z';
const dateOptions = computed(() =>
    dateFormats.map((value) => ({
        value,
        label: locationDate(
            example,
            { timezone: props.location.timezone, display_settings: { ...form.data(), date_format: value } },
            'date',
        ),
    })),
);
const datetimeOptions = computed(() =>
    dateTimeFormats.map((value) => ({
        value,
        label: locationDate(example, {
            timezone: props.location.timezone,
            display_settings: { ...form.data(), datetime_format: value },
        }),
    })),
);
const flags = [
    'show_phone',
    'show_email',
    'show_website',
    'show_address',
    'show_description',
    'show_product_images',
] as const;
function save() {
    form.put(`/app/locations/${props.location.public_id}/general-settings`, { preserveScroll: true });
}
</script>
<template>
    <component :is="embedded ? 'section' : RestaurantLayout" v-bind="embedded ? {} : $props">
        <Head v-if="!embedded" :title="$t('general_title')" />
        <div class="mx-auto max-w-3xl space-y-6">
            <div v-if="!embedded">
                <h1 class="text-3xl font-semibold">{{ $t('general_title') }}</h1>
                <p class="muted mt-2">{{ $t('general_help') }}</p>
            </div>
            <form class="panel space-y-6" @submit.prevent="save">
                <div class="grid gap-5 sm:grid-cols-2">
                    <label class="field"
                        ><span class="field-label"
                            >{{ $t('general_date_format')
                            }}<FieldHelp :label="$t('general_date_format')" :text="$t('general_date_help')" /></span
                        ><Select
                            v-model="form.date_format"
                            :aria-label="$t('general_date_format')"
                            :options="dateOptions"
                            option-label="label"
                            option-value="value"
                    /></label>
                    <label class="field"
                        ><span class="field-label"
                            >{{ $t('general_datetime_format')
                            }}<FieldHelp :label="$t('general_datetime_format')" :text="$t('general_date_help')" /></span
                        ><Select
                            v-model="form.datetime_format"
                            :aria-label="$t('general_datetime_format')"
                            :options="datetimeOptions"
                            option-label="label"
                            option-value="value"
                    /></label>
                </div>
                <p class="muted text-sm">{{ $t('general_timezone') }}: {{ location.timezone }}</p>
                <section class="space-y-3 rounded-xl border border-slate-200 p-4 dark:border-slate-700">
                    <h2 class="text-lg font-semibold">{{ $t('ordering_settings_title') }}</h2>
                    <label class="flex items-start gap-3"
                        ><ToggleSwitch
                            v-model="form.block_unavailable_online_orders"
                            class="shrink-0"
                            :aria-label="$t('ordering_block_setting')" /><span class="text-sm">{{
                            $t('ordering_block_setting')
                        }}</span
                        ><FieldHelp :label="$t('ordering_block_setting')" :text="$t('ordering_block_help')"
                    /></label>
                    <p class="muted text-sm">{{ $t('ordering_block_explanation') }}</p>
                </section>
                <section class="space-y-4 rounded-xl border border-slate-200 p-4">
                    <h2 class="text-lg font-semibold">{{ $t('commerce_title') }}</h2>
                    <label
                        v-for="key in ['scheduled_orders_enabled', 'reservation_preorders_enabled'] as const"
                        :key="key"
                        class="flex items-center gap-3"
                        ><ToggleSwitch
                            v-model="form[key]"
                            :disabled="permissions?.orders !== 'manage'"
                            :aria-label="$t(key)" /><span>{{ $t(key) }}</span
                        ><FieldHelp :label="$t(key)" :text="$t(key + '_help')"
                    /></label>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <label
                            v-for="key in [
                                'scheduling_notice_minutes',
                                'scheduling_days',
                                'preparation_minutes',
                                'scheduled_orders_per_slot',
                            ] as const"
                            :key="key"
                            class="field"
                            ><span class="field-label"
                                >{{ $t(key) }}<FieldHelp :label="$t(key)" :text="$t(key + '_help')" /></span
                            ><InputNumber
                                v-model="form[key]"
                                :disabled="permissions?.orders !== 'manage'"
                                :aria-label="$t(key)"
                                :min="1"
                                :max="key === 'scheduling_days' ? 90 : 1440"
                                :use-grouping="false"
                        /></label>
                    </div>
                </section>
                <label class="flex items-center gap-3"
                    ><ToggleSwitch v-model="form.communication_logs_enabled" :aria-label="$t('comm_enable')" /><span>{{
                        $t('comm_enable')
                    }}</span
                    ><FieldHelp :label="$t('comm_enable')" :text="$t('comm_enable_help')"
                /></label>
                <h2 class="text-lg font-semibold">{{ $t('general_public_display') }}</h2>
                <div class="grid gap-4 sm:grid-cols-2">
                    <label v-for="flag in flags" :key="flag" class="flex items-center gap-3"
                        ><ToggleSwitch v-model="form[flag]" :aria-label="t('general_' + flag)" /><span
                            class="text-sm"
                            >{{ $t('general_' + flag) }}</span
                        ><FieldHelp :label="t('general_' + flag)" :text="$t('general_display_help')"
                    /></label>
                </div>
                <p v-for="(message, key) in form.errors" :key="key" role="alert" class="text-red-700">{{ message }}</p>
                <p v-if="form.recentlySuccessful" role="status" class="text-emerald-700">{{ $t('general_saved') }}</p>
                <Button type="submit" :label="$t('save')" :loading="form.processing" :disabled="form.processing" />
            </form>
        </div>
    </component>
</template>
