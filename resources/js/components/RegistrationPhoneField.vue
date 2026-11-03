<script setup lang="ts">
import { computed, ref } from 'vue';
import Select from 'primevue/select';
import InputText from 'primevue/inputtext';
import ZFFormField from '@/components/ui/ZFFormField.vue';
import { i18n, tr as t } from '@/i18n';
import { phoneCountries, registrationPhone } from '@/lib/registrationPhone';

const phone = defineModel<string>({ required: true });
const country = defineModel<string>('country', { required: true });
const props = defineProps<{ error?: string; countryError?: string }>();
const emit = defineEmits<{ change: [] }>();
const flags = import.meta.glob<string>('/node_modules/flag-icons/flags/4x3/*.svg', {
    eager: true,
    exhaustive: true,
    import: 'default',
    query: '?url',
});
const flag = (code: string) =>
    ['AC', 'TA'].includes(code)
        ? `/flags/phone/${code.toLowerCase()}.svg`
        : flags[`/node_modules/flag-icons/flags/4x3/${code.toLowerCase()}.svg`];
const options = computed(() => {
    const names = new Intl.DisplayNames([i18n.global.locale.value], { type: 'region' });
    return phoneCountries.map((row) => ({
        ...row,
        label: `${row.name} (${row.prefix})`,
        search: [row.name, names.of(row.code), row.code, row.prefix].join(' '),
    }));
});
const selected = computed(() => phoneCountries.find((row) => row.code === country.value));
const touched = ref(false);
const valid = computed(() => registrationPhone(country.value, phone.value) !== null);
const error = computed(
    () => props.error || (touched.value && !valid.value ? t('registration_phone_invalid') : undefined),
);
function validate() {
    touched.value = true;
    if (!valid.value) document.getElementById('phone')?.focus();
    return valid.value;
}
defineExpose({ validate });
</script>
<template>
    <div
        class="registration-phone grid min-w-0 grid-cols-[8.5rem_minmax(0,1fr)] items-start"
        data-testid="registration-phone"
    >
        <ZFFormField
            for="phone-country"
            :label="$t('registration_phone_country')"
            :help="$t('registration_phone_country_help')"
            :error="countryError"
        >
            <Select
                v-model="country"
                input-id="phone-country"
                name="phone_country"
                :aria-label="$t('registration_phone_country')"
                aria-required="true"
                :options="options"
                option-label="label"
                option-value="code"
                filter
                :filter-fields="['search']"
                :filter-placeholder="$t('registration_phone_search')"
                :pt="{
                    label: { 'aria-required': 'true' },
                    pcFilter: {
                        root: {
                            'aria-label': $t('registration_phone_search'),
                            'aria-describedby': 'phone-country-help',
                        },
                    },
                }"
                reset-filter-on-hide
                class="phone-country w-full"
                :overlay-style="{ minWidth: 'min(21rem, calc(100vw - 2rem))', maxWidth: 'calc(100vw - 2rem)' }"
                @update:model-value="emit('change')"
            >
                <template #value
                    ><span v-if="selected" class="flex items-center gap-2"
                        ><img
                            v-if="flag(selected.code)"
                            :src="flag(selected.code)"
                            width="24"
                            height="18"
                            alt=""
                            aria-hidden="true"
                            class="h-[18px] w-6 shrink-0 rounded-sm object-cover"
                        /><i v-else class="pi pi-globe" aria-hidden="true" /><span>{{ selected.prefix }}</span
                        ><span class="sr-only">{{ selected.name }}</span></span
                    ></template
                >
                <template #option="slot"
                    ><span class="flex min-w-0 items-center gap-3"
                        ><img
                            v-if="flag(slot.option.code)"
                            :src="flag(slot.option.code)"
                            width="24"
                            height="18"
                            alt=""
                            aria-hidden="true"
                            loading="lazy"
                            class="h-[18px] w-6 shrink-0 rounded-sm object-cover"
                        /><i v-else class="pi pi-globe" aria-hidden="true" /><span
                            class="w-12 shrink-0 text-sm tabular-nums text-slate-500"
                            >{{ slot.option.prefix }}</span
                        ><span class="whitespace-normal text-sm">{{ slot.option.name }}</span></span
                    ></template
                >
            </Select>
        </ZFFormField>
        <ZFFormField for="phone" :label="$t('registration_phone_number')" :help="$t('registration_phone_help')">
            <InputText
                id="phone"
                v-model="phone"
                name="phone"
                type="tel"
                inputmode="tel"
                autocomplete="tel-national"
                required
                maxlength="40"
                class="phone-national w-full min-w-0"
                :aria-label="$t('registration_phone_number')"
                :aria-invalid="!!error"
                :aria-describedby="error ? 'phone-help phone-error' : 'phone-help phone-hint'"
                @blur="touched = true"
                @update:model-value="emit('change')"
            />
        </ZFFormField>
        <div class="col-span-2 min-h-9 pt-1">
            <small v-if="error" id="phone-error" role="alert" class="text-red-600">{{ error }}</small>
            <p v-else id="phone-hint" class="text-xs text-slate-500">{{ $t('registration_phone_hint') }}</p>
        </div>
    </div>
</template>
<style scoped>
.phone-country {
    border-top-right-radius: 0;
    border-bottom-right-radius: 0;
    border-right-width: 0;
}
.phone-national {
    border-top-left-radius: 0;
    border-bottom-left-radius: 0;
}
.registration-phone :deep(.field-label) {
    min-height: 1.5rem;
}
</style>
