<script setup lang="ts">
import { computed } from 'vue';
import Checkbox from 'primevue/checkbox';
import ZFFormField from '@/components/ui/ZFFormField.vue';
import ImportZonesPreview from '@/components/maps/ImportZonesPreview.vue';
import type { MapConfig } from '@/lib/deliveryGeometry';
import type { ImportedAddress, ImportedHours, ImportedSettingPreview, ImportedZone } from '@/lib/migrationSettings';
import { money } from '@/lib/http';
const props = defineProps<{
    fields: ImportedSettingPreview[];
    readonly: boolean;
    canManageOrders: boolean;
    currency: string;
    mapConfig: MapConfig;
}>();
const selected = defineModel<string[]>({ required: true });
const days = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];
const checked = computed(() => new Set(selected.value));
function allowed(field: ImportedSettingPreview) {
    return field.available && (!['hours', 'zones'].includes(field.key) || props.canManageOrders);
}
</script>
<template>
    <section class="panel space-y-5" data-testid="migration-settings-review">
        <div>
            <h3 class="text-lg font-semibold">{{ $t('mig_settings_title') }}</h3>
            <p class="mt-2 text-sm text-slate-500">{{ $t('mig_settings_intro') }}</p>
        </div>
        <article
            v-for="field in fields"
            :key="field.key"
            :data-setting="field.key"
            class="rounded-2xl border p-4"
            :class="
                checked.has(field.key)
                    ? 'border-violet-300 bg-violet-50/40 dark:bg-violet-950/20'
                    : 'border-slate-200 dark:border-slate-700'
            "
        >
            <ZFFormField :label="$t('mig_setting_' + field.key)" :help="$t('mig_setting_' + field.key + '_help')">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <label class="inline-flex items-center gap-2 text-sm"
                        ><Checkbox
                            v-model="selected"
                            :value="field.key"
                            :disabled="readonly || !allowed(field)"
                            :aria-label="$t('mig_select_' + field.key)"
                        /><span>{{ $t('mig_select_' + field.key) }}</span></label
                    >
                    <span class="text-xs text-slate-500">{{
                        $t(field.changed ? 'mig_settings_changed' : 'mig_settings_same')
                    }}</span>
                </div>
            </ZFFormField>
            <p v-if="!field.available" role="status" class="mt-2 text-sm text-amber-800 dark:text-amber-200">
                {{ $t('mig_settings_error_' + field.error) }}
            </p>
            <p v-else-if="!allowed(field)" class="mt-2 text-sm text-amber-800">
                {{ $t('mig_settings_orders_permission') }}
            </p>
            <p v-if="['name', 'phone', 'logo'].includes(field.key)" class="mt-3 text-xs text-slate-500">
                {{ $t('mig_settings_shared') }}: {{ field.affected_locations.join(', ') }}
            </p>
            <p v-if="field.key === 'zones'" class="mt-3 text-xs text-slate-500">
                {{ $t('mig_settings_zones_add_only') }} <strong>{{ field.added_zones }}</strong>
            </p>
            <div class="mt-4 grid min-w-0 gap-3 md:grid-cols-2">
                <div
                    v-for="side in ['current', 'proposed'] as const"
                    :key="side"
                    class="min-w-0 break-words rounded-xl border border-slate-200 bg-white p-3 text-sm dark:border-slate-700 dark:bg-slate-900"
                >
                    <h4 class="mb-3 text-xs font-semibold uppercase tracking-wide text-slate-500">
                        {{ $t(side === 'current' ? 'mig_settings_current' : 'mig_settings_proposed') }}
                    </h4>
                    <template v-if="field[side] === null"
                        ><p class="text-slate-400">{{ $t('mig_settings_empty') }}</p></template
                    >
                    <template v-else-if="field.key === 'logo'">
                        <div
                            class="flex h-40 items-center justify-center rounded-xl border border-slate-100 bg-white p-4"
                        >
                            <img
                                v-if="side === 'current' ? field.current_image_url : field.proposed_image_url"
                                :src="(side === 'current' ? field.current_image_url : field.proposed_image_url)!"
                                :alt="$t(side === 'current' ? 'mig_logo_current_alt' : 'mig_logo_proposed_alt')"
                                class="max-h-full max-w-full object-contain"
                            />
                        </div>
                        <p class="mt-2 text-xs text-slate-500">{{ $t('mig_logo_preserved') }}</p>
                    </template>
                    <template v-else-if="field.key === 'hours'">
                        <p class="mb-2 font-medium">{{ (field[side] as ImportedHours).timezone }}</p>
                        <dl class="grid gap-1">
                            <div v-for="(day, index) in days" :key="day" class="flex justify-between gap-2">
                                <dt>{{ $t(day) }}</dt>
                                <dd>
                                    {{
                                        (field[side] as ImportedHours).hours.find((h) => h.weekday === index + 1)
                                            ?.opens_at ?? $t('mig_settings_closed')
                                    }}<template
                                        v-if="(field[side] as ImportedHours).hours.some((h) => h.weekday === index + 1)"
                                        >–{{
                                            (field[side] as ImportedHours).hours.find((h) => h.weekday === index + 1)
                                                ?.closes_at
                                        }}</template
                                    >
                                </dd>
                            </div>
                        </dl>
                    </template>
                    <template v-else-if="field.key === 'address'">
                        <p>{{ (field[side] as ImportedAddress).address }}</p>
                        <p class="mt-1 text-slate-500">
                            {{
                                [
                                    (field[side] as ImportedAddress).city,
                                    (field[side] as ImportedAddress).postal_code,
                                    (field[side] as ImportedAddress).country,
                                ]
                                    .filter(Boolean)
                                    .join(' · ')
                            }}
                        </p>
                        <p class="mt-2 text-xs text-slate-500">
                            {{ $t('mig_settings_pin') }}:
                            {{
                                (field[side] as ImportedAddress).latitude !== null
                                    ? `${(field[side] as ImportedAddress).latitude}, ${(field[side] as ImportedAddress).longitude}`
                                    : $t('mig_settings_empty')
                            }}
                        </p>
                    </template>
                    <template v-else-if="field.key === 'zones'">
                        <p v-if="!(field[side] as ImportedZone[]).length" class="text-slate-400">
                            {{ $t('mig_settings_empty') }}
                        </p>
                        <div
                            v-for="(zone, index) in field[side] as ImportedZone[]"
                            :key="index"
                            class="border-b border-slate-100 py-2 last:border-0 dark:border-slate-700"
                        >
                            <p class="font-medium">
                                {{ zone.name }}
                                <span class="text-xs text-slate-500">{{
                                    $t(zone.is_active ? 'mig_settings_zone_active' : 'mig_settings_zone_inactive')
                                }}</span>
                            </p>
                            <p v-if="zone.postal_prefix" class="mt-1 text-xs">
                                {{ $t('postal_prefix') }}: {{ zone.postal_prefix }}
                            </p>
                            <p class="mt-1 text-xs">
                                {{ $t('mig_settings_zone_fee') }} {{ money(zone.fee, currency) }} ·
                                {{ $t('mig_settings_zone_min') }} {{ money(zone.minimum_order, currency) }}
                            </p>
                        </div>
                        <ImportZonesPreview
                            v-if="(field[side] as ImportedZone[]).some((z) => z.geometry)"
                            :zones="field[side] as ImportedZone[]"
                            :map-config="mapConfig"
                            :label="
                                $t('mig_settings_zone_map') +
                                ' · ' +
                                $t(side === 'current' ? 'mig_settings_current' : 'mig_settings_proposed')
                            "
                        />
                    </template>
                    <p v-else class="break-words">{{ field[side] || $t('mig_settings_empty') }}</p>
                    <p
                        v-if="
                            field.key === 'phone' &&
                            side === 'current' &&
                            field.current_setup_phone &&
                            field.current_setup_phone !== field.current
                        "
                        class="mt-2 text-xs text-slate-500"
                    >
                        {{ $t('mig_settings_setup_phone') }}: {{ field.current_setup_phone }}
                    </p>
                </div>
            </div>
        </article>
    </section>
</template>
