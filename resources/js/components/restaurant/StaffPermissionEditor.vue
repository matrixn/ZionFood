<script setup lang="ts">
import { computed } from 'vue';
import Select from 'primevue/select';
import { tr } from '@/i18n';
import type { AccessLevel, FeaturePermissions, StaffFeature } from '@/types';
const value = defineModel<FeaturePermissions>({ required: true });
const props = defineProps<{ ceiling: FeaturePermissions }>();
const features: StaffFeature[] = ['orders', 'reservations', 'tables', 'kitchen', 'menu'];
const ranks: Record<AccessLevel, number> = { none: 0, view: 1, manage: 2 };
const options = computed(() =>
    Object.fromEntries(
        features.map((feature) => [
            feature,
            (['none', 'view', 'manage'] as AccessLevel[])
                .filter((level) => ranks[level] <= ranks[props.ceiling[feature] ?? 'none'])
                .map((level) => ({ value: level, label: tr('permission_' + level) })),
        ]),
    ),
);
function change(feature: StaffFeature, level: AccessLevel) {
    value.value = { ...value.value, [feature]: level };
}
</script>
<template>
    <fieldset
        class="grid min-w-0 gap-3 rounded-xl border border-slate-200 p-4"
        data-testid="staff-permissions"
        style="grid-template-columns: repeat(auto-fit, minmax(min(100%, 14rem), 1fr))"
    >
        <legend class="px-2 text-sm font-semibold">{{ $t('permissions_title') }}</legend>
        <label v-for="feature in features" :key="feature" class="field min-w-0"
            ><span class="field-label"
                >{{ $t('permission_feature_' + feature)
                }}<FieldHelp :label="$t('permission_feature_' + feature)" field="permission_feature_" /></span
            ><Select
                :model-value="value[feature]"
                :options="options[feature]"
                :aria-label="$t('permission_feature_' + feature)"
                option-label="label"
                option-value="value"
                @update:model-value="change(feature, $event)"
        /></label>
    </fieldset>
</template>
