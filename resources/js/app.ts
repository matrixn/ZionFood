import { createApp, h, type DefineComponent } from 'vue';
import { createInertiaApp } from '@inertiajs/vue3';
import { createPinia } from 'pinia';
import PrimeVue from 'primevue/config';
import ToastService from 'primevue/toastservice';
import ConfirmationService from 'primevue/confirmationservice';
import { definePreset } from '@primeuix/themes';
import Aura from '@primeuix/themes/aura';
import { i18n, primeLocale } from '@/i18n';
import '../css/app.css';
import 'primeicons/primeicons.css';
const preset = definePreset(Aura, {
    semantic: {
        primary: {
            50: '#f0efff',
            100: '#e1dfff',
            200: '#c5c0ff',
            300: '#a59dff',
            400: '#8479ff',
            500: '#635bff',
            600: '#5146e5',
            700: '#4439c2',
            800: '#382f9d',
            900: '#302a7d',
            950: '#1c174b',
        },
    },
});
createInertiaApp({
    title: (title) => `${title} · ZionFood`,
    resolve: async (name) => {
        const pages = import.meta.glob<{ default: DefineComponent }>('./pages/**/*.vue');
        const loader = pages[`./pages/${name}.vue`];
        if (!loader) throw new Error(`Unknown page: ${name}`);
        return (await loader()).default;
    },
    setup({ el, App, props, plugin }) {
        i18n.global.locale.value = props.initialPage.props.locale === 'en' ? 'en' : 'ro';
        createApp({ render: () => h(App, props) })
            .use(plugin)
            .use(i18n)
            .use(createPinia())
            .use(PrimeVue, {
                locale: primeLocale(i18n.global.locale.value),
                theme: { preset, options: { darkModeSelector: '.dark' } },
            })
            .use(ToastService)
            .use(ConfirmationService)
            .mount(el);
    },
});
