<script setup lang="ts">
import { ref, watch } from 'vue';
import Select from 'primevue/select';
import { usePrimeVue } from 'primevue/config';
import { i18n, primeLocale, type Locale } from '@/i18n';
import { api } from '@/lib/http';
const prime = usePrimeVue();
const selection = ref<Locale>(i18n.global.locale.value);
const busy = ref(false);
const error = ref('');
const languages = [
    { code: 'ro', name: 'Română', flag: '/flags/ro.svg' },
    { code: 'en', name: 'English', flag: '/flags/en.svg' },
];
watch(
    i18n.global.locale,
    (locale) => {
        selection.value = locale;
        document.documentElement.lang = locale;
        Object.assign(prime.config.locale!, primeLocale(locale), {
            aria: { ...prime.config.locale?.aria, ...primeLocale(locale).aria },
        });
    },
    { immediate: true },
);
async function change(locale: Locale) {
    if (busy.value || locale === i18n.global.locale.value) return;
    busy.value = true;
    error.value = '';
    try {
        await api('/locale', 'POST', { locale });
        i18n.global.locale.value = locale;
    } catch {
        selection.value = i18n.global.locale.value;
        error.value = i18n.global.t('language_change_failed');
    } finally {
        busy.value = false;
    }
}
</script>
<template>
    <div class="relative shrink-0">
        <Select
            v-model="selection"
            :options="languages"
            option-label="name"
            option-value="code"
            :aria-label="$t('choose_language')"
            :loading="busy"
            :disabled="busy"
            class="!min-w-36 !rounded-xl !text-sm"
            data-testid="language-selector"
            @update:model-value="change"
            ><template #value="slot"
                ><span class="flex items-center gap-2.5"
                    ><img
                        :src="languages.find((l) => l.code === slot.value)?.flag"
                        alt=""
                        aria-hidden="true"
                        class="h-4 w-6 rounded-sm shadow-sm"
                    /><span>{{ languages.find((l) => l.code === slot.value)?.name }}</span></span
                ></template
            ><template #option="slot"
                ><span class="flex items-center gap-2.5"
                    ><img
                        :src="slot.option.flag"
                        alt=""
                        aria-hidden="true"
                        class="h-4 w-6 rounded-sm shadow-sm"
                    /><span>{{ slot.option.name }}</span></span
                ></template
            ></Select
        ><span
            v-if="error"
            role="alert"
            class="absolute right-0 top-full z-50 mt-2 w-60 rounded-lg bg-red-50 p-3 text-xs text-red-700 shadow"
            >{{ error }}</span
        >
    </div>
</template>
