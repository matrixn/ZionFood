<script setup lang="ts">
import { computed, ref } from 'vue';
import Select from 'primevue/select';
import { usePrimeVue } from 'primevue/config';
import { i18n, primeLocale } from '@/i18n';
import { api } from '@/lib/http';
const props = defineProps<{ available: string[] }>();
const selection = defineModel<string>({ required: true });
const busy = ref(false);
const error = ref('');
const prime = usePrimeVue();
const names: Record<string, string> = {
    ro: '🇷🇴 Română',
    en: '🇬🇧 English',
    de: '🇩🇪 Deutsch',
    fr: '🇫🇷 Français',
    it: '🇮🇹 Italiano',
    es: '🇪🇸 Español',
    hu: '🇭🇺 Magyar',
    uk: '🇺🇦 Українська',
    bg: '🇧🇬 Български',
    tr: '🇹🇷 Türkçe',
    pl: '🇵🇱 Polski',
};
const options = computed(() =>
    [...new Set(['ro', 'en', ...props.available])]
        .filter((code) => names[code])
        .map((code) => ({ code, name: names[code].replace(/^\S+\s/, ''), flag: '/flags/' + code + '.svg' })),
);
async function change(value: string) {
    busy.value = true;
    error.value = '';
    const ui = value === 'ro' ? 'ro' : 'en';
    try {
        await api('/locale', 'POST', { locale: ui });
        selection.value = value;
        i18n.global.locale.value = ui;
        document.documentElement.lang = value;
        Object.assign(prime.config.locale!, primeLocale(ui));
    } catch {
        error.value = i18n.global.t('language_change_failed');
    } finally {
        busy.value = false;
    }
}
</script>
<template>
    <div class="relative">
        <Select
            :model-value="selection"
            :options="options"
            option-label="name"
            option-value="code"
            :loading="busy"
            :disabled="busy"
            :aria-label="$t('choose_language')"
            data-testid="language-selector"
            class="!min-w-36 !rounded-xl !text-sm"
            @update:model-value="change"
            ><template #value="slot"
                ><span class="flex items-center gap-2.5"
                    ><img
                        :src="options.find((o) => o.code === slot.value)?.flag"
                        alt=""
                        aria-hidden="true"
                        class="h-4 w-6 rounded-sm shadow-sm"
                    /><span>{{ options.find((o) => o.code === slot.value)?.name }}</span></span
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
            class="absolute right-0 top-full z-50 mt-2 rounded-lg bg-red-50 p-3 text-xs text-red-700"
            >{{ error }}</span
        >
    </div>
</template>
