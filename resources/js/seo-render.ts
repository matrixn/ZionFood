import { createSSRApp, h, type DefineComponent } from 'vue';
import { renderToString } from 'vue/server-renderer';
import { createInertiaApp } from '@inertiajs/vue3';
import PrimeVue from 'primevue/config';
import FieldHelp from '@/components/ui/FieldHelp.vue';
import Welcome from '@/pages/Welcome.vue';
import { i18n, primeLocale, type Locale } from '@/i18n';

export async function render(locale: Locale, member: boolean, version: string) {
    i18n.global.locale.value = locale;
    const result = await createInertiaApp({
        page: {
            component: 'Welcome',
            url: locale === 'en' ? '/en' : '/',
            version,
            props: {
                locale,
                version,
                errors: {},
                auth: { user: member ? { name: 'Member' } : null },
                seo: {
                    home: locale === 'en' ? '/en' : '/',
                    alternates: { ro: '/', en: '/en' },
                    title: i18n.global.t('landing_meta_title') + ' · ZionFood',
                    description: i18n.global.t('landing_meta_description'),
                    url: '/',
                    image: '',
                    image_alt: '',
                    locale: locale === 'en' ? 'en_GB' : 'ro_RO',
                    robots: 'noindex, nofollow',
                    schema: {},
                },
            },
            rescuedProps: [],
            flash: {},
            rememberedState: {},
            clearHistory: false,
            encryptHistory: false,
        },
        resolve: () => Welcome as DefineComponent,
        render: renderToString,
        setup: ({ App, props, plugin }) =>
            createSSRApp({ render: () => h(App, props) })
                .use(plugin)
                .use(i18n)
                .use(PrimeVue, { locale: primeLocale(locale) })
                .component('FieldHelp', FieldHelp),
    });
    if (!result || !('body' in result)) throw new Error('Landing prerender returned no HTML.');
    const match = result.body.match(/<div\b[^>]*\bid="app"[^>]*>([\s\S]*)<\/div>$/);
    if (!match) throw new Error('Unexpected Inertia prerender root.');
    return match[1];
}
