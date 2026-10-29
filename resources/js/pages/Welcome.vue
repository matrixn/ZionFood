<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import LanguageSelector from '@/components/ui/LanguageSelector.vue';
import ZFLogo from '@/components/ui/ZFLogo.vue';
interface Seo {
    title: string;
    description: string;
    url: string;
    home: string;
    alternates: Record<string, string>;
    locale: string;
    image: string;
    image_alt: string;
    robots: string;
    schema: Record<string, unknown>;
}
const page = usePage<{ auth: { user: unknown }; version: string; seo: Seo }>();
const seo = computed(() => page.props.seo);
const schema = computed(() => JSON.stringify(seo.value.schema).replaceAll('<', '\\u003c'));

const signedIn = computed(() => !!page.props.auth?.user);
const mobileOpen = ref(false);
const active = ref(0);
const orderStep = ref(0);
const root = ref<HTMLElement | null>(null);
const tabs = ref<HTMLElement | null>(null);
const progress = ref<HTMLElement | null>(null);
const ready = ref(false);
const iconsReady = ref(false);
let disposed = false;
const enhanced = ref(false);
const features = [
    { key: 'menu', icon: 'pi-book' },
    { key: 'reservations', icon: 'pi-calendar' },
    { key: 'qr', icon: 'pi-qrcode' },
    { key: 'delivery', icon: 'pi-map-marker' },
];
const orderStates = ['new', 'preparing', 'ready'];
const faqs = ['migration', 'services', 'website', 'qr', 'payments', 'start'];
let observer: IntersectionObserver | null = null;
let frame = 0;
function scroll() {
    if (frame) return;
    frame = requestAnimationFrame(() => {
        const distance = document.documentElement.scrollHeight - window.innerHeight;
        if (progress.value) progress.value.style.transform = `scaleX(${distance > 0 ? window.scrollY / distance : 0})`;
        frame = 0;
    });
}
function tabKey(event: KeyboardEvent) {
    let next = active.value;
    if (event.key === 'ArrowRight' || event.key === 'ArrowDown') next = (next + 1) % features.length;
    else if (event.key === 'ArrowLeft' || event.key === 'ArrowUp')
        next = (next + features.length - 1) % features.length;
    else if (event.key === 'Home') next = 0;
    else if (event.key === 'End') next = features.length - 1;
    else return;
    event.preventDefault();
    active.value = next;
    tabs.value?.querySelectorAll<HTMLButtonElement>('[role="tab"]')[next]?.focus();
}
onMounted(() => {
    enhanced.value = true;
    if (document.fonts) {
        void document.fonts
            .load('16px primeicons')
            .then((faces) => {
                if (!disposed && faces.length > 0 && faces.every((face) => face.status === 'loaded'))
                    iconsReady.value = true;
            })
            .catch(() => {
                /* Decorative icons stay hidden if their font fails. */
            });
    }
    if (!window.matchMedia('(prefers-reduced-motion: reduce)').matches && 'IntersectionObserver' in window) {
        ready.value = true;
        observer = new IntersectionObserver(
            (entries) =>
                entries.forEach((entry) => {
                    if (entry.isIntersecting) {
                        entry.target.classList.add('is-visible');
                        observer?.unobserve(entry.target);
                    }
                }),
            { threshold: 0.1 },
        );
        root.value?.querySelectorAll('[data-reveal]').forEach((element) => observer?.observe(element));
    }
    window.addEventListener('scroll', scroll, { passive: true });
    scroll();
});
onBeforeUnmount(() => {
    disposed = true;
    observer?.disconnect();
    window.removeEventListener('scroll', scroll);
    cancelAnimationFrame(frame);
});
</script>

<template>
    <Head :title="$t('landing_meta_title')">
        <meta head-key="description" name="description" :content="seo.description" />
        <meta head-key="robots" name="robots" :content="seo.robots" />
        <link head-key="canonical" rel="canonical" :href="seo.url" />
        <link
            v-for="(url, locale) in seo.alternates"
            :key="locale"
            :head-key="'alternate-' + locale"
            rel="alternate"
            :hreflang="locale"
            :href="url"
        />
        <link head-key="alternate-default" rel="alternate" hreflang="x-default" :href="seo.alternates.ro" />
        <meta
            v-for="key in ['title', 'description', 'url', 'locale', 'image'] as const"
            :key="key"
            :head-key="'og-' + key"
            :property="'og:' + key"
            :content="seo[key]"
        />
        <meta head-key="og-type" property="og:type" content="website" /><meta
            head-key="og-site"
            property="og:site_name"
            content="ZionFood"
        />
        <meta head-key="og-width" property="og:image:width" content="1440" /><meta
            head-key="og-height"
            property="og:image:height"
            content="1080"
        />
        <meta head-key="og-image-type" property="og:image:type" content="image/webp" /><meta
            head-key="og-alt"
            property="og:image:alt"
            :content="seo.image_alt"
        />
        <meta head-key="twitter-card" name="twitter:card" content="summary_large_image" />
        <meta
            v-for="key in ['title', 'description', 'image'] as const"
            :key="'twitter-' + key"
            :head-key="'twitter-' + key"
            :name="'twitter:' + key"
            :content="seo[key]"
        />
        <meta head-key="twitter-alt" name="twitter:image:alt" :content="seo.image_alt" />
        <component :is="'script'" head-key="schema" type="application/ld+json">{{ schema }}</component>
    </Head>
    <div
        ref="root"
        :data-enhanced="enhanced"
        class="landing"
        :class="{ 'motion-ready': ready, 'icons-ready': iconsReady }"
    >
        <a href="#main" class="skip-link">{{ $t('landing_skip') }}</a>
        <div ref="progress" class="reading-progress" aria-hidden="true" />
        <header class="site-header">
            <nav class="nav-wrap" :aria-label="$t('landing_navigation')">
                <Link :href="seo.home" class="wordmark" aria-label="ZionFood"
                    ><ZFLogo class="brand-mark" :size="40" />Zion<span>Food</span
                    ><span class="brand-period">.</span></Link
                >
                <div class="desktop-links">
                    <a href="#platform">{{ $t('landing_nav_platform') }}</a
                    ><a href="#workflow">{{ $t('landing_nav_workflow') }}</a
                    ><a href="#migration">{{ $t('landing_nav_migration') }}</a
                    ><a href="#questions">{{ $t('landing_nav_faq') }}</a>
                </div>
                <div class="nav-actions">
                    <Link :href="signedIn ? '/app' : '/login'" class="nav-login"
                        >{{ signedIn ? $t('landing_dashboard') : $t('sign_in') }}
                        <i class="pi pi-arrow-up-right" aria-hidden="true" /></Link
                    ><LanguageSelector :urls="seo.alternates" /><button
                        type="button"
                        class="mobile-toggle"
                        :aria-expanded="mobileOpen"
                        aria-controls="mobile-navigation"
                        :aria-label="$t('landing_toggle_menu')"
                        @click="mobileOpen = !mobileOpen"
                    >
                        <i :class="mobileOpen ? 'pi pi-times' : 'pi pi-bars'" aria-hidden="true" />
                    </button>
                </div>
                <div v-if="mobileOpen" id="mobile-navigation" class="mobile-navigation">
                    <a href="#platform" @click="mobileOpen = false">{{ $t('landing_nav_platform') }}</a
                    ><a href="#workflow" @click="mobileOpen = false">{{ $t('landing_nav_workflow') }}</a
                    ><a href="#migration" @click="mobileOpen = false">{{ $t('landing_nav_migration') }}</a
                    ><a href="#questions" @click="mobileOpen = false">{{ $t('landing_nav_faq') }}</a
                    ><Link :href="signedIn ? '/app' : '/login'">{{
                        signedIn ? $t('landing_dashboard') : $t('sign_in')
                    }}</Link>
                </div>
            </nav>
        </header>

        <main id="main">
            <section class="hero section-wrap">
                <div class="hero-copy">
                    <p class="eyebrow"><span class="live-dot" />{{ $t('landing_hero_eyebrow') }}</p>
                    <h1>
                        {{ $t('landing_hero_title')
                        }}<em
                            >{{ $t('landing_hero_accent')
                            }}<svg
                                data-decoration
                                width="420"
                                height="22"
                                style="visibility: hidden; max-width: 100%"
                                viewBox="0 0 420 22"
                                fill="none"
                                aria-hidden="true"
                            >
                                <path
                                    d="M4 16C108 0 283 0 414 14"
                                    stroke="currentColor"
                                    stroke-width="7"
                                    stroke-linecap="round"
                                /></svg
                        ></em>
                    </h1>
                    <p class="hero-description">{{ $t('landing_hero_description') }}</p>
                    <div class="hero-actions">
                        <Link :href="signedIn ? '/app' : '/register'" class="button button-primary"
                            >{{ signedIn ? $t('landing_dashboard') : $t('landing_cta_start')
                            }}<i class="pi pi-arrow-up-right" aria-hidden="true" /></Link
                        ><a href="#workflow" class="button button-quiet"
                            ><span class="play-icon"><i class="pi pi-play" aria-hidden="true" /></span
                            >{{ $t('landing_cta_explore') }}</a
                        >
                    </div>
                    <p class="hero-note">
                        <i class="pi pi-check-circle" aria-hidden="true" />{{ $t('landing_trial_note') }}
                    </p>
                    <div class="hero-signature">
                        <span class="signature-line" /><span>{{ $t('landing_made_for') }}</span
                        ><svg
                            data-decoration
                            width="45"
                            height="32"
                            style="visibility: hidden; max-width: 100%"
                            viewBox="0 0 45 32"
                            aria-hidden="true"
                        >
                            <path
                                d="M3 6c20-8 30 4 28 17M23 18l9 8 9-10"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="2"
                                stroke-linecap="round"
                            />
                        </svg>
                    </div>
                </div>
                <div class="hero-stage">
                    <div class="orbit orbit-one" aria-hidden="true" />
                    <div class="orbit orbit-two" aria-hidden="true" />
                    <div class="hero-photo">
                        <img
                            :src="'/images/marketing/restaurant-table-1440.webp'"
                            :srcset="'/images/marketing/restaurant-table-640.webp 640w, /images/marketing/restaurant-table-960.webp 960w, /images/marketing/restaurant-table-1440.webp 1440w'"
                            sizes="(max-width: 700px) 100vw, 55vw"
                            :alt="$t('landing_hero_image_alt')"
                            width="1448"
                            height="1086"
                            fetchpriority="high"
                        /><span class="photo-caption"
                            >{{ $t('landing_photo_caption') }} <i class="pi pi-heart-fill" aria-hidden="true"
                        /></span>
                    </div>
                    <div class="floating-reservation">
                        <span class="floating-icon"><i class="pi pi-calendar" aria-hidden="true" /></span>
                        <div>
                            <span class="micro-label">{{ $t('landing_example') }}</span
                            ><strong>{{ $t('landing_reservation_confirmed') }}</strong
                            ><span>{{ $t('landing_reservation_detail') }}</span>
                        </div>
                        <i class="pi pi-check-circle text-green" aria-hidden="true" />
                    </div>
                    <div class="floating-table">
                        <span class="table-code"><i class="pi pi-qrcode" aria-hidden="true" /></span>
                        <div>
                            <strong>{{ $t('landing_table_label') }}</strong
                            ><span>{{ $t('landing_table_detail') }}</span>
                        </div>
                        <span class="table-dot" />
                    </div>
                    <div class="hero-sticker" aria-hidden="true">
                        <svg
                            data-decoration
                            width="100"
                            height="100"
                            style="visibility: hidden; max-width: 100%"
                            viewBox="0 0 100 100"
                        >
                            <path
                                d="m50 1 8 10 13-5 3 13 14 2-3 14 13 7-9 11 7 12-14 5 1 14-14-1-6 13-11-8-12 7-4-14-14-1 3-13L1 60l9-10-6-13 14-4V19l14 2 7-12 11 8Z"
                                fill="currentColor"
                            /></svg
                        ><i class="pi pi-sparkles" />
                    </div>
                </div>
            </section>

            <section class="proof-strip section-wrap" :aria-label="$t('landing_highlights')" data-reveal>
                <p>{{ $t('landing_strip_title') }}</p>
                <div>
                    <strong>27</strong><span>{{ $t('landing_stat_cuisines') }}</span>
                </div>
                <div>
                    <strong>4</strong><span>{{ $t('landing_stat_services') }}</span>
                </div>
                <div>
                    <strong>RO / EN</strong><span>{{ $t('landing_stat_languages') }}</span>
                </div>
            </section>

            <section id="platform" class="platform-section section-wrap">
                <div class="section-heading" data-reveal>
                    <p class="eyebrow">{{ $t('landing_platform_eyebrow') }}</p>
                    <h2>
                        {{ $t('landing_platform_title') }}<em>{{ $t('landing_platform_accent') }}</em>
                    </h2>
                    <p>{{ $t('landing_platform_description') }}</p>
                </div>
                <div class="platform-grid" data-reveal>
                    <div
                        ref="tabs"
                        class="feature-tabs"
                        role="tablist"
                        :aria-label="$t('landing_feature_tabs')"
                        @keydown="tabKey"
                    >
                        <button
                            v-for="(feature, index) in features"
                            :id="`feature-tab-${index}`"
                            :key="feature.key"
                            type="button"
                            role="tab"
                            :aria-selected="active === index"
                            :aria-controls="`feature-panel-${index}`"
                            :tabindex="active === index ? 0 : -1"
                            :class="{ selected: active === index }"
                            @click="active = index"
                        >
                            <span class="feature-icon"><i :class="['pi', feature.icon]" aria-hidden="true" /></span
                            ><span
                                ><strong>{{ $t(`landing_feature_${feature.key}`) }}</strong
                                ><span>{{ $t(`landing_feature_${feature.key}_description`) }}</span></span
                            ><i class="pi pi-arrow-up-right feature-arrow" aria-hidden="true" />
                        </button>
                    </div>
                    <div class="preview-shell">
                        <div class="preview-top">
                            <span class="preview-dots"><i /><i /><i /></span
                            ><span>ZionFood / {{ $t('landing_product_preview') }}</span
                            ><span class="demo-label">DEMO</span>
                        </div>
                        <Transition name="preview" mode="out-in"
                            ><div
                                :id="`feature-panel-${active}`"
                                :key="active"
                                class="feature-preview"
                                role="tabpanel"
                                :aria-labelledby="`feature-tab-${active}`"
                                tabindex="0"
                            >
                                <template v-if="active === 0"
                                    ><div class="menu-preview-heading">
                                        <ZFLogo class="mini-brand" :size="48" />
                                        <div>
                                            <span class="micro-label">{{ $t('landing_your_brand') }}</span>
                                            <h3>{{ $t('landing_menu_title') }}</h3>
                                        </div>
                                        <i class="pi pi-shopping-bag" aria-hidden="true" />
                                    </div>
                                    <div class="menu-category">{{ $t('landing_menu_category') }}<span>03</span></div>
                                    <div
                                        v-for="(item, i) in ['pizza', 'burger', 'salad']"
                                        :key="item"
                                        class="menu-line"
                                    >
                                        <span :class="['dish-icon', `dish-${i}`]"
                                            ><i class="pi pi-sparkles" aria-hidden="true"
                                        /></span>
                                        <div>
                                            <strong>{{ $t(`landing_dish_${item}`) }}</strong
                                            ><span>{{ $t(`landing_dish_${item}_detail`) }}</span>
                                        </div>
                                        <span class="dish-plus" aria-hidden="true">+</span>
                                    </div>
                                    <div class="preview-bottom">
                                        <i class="pi pi-check" aria-hidden="true" />{{ $t('landing_menu_bottom') }}
                                    </div></template
                                >
                                <template v-else-if="active === 1"
                                    ><div class="booking-title">
                                        <p class="micro-label">{{ $t('landing_reservations_title') }}</p>
                                        <h3>{{ $t('landing_booking_heading') }}</h3>
                                        <span class="booking-time"
                                            >20:00 <i class="pi pi-sun" aria-hidden="true"
                                        /></span>
                                    </div>
                                    <div class="calendar-grid" aria-hidden="true">
                                        <span v-for="n in 14" :key="n" :class="{ chosen: n === 8, faded: n < 4 }">{{
                                            n + 10
                                        }}</span>
                                    </div>
                                    <div class="booking-guest">
                                        <span class="guest-avatar"><i class="pi pi-users" aria-hidden="true" /></span>
                                        <div>
                                            <strong>{{ $t('landing_booking_guests') }}</strong
                                            ><span>{{ $t('landing_booking_capacity') }}</span>
                                        </div>
                                        <i class="pi pi-check-circle text-green" aria-hidden="true" />
                                    </div>
                                    <div class="preview-bottom">
                                        <i class="pi pi-lock" aria-hidden="true" />{{ $t('landing_booking_bottom') }}
                                    </div></template
                                >
                                <template v-else-if="active === 2"
                                    ><div class="qr-preview">
                                        <p class="micro-label">{{ $t('landing_qr_eyebrow') }}</p>
                                        <h3>{{ $t('landing_table_label') }}</h3>
                                        <div class="qr-art" aria-hidden="true">
                                            <svg
                                                data-decoration
                                                width="150"
                                                height="150"
                                                style="visibility: hidden; max-width: 100%"
                                                viewBox="0 0 150 150"
                                            >
                                                <g fill="currentColor">
                                                    <path
                                                        d="M10 10h45v45H10zm8 8v29h29V18zM95 10h45v45H95zm8 8v29h29v-29zM10 95h45v45H10zm8 8v29h29v-29z"
                                                        fill-rule="evenodd"
                                                    />
                                                    <path
                                                        d="M27 27h11v11H27zM112 27h11v11h-11zM27 112h11v11H27zM68 14h12v22H68zM65 48h18v20H65zM12 68h26v12H12zM48 68h20v18H48zM87 64h18v18H87zM113 66h26v12h-26zM70 98h12v30H70zM92 95h20v18H92zM118 91h19v46h-12v-32h-7zM91 125h19v12H91z"
                                                    />
                                                </g>
                                            </svg>
                                        </div>
                                        <p>{{ $t('landing_qr_description') }}</p>
                                        <span class="qr-pill"
                                            ><span class="live-dot" />{{ $t('landing_qr_control') }}</span
                                        >
                                    </div></template
                                >
                                <template v-else
                                    ><div class="delivery-map-art">
                                        <svg
                                            data-decoration
                                            width="500"
                                            height="330"
                                            style="visibility: hidden; max-width: 100%"
                                            viewBox="0 0 500 330"
                                            fill="none"
                                            aria-hidden="true"
                                        >
                                            <defs>
                                                <pattern
                                                    id="streets"
                                                    x="0"
                                                    y="0"
                                                    width="85"
                                                    height="68"
                                                    patternUnits="userSpaceOnUse"
                                                >
                                                    <rect x="7" y="7" width="69" height="51" rx="12" fill="#e3e7de" />
                                                </pattern>
                                            </defs>
                                            <rect width="500" height="330" fill="#f4f5ef" />
                                            <rect width="500" height="330" fill="url(#streets)" />
                                            <path
                                                d="m112 62 191-24 107 99-49 149-210 13L71 184Z"
                                                fill="#635bff"
                                                fill-opacity=".13"
                                                stroke="#635bff"
                                                stroke-width="3"
                                                stroke-dasharray="8 5"
                                            />
                                            <path
                                                class="map-route"
                                                d="M178 199h73v-67h76"
                                                stroke="#635bff"
                                                stroke-width="6"
                                                stroke-linecap="round"
                                                stroke-dasharray="10 9"
                                            />
                                            <circle
                                                cx="178"
                                                cy="199"
                                                r="13"
                                                fill="#635bff"
                                                stroke="white"
                                                stroke-width="7"
                                            />
                                            <path
                                                d="M327 91c-22 0-36 15-36 34 0 25 36 56 36 56s36-31 36-56c0-19-14-34-36-34Z"
                                                fill="#635bff"
                                            />
                                            <circle cx="327" cy="124" r="11" fill="white" /></svg
                                        ><span class="map-label"
                                            ><i class="pi pi-map-marker" aria-hidden="true" />{{
                                                $t('landing_delivery_zone')
                                            }}</span
                                        >
                                    </div>
                                    <div class="delivery-footer">
                                        <span class="feature-icon"><i class="pi pi-send" aria-hidden="true" /></span>
                                        <div>
                                            <h3>{{ $t('landing_delivery_title') }}</h3>
                                            <p>{{ $t('landing_delivery_detail') }}</p>
                                        </div>
                                        <i class="pi pi-check-circle text-green" aria-hidden="true" /></div
                                ></template></div
                        ></Transition>
                    </div>
                </div>
            </section>

            <section id="workflow" class="workflow-section">
                <div class="section-wrap workflow-grid">
                    <div class="workflow-copy" data-reveal>
                        <p class="eyebrow">{{ $t('landing_workflow_eyebrow') }}</p>
                        <h2>
                            {{ $t('landing_workflow_title') }}<em>{{ $t('landing_workflow_accent') }}</em>
                        </h2>
                        <p>{{ $t('landing_workflow_description') }}</p>
                        <ol>
                            <li v-for="(key, i) in ['configure', 'publish', 'serve']" :key="key">
                                <span>0{{ i + 1 }}</span>
                                <div>
                                    <strong>{{ $t(`landing_step_${key}`) }}</strong>
                                    <p>{{ $t(`landing_step_${key}_description`) }}</p>
                                </div>
                            </li>
                        </ol>
                    </div>
                    <div class="order-demo" data-reveal>
                        <div class="order-demo-heading">
                            <div>
                                <span class="micro-label">{{ $t('landing_interactive_demo') }}</span>
                                <h3>{{ $t('landing_kitchen_live') }}</h3>
                            </div>
                            <span class="kitchen-live-dot" />
                        </div>
                        <div class="order-steps" :aria-label="$t('landing_order_progress')">
                            <button
                                v-for="(state, i) in orderStates"
                                :key="state"
                                type="button"
                                :aria-pressed="orderStep === i"
                                :class="{ current: orderStep === i, complete: orderStep > i }"
                                @click="orderStep = i"
                            >
                                <span>{{ i + 1 }}</span
                                >{{ $t(`landing_order_${state}`) }}
                            </button>
                        </div>
                        <div class="order-ticket">
                            <div class="ticket-top">
                                <strong>#0042</strong><span>{{ $t('landing_table_label') }} · QR</span>
                            </div>
                            <div class="ticket-line">
                                <span class="ticket-food"><i class="pi pi-sparkles" aria-hidden="true" /></span>
                                <div>
                                    <strong>Pizza Margherita</strong><span>{{ $t('landing_order_quantity') }}</span>
                                </div>
                                <strong>×2</strong>
                            </div>
                            <div :class="['ticket-status', `status-${orderStep}`]" role="status" aria-live="polite">
                                <i
                                    :class="['pi', ['pi-bell', 'pi-clock', 'pi-check-circle'][orderStep]]"
                                    aria-hidden="true"
                                /><span>{{ $t(`landing_status_${orderStates[orderStep]}`) }}</span>
                            </div>
                        </div>
                        <button
                            type="button"
                            class="button demo-next"
                            data-testid="landing-next-step"
                            @click="orderStep = (orderStep + 1) % 3"
                        >
                            {{ orderStep === 2 ? $t('landing_demo_restart') : $t('landing_demo_next')
                            }}<i class="pi pi-arrow-right" aria-hidden="true" />
                        </button>
                        <p class="demo-disclaimer">{{ $t('landing_demo_disclaimer') }}</p>
                    </div>
                </div>
            </section>

            <section class="kitchen-section section-wrap" data-reveal>
                <div class="kitchen-story">
                    <img
                        :src="'/images/marketing/kitchen-service-1440.webp'"
                        :srcset="'/images/marketing/kitchen-service-640.webp 640w, /images/marketing/kitchen-service-960.webp 960w, /images/marketing/kitchen-service-1440.webp 1440w'"
                        sizes="(max-width: 700px) 100vw, 90vw"
                        :alt="$t('landing_kitchen_image_alt')"
                        width="1672"
                        height="941"
                        loading="lazy"
                        decoding="async"
                    />
                    <div class="kitchen-shade" />
                    <div class="kitchen-copy">
                        <span class="story-tag"
                            ><i class="pi pi-heart" aria-hidden="true" />{{ $t('landing_kitchen_eyebrow') }}</span
                        >
                        <h2>
                            {{ $t('landing_kitchen_title') }}<em>{{ $t('landing_kitchen_accent') }}</em>
                        </h2>
                        <p>{{ $t('landing_kitchen_description') }}</p>
                        <div class="story-chips">
                            <span
                                ><i class="pi pi-check" aria-hidden="true" />{{ $t('landing_kitchen_chip_team') }}</span
                            ><span
                                ><i class="pi pi-check" aria-hidden="true" />{{
                                    $t('landing_kitchen_chip_brand')
                                }}</span
                            >
                        </div>
                    </div>
                    <div class="story-corner">
                        <i class="pi pi-sparkles" aria-hidden="true" /><span>{{ $t('landing_kitchen_corner') }}</span>
                    </div>
                </div>
            </section>

            <section
                id="migration"
                class="migration-section section-wrap"
                data-reveal
                aria-labelledby="migration-title"
            >
                <div class="migration-intro">
                    <p class="eyebrow">{{ $t('landing_migration_eyebrow') }}</p>
                    <h2 id="migration-title">{{ $t('landing_migration_title') }}</h2>
                    <p>{{ $t('landing_migration_description') }}</p>
                    <Link :href="signedIn ? '/app' : '/register'" class="button button-primary"
                        >{{ $t('landing_migration_cta') }}<i class="pi pi-arrow-up-right" aria-hidden="true"
                    /></Link>
                </div>
                <div class="migration-steps">
                    <article v-for="(step, index) in ['connect', 'verify', 'publish']" :key="step">
                        <span class="migration-number">0{{ index + 1 }}</span>
                        <div>
                            <h3>{{ $t('landing_migration_' + step) }}</h3>
                            <p>{{ $t('landing_migration_' + step + '_description') }}</p>
                        </div>
                    </article>
                    <p class="migration-note">
                        <i class="pi pi-info-circle" aria-hidden="true" />{{ $t('landing_migration_limits') }}
                    </p>
                    <a
                        href="https://github.com/GlobalFood/integration_docs/blob/master/fetch_menu/README.md"
                        rel="noopener noreferrer"
                        target="_blank"
                        >{{ $t('landing_migration_source') }} ↗</a
                    >
                </div>
            </section>

            <section id="questions" class="faq-section section-wrap" data-reveal>
                <div class="faq-intro">
                    <p class="eyebrow">{{ $t('landing_faq_eyebrow') }}</p>
                    <h2>
                        {{ $t('landing_faq_title') }}<em>{{ $t('landing_faq_accent') }}</em>
                    </h2>
                    <p>{{ $t('landing_faq_description') }}</p>
                </div>
                <div class="faq-list">
                    <details v-for="key in faqs" :key="key">
                        <summary>{{ $t(`landing_faq_${key}_question`) }}<span aria-hidden="true">+</span></summary>
                        <p>{{ $t(`landing_faq_${key}_answer`) }}</p>
                    </details>
                </div>
            </section>

            <section class="closing-section section-wrap" data-reveal>
                <div class="closing-card">
                    <div class="closing-orbit" aria-hidden="true" />
                    <span class="closing-spark" aria-hidden="true">✳</span>
                    <p class="eyebrow">{{ $t('landing_closing_eyebrow') }}</p>
                    <h2>
                        {{ $t('landing_closing_title') }}<em>{{ $t('landing_closing_accent') }}</em>
                    </h2>
                    <p>{{ $t('landing_closing_description') }}</p>
                    <Link :href="signedIn ? '/app' : '/register'" class="button button-lime"
                        >{{ signedIn ? $t('landing_dashboard') : $t('landing_cta_start')
                        }}<i class="pi pi-arrow-up-right" aria-hidden="true" /></Link
                    ><span class="closing-note">{{ $t('landing_trial_note') }}</span>
                </div>
            </section>
        </main>
        <footer class="site-footer section-wrap">
            <div>
                <Link :href="seo.home" class="wordmark" aria-label="ZionFood"
                    ><ZFLogo class="brand-mark" :size="40" />Zion<span>Food</span
                    ><span class="brand-period">.</span></Link
                >
                <p>{{ $t('landing_footer_tagline') }}</p>
            </div>
            <div class="footer-links">
                <a href="#platform">{{ $t('landing_nav_platform') }}</a
                ><a href="#questions">{{ $t('landing_nav_faq') }}</a
                ><Link href="/login">{{ $t('sign_in') }}</Link>
            </div>
            <div class="footer-languages" aria-label="Language">
                <a :href="seo.alternates.ro" hreflang="ro" lang="ro">🇷🇴 Română</a
                ><a :href="seo.alternates.en" hreflang="en" lang="en">🇬🇧 English</a>
            </div>
            <div class="footer-bottom">
                <span>ZionFood · v{{ page.props.version }}</span
                ><span>{{ $t('landing_ai_note') }}</span>
            </div>
        </footer>
    </div>
</template>

<style scoped>
/* Decorative SVGs are hidden inline until this render-blocking page CSS arrives. */
.landing svg[data-decoration] {
    visibility: visible !important;
}
.landing .pi::before {
    visibility: hidden;
}
.landing.icons-ready .pi::before {
    visibility: visible;
}

#migration {
    scroll-margin-top: 112px;
}
.migration-section {
    display: grid;
    grid-template-columns: 1fr 1.1fr;
    gap: 70px;
    padding-block: 90px;
}
.migration-section h2 {
    font-size: clamp(34px, 4vw, 52px);
    line-height: 1.1;
    letter-spacing: -1.8px;
}
.migration-intro > p:not(.eyebrow) {
    margin-block: 24px;
    color: #52635a;
    line-height: 1.75;
}
.migration-steps article {
    display: flex;
    gap: 20px;
    padding: 24px 0;
    border-bottom: 1px solid #dfe5dd;
}
.migration-number {
    color: #635bff;
    font-weight: 700;
    font-size: 24px;
}
.migration-steps h3 {
    font-size: 20px;
    margin-bottom: 8px;
}
.migration-steps p {
    font-size: 14px;
    line-height: 1.75;
    color: #52635a;
}
.migration-steps .migration-note {
    display: flex;
    gap: 10px;
    padding: 20px;
    background: #eeece5;
    border-radius: 16px;
    margin-block: 20px;
    font-size: 13px;
}
.migration-steps > a {
    color: #5146e5;
    font-size: 13px;
    text-decoration: underline;
    text-underline-offset: 4px;
}
.footer-languages {
    display: flex;
    gap: 24px;
    padding-block: 20px;
    font-size: 13px;
}
@media (max-width: 800px) {
    .migration-section {
        grid-template-columns: 1fr;
        gap: 28px;
        padding-block: 60px;
    }
}
.landing {
    --ink: #1c2723;
    --purple: #635bff;
    --lime: #d7f378;
    --cream: #faf9f5;
    background: var(--cream);
    color: var(--ink);
    overflow: clip;
    color-scheme: light;
    font-size: 16px;
}
.section-wrap {
    width: min(1280px, calc(100% - 80px));
    margin-inline: auto;
}
.landing h1,
.landing h2,
.landing h3,
.landing p {
    margin: 0;
}
.landing a,
.landing button {
    -webkit-tap-highlight-color: transparent;
}
.landing button {
    cursor: pointer;
}
.landing a:focus-visible,
.landing button:focus-visible,
.landing summary:focus-visible {
    outline: 3px solid #998aff;
    outline-offset: 5px;
}
.reading-progress {
    position: fixed;
    inset: 0 0 auto;
    height: 3px;
    background: var(--purple);
    z-index: 70;
    transform: scaleX(0);
    transform-origin: left;
    pointer-events: none;
}
.site-header {
    position: sticky;
    top: 0;
    z-index: 50;
    background: rgb(250 249 245 / 0.92);
    backdrop-filter: blur(16px);
    border-bottom: 1px solid #e9e9e0;
}
.nav-wrap {
    width: min(1360px, calc(100% - 64px));
    height: 92px;
    margin: auto;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 28px;
    position: relative;
}
.wordmark {
    display: inline-flex;
    align-items: center;
    color: var(--ink);
    font-size: 27px;
    font-weight: 850;
    letter-spacing: -1.5px;
    white-space: nowrap;
}
.wordmark > span:not(.brand-mark) {
    color: var(--purple);
}
.brand-mark {
    width: 40px;
    height: 40px;
    margin-right: 10px;
    object-fit: contain;
}

.brand-period {
    color: var(--purple);
}
.desktop-links,
.nav-actions {
    display: flex;
    align-items: center;
    gap: 30px;
}
.desktop-links a,
.nav-login {
    font-size: 13px;
    font-weight: 600;
    color: #424a45;
}
.desktop-links a:hover,
.nav-login:hover {
    color: var(--purple);
}
.nav-login {
    white-space: nowrap;
    display: flex;
    gap: 9px;
    align-items: center;
}
.nav-actions {
    gap: 22px;
}
.mobile-toggle,
.mobile-navigation {
    display: none;
}
.skip-link {
    position: fixed;
    left: 15px;
    top: -80px;
    background: var(--ink);
    color: #fff;
    padding: 12px 20px;
    z-index: 100;
    border-radius: 8px;
}
.skip-link:focus {
    top: 12px;
}
.hero {
    display: grid;
    grid-template-columns: 1fr 1.05fr;
    gap: 52px;
    align-items: center;
    min-height: 730px;
    padding-block: 76px 78px;
}
.eyebrow {
    display: flex;
    align-items: center;
    gap: 9px;
    color: #526052;
    font-size: 11px;
    font-weight: 750;
    letter-spacing: 1.6px;
    text-transform: uppercase;
}
.live-dot {
    width: 7px;
    height: 7px;
    flex-shrink: 0;
    border-radius: 50%;
    background: #92ae4e;
    box-shadow: 0 0 0 5px #92ae4e16;
}
.hero h1 {
    font-size: clamp(58px, 5.4vw, 78px);
    font-weight: 750;
    line-height: 1.06;
    letter-spacing: -4px;
    margin-top: 28px;
    text-wrap: balance;
}
.landing h1 em,
.landing h2 em {
    display: block;
    font-family: Georgia, 'Times New Roman', serif;
    font-weight: 400;
    font-style: italic;
    letter-spacing: -3px;
}
.hero h1 em {
    position: relative;
    display: block;
    width: fit-content;
    max-width: 100%;
    color: var(--purple);
    padding-bottom: 10px;
}
.hero h1 em svg {
    position: absolute;
    bottom: -3px;
    left: 0;
    width: 100%;
    height: 17px;
    color: var(--lime);
    z-index: 0;
}
.hero-description {
    max-width: 460px;
    font-size: 16px;
    line-height: 1.85;
    color: #68706a;
    margin-top: 30px !important;
}
.hero-actions {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 18px;
    margin-top: 31px;
}
.button {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 20px;
    border-radius: 14px;
    padding: 18px 22px;
    font-size: 13px;
    line-height: 1.4;
    font-weight: 650;
    border: 1px solid transparent;
    transition:
        transform 0.2s,
        box-shadow 0.2s,
        background 0.2s;
}
.button:hover {
    transform: translateY(-3px);
}
.button-primary {
    background: var(--purple);
    color: white;
    box-shadow: 0 8px 20px #635bff20;
}
.button-primary:hover {
    background: #5147e9;
    box-shadow: 0 12px 26px #635bff32;
}
.button-quiet {
    padding-inline: 0;
    color: var(--ink);
    gap: 9px;
}
.play-icon {
    border: 1px solid #d9ddd1;
    border-radius: 50%;
    height: 33px;
    width: 33px;
    display: grid;
    place-items: center;
    font-size: 10px;
}
.hero-note {
    display: flex;
    gap: 8px;
    align-items: center;
    margin-top: 18px !important;
    font-size: 11px;
    color: #7b8179;
}
.hero-note i {
    color: #859a55;
}
.hero-signature {
    display: flex;
    align-items: center;
    gap: 12px;
    color: #90958c;
    margin-top: 41px;
    font-size: 11px;
}
.signature-line {
    height: 1px;
    width: 32px;
    background: #b7bdb1;
}
.hero-signature svg {
    width: 43px;
    color: #899671;
    transform: rotate(-15deg);
}
.hero-stage {
    position: relative;
    min-width: 0;
    padding: 15px 14px 35px 18px;
    isolation: isolate;
}
.hero-photo {
    height: 540px;
    border-radius: 180px 180px 32px 32px;
    overflow: hidden;
    position: relative;
    background: #e3e4d6;
    box-shadow: 0 25px 55px #282c2013;
}
.hero-photo > img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    object-position: 47% center;
    animation: photo-enter 1.1s ease both;
}
.photo-caption {
    position: absolute;
    bottom: 25px;
    left: 25px;
    right: 25px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    border-top: 1px solid #fff7;
    padding-top: 12px;
    color: #fff;
    text-shadow: 0 1px 10px #000b;
    font-size: 11px;
    letter-spacing: 0.5px;
}
.photo-caption i {
    color: #e2f490;
}
.orbit {
    position: absolute;
    border: 1px solid #d8dfb9;
    border-radius: 50%;
    pointer-events: none;
    z-index: -1;
}
.orbit-one {
    width: 100%;
    height: 96%;
    left: 2px;
    top: 0;
    transform: rotate(15deg);
}
.orbit-two {
    width: 95%;
    height: 100%;
    left: 10px;
    top: 0;
    transform: rotate(-15deg);
}
.floating-reservation,
.floating-table {
    position: absolute;
    display: flex;
    align-items: center;
    background: #fff;
    padding: 17px;
    gap: 13px;
    box-shadow: 0 16px 50px #1e251e18;
    border: 1px solid #f0f0e8;
    border-radius: 17px;
    animation: float 7s ease-in-out infinite;
}
.floating-reservation {
    left: -35px;
    bottom: 64px;
    width: 282px;
}
.floating-reservation > div,
.floating-table > div {
    display: grid;
    gap: 4px;
    flex: 1;
}
.floating-reservation strong,
.floating-table strong {
    font-size: 12px;
    color: var(--ink);
}
.floating-reservation span:not(.floating-icon):not(.micro-label),
.floating-table > div > span {
    font-size: 10px;
    color: #8b9189;
}
.micro-label {
    display: block;
    font-size: 9px;
    letter-spacing: 1.5px;
    text-transform: uppercase;
    color: #92998e;
    font-weight: 650;
}
.floating-icon,
.table-code {
    height: 43px;
    width: 43px;
    background: #eef5dc;
    border-radius: 12px;
    color: #718b3e;
    display: grid;
    place-items: center;
    font-size: 18px;
}
.text-green {
    color: #789a45;
}
.floating-table {
    right: -13px;
    top: 80px;
    animation-delay: -3s;
    width: 220px;
    padding: 15px;
}
.table-code {
    background: #efedff;
    color: var(--purple);
}
.table-dot {
    height: 7px;
    width: 7px;
    background: #8caf57;
    border-radius: 50%;
}
.hero-sticker {
    position: absolute;
    right: -14px;
    bottom: 9px;
    width: 88px;
    height: 88px;
    color: var(--lime);
    transform: rotate(14deg);
}
.hero-sticker svg {
    width: 100%;
    height: 100%;
}
.hero-sticker > i {
    position: absolute;
    inset: 0;
    display: grid;
    place-items: center;
    color: var(--ink);
    font-size: 30px;
}
.proof-strip {
    display: flex;
    align-items: center;
    justify-content: space-between;
    border-block: 1px solid #e2e5dc;
    padding-block: 25px;
    gap: 28px;
}
.proof-strip > p {
    font-family: Georgia, serif;
    font-style: italic;
    font-size: 21px;
    max-width: 220px;
    color: #7d8676;
}
.proof-strip > div {
    display: flex;
    gap: 14px;
    align-items: center;
}
.proof-strip strong {
    font-size: 29px;
    font-weight: 550;
    letter-spacing: -1px;
}
.proof-strip span {
    font-size: 11px;
    color: #7c837a;
    max-width: 110px;
    line-height: 1.6;
}
.platform-section {
    padding-block: 104px 112px;
    scroll-margin-top: 112px;
}
.section-heading {
    max-width: 640px;
    margin-bottom: 46px;
}
.landing h2 {
    font-size: clamp(38px, 4vw, 54px);
    font-weight: 650;
    letter-spacing: -2.5px;
    line-height: 1.13;
    text-wrap: balance;
}
.section-heading h2 {
    margin-top: 16px;
}
.section-heading h2 em {
    display: inline;
    margin-left: 10px;
    color: #899d54;
}
.section-heading > p:last-child {
    font-size: 14px;
    color: #7b8478;
    line-height: 1.9;
    max-width: 490px;
    margin-top: 19px;
}
.platform-grid {
    display: grid;
    grid-template-columns: 0.85fr 1.15fr;
    gap: 70px;
    align-items: center;
}
.feature-tabs {
    display: grid;
    gap: 9px;
}
.feature-tabs button {
    display: flex;
    align-items: center;
    gap: 17px;
    text-align: left;
    border: 1px solid transparent;
    background: transparent;
    padding: 22px 19px;
    border-radius: 18px;
    color: var(--ink);
    transition:
        background 0.2s,
        border-color 0.2s;
}
.feature-tabs button:hover {
    background: #eeeee5;
}
.feature-tabs button.selected {
    background: #fff;
    border-color: #e3e6da;
    box-shadow: 0 6px 24px #27351e04;
}
.feature-icon {
    flex: 0 0 auto;
    display: grid;
    place-items: center;
    width: 47px;
    height: 47px;
    background: #eeeee6;
    color: #728061;
    border-radius: 14px;
    font-size: 20px;
}
.selected .feature-icon {
    background: #eeedff;
    color: var(--purple);
}
.feature-tabs button > span:nth-child(2) {
    display: grid;
    gap: 7px;
}
.feature-tabs strong {
    font-size: 15px;
    font-weight: 650;
}
.feature-tabs button > span > span {
    font-size: 12px;
    color: #8a9186;
    line-height: 1.7;
}
.feature-arrow {
    margin-left: auto;
    color: #a9b19d;
    font-size: 13px;
}
.selected .feature-arrow {
    color: var(--purple);
}
.preview-shell {
    min-width: 0;
    background: #fff;
    border: 1px solid #e2e7da;
    border-radius: 25px;
    padding: 8px;
    box-shadow: 0 22px 60px #20381909;
    position: relative;
    transform: rotate(1deg);
}
.preview-top {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 15px;
    padding: 16px 17px;
    font-size: 9px;
    color: #92998b;
    border-bottom: 1px solid #f1f2eb;
}
.preview-dots {
    display: flex;
    gap: 4px;
}
.preview-dots i {
    width: 5px;
    height: 5px;
    border-radius: 50%;
    background: #d9decf;
}
.demo-label {
    color: #8d9387;
    font-size: 8px;
    letter-spacing: 1.5px;
    border: 1px solid #e9ecdf;
    border-radius: 4px;
    padding: 3px 5px;
}
.feature-preview {
    height: 386px;
    padding: 30px 31px 15px;
    overflow: hidden;
    outline-offset: -4px !important;
}
.menu-preview-heading {
    display: flex;
    align-items: center;
    gap: 12px;
}
.mini-brand {
    width: 48px;
    height: 48px;
    object-fit: contain;
}

.menu-preview-heading h3 {
    font-size: 17px;
    letter-spacing: -0.5px;
    margin-top: 4px;
}
.menu-preview-heading > i {
    margin-left: auto;
    color: #99a187;
}
.menu-category {
    display: flex;
    justify-content: space-between;
    align-items: center;
    font-size: 10px;
    color: #8a9280;
    border-bottom: 1px solid #eef1e5;
    padding-block: 22px 10px;
}
.menu-category > span {
    font-size: 9px;
}
.menu-line {
    display: flex;
    align-items: center;
    gap: 14px;
    padding-block: 12px;
    border-bottom: 1px solid #f0f2e9;
}
.dish-icon {
    width: 37px;
    height: 37px;
    border-radius: 12px;
    display: grid;
    place-items: center;
    background: #f6e4cf;
    color: #ad8e5c;
}
.dish-1 {
    background: #ebefdd;
    color: #9aab66;
}
.dish-2 {
    background: #eee8f2;
    color: #9f8db3;
}
.menu-line > div {
    display: grid;
    gap: 4px;
}
.menu-line strong {
    font-size: 11px;
}
.menu-line > div > span {
    font-size: 9px;
    color: #9da38f;
}
.dish-plus {
    margin-left: auto;
    color: var(--purple);
    font-size: 19px;
}
.preview-bottom {
    display: flex;
    align-items: center;
    gap: 7px;
    margin-top: 14px;
    font-size: 9px;
    color: #8d997d;
}
.preview-bottom > i {
    color: #8fab56;
}
.booking-title {
    position: relative;
}
.booking-title h3 {
    font-size: 20px;
    margin-top: 8px;
    max-width: 70%;
    letter-spacing: -0.7px;
}
.booking-time {
    position: absolute;
    right: 0;
    top: 14px;
    color: var(--purple);
    background: #f2efff;
    padding: 9px;
    border-radius: 9px;
    font-size: 11px;
}
.booking-time > i {
    font-size: 11px;
    margin-left: 6px;
}
.calendar-grid {
    display: grid;
    grid-template-columns: repeat(7, 1fr);
    gap: 10px;
    margin-block: 24px;
}
.calendar-grid > span {
    height: 35px;
    display: grid;
    place-items: center;
    font-size: 11px;
    background: #f7f8f2;
    border-radius: 8px;
    color: #6b735f;
}
.calendar-grid .chosen {
    background: var(--purple);
    color: #fff;
    box-shadow: 0 6px 14px #635bff29;
}
.calendar-grid .faded {
    opacity: 0.3;
}
.booking-guest {
    display: flex;
    gap: 12px;
    align-items: center;
    background: #f7f9ef;
    border-radius: 13px;
    padding: 14px;
}
.guest-avatar {
    width: 35px;
    height: 35px;
    display: grid;
    place-items: center;
    background: #e9eedb;
    color: #8a9f5f;
    border-radius: 50%;
}
.booking-guest > div {
    display: grid;
    gap: 5px;
}
.booking-guest strong {
    font-size: 11px;
}
.booking-guest > div > span {
    font-size: 9px;
    color: #9aa088;
}
.booking-guest > i {
    margin-left: auto;
}
.qr-preview {
    text-align: center;
}
.qr-preview h3 {
    font-size: 24px;
    letter-spacing: -1px;
    margin-top: 8px;
}
.qr-art {
    width: 160px;
    height: 160px;
    margin: 15px auto;
    color: var(--ink);
    padding: 12px;
    background: #fafbf5;
    border: 1px solid #eaeddf;
    border-radius: 17px;
    box-shadow: 0 8px 20px #25301707;
    transform: rotate(-4deg);
}
.qr-art svg {
    width: 100%;
    height: 100%;
}
.qr-preview > p:not(.micro-label) {
    font-size: 11px;
    color: #8c967e;
}
.qr-pill {
    display: inline-flex;
    align-items: center;
    gap: 9px;
    margin-top: 15px;
    border: 1px solid #e1e8ce;
    border-radius: 30px;
    padding: 10px 13px;
    font-size: 9px;
    color: #80935d;
}
.delivery-map-art {
    margin: -30px -31px 0;
    height: 275px;
    position: relative;
    overflow: hidden;
}
.delivery-map-art > svg {
    height: 100%;
    width: 100%;
    object-fit: cover;
}
.map-label {
    position: absolute;
    top: 20px;
    left: 20px;
    font-size: 10px;
    border-radius: 9px;
    background: #fff;
    padding: 10px 14px;
    box-shadow: 0 4px 15px #19221a0c;
    color: #7b846f;
    display: flex;
    gap: 7px;
    align-items: center;
}
.map-label i {
    color: var(--purple);
}
.map-route {
    animation: route 9s linear infinite;
}
.delivery-footer {
    display: flex;
    align-items: center;
    gap: 12px;
    padding-top: 20px;
}
.delivery-footer .feature-icon {
    width: 35px;
    height: 35px;
    font-size: 15px;
}
.delivery-footer h3 {
    font-size: 12px;
}
.delivery-footer p {
    font-size: 9px;
    color: #8b947c;
    margin-top: 5px;
}
.delivery-footer > i {
    margin-left: auto;
}
.workflow-section {
    background: #eff1e8;
    padding-block: 98px;
    scroll-margin-top: 85px;
}
.workflow-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 110px;
    align-items: center;
}
.workflow-copy h2 {
    margin-top: 18px;
}
.workflow-copy h2 em {
    color: var(--purple);
}
.workflow-copy > p:not(.eyebrow) {
    font-size: 14px;
    line-height: 1.9;
    color: #7c8575;
    max-width: 420px;
    margin-top: 24px;
}
.workflow-copy ol {
    padding: 0;
    list-style: none;
    display: grid;
    gap: 23px;
    margin: 34px 0 0;
}
.workflow-copy li {
    display: flex;
    gap: 17px;
    align-items: flex-start;
}
.workflow-copy li > span {
    color: #99a778;
    border: 1px solid #d4ddc2;
    border-radius: 10px;
    padding: 8px 9px;
    font-size: 11px;
}
.workflow-copy li strong {
    font-size: 13px;
}
.workflow-copy li p {
    font-size: 11px;
    line-height: 1.7;
    color: #929b85;
    margin-top: 5px;
}
.order-demo {
    border-radius: 23px;
    padding: 29px;
    background: #fafbf6;
    border: 1px solid #e0e6d4;
    box-shadow: 0 22px 60px #1d311910;
    transform: rotate(-2deg);
}
.order-demo-heading {
    display: flex;
    justify-content: space-between;
    align-items: center;
}
.order-demo-heading h3 {
    font-size: 17px;
    margin-top: 6px;
    letter-spacing: -0.5px;
}
.kitchen-live-dot {
    height: 7px;
    width: 7px;
    background: #94b65d;
    box-shadow: 0 0 0 5px #94b65d15;
    border-radius: 50%;
    animation: pulse 3s ease infinite;
}
.order-steps {
    display: flex;
    align-items: start;
    justify-content: space-between;
    gap: 9px;
    margin-block: 25px;
}
.order-steps button {
    display: flex;
    align-items: center;
    gap: 6px;
    border: 0;
    background: none;
    color: #a1ab90;
    font-size: 9px;
    line-height: 1.5;
    padding: 0;
}
.order-steps button > span {
    display: grid;
    place-items: center;
    width: 21px;
    height: 21px;
    background: #eaf0de;
    border-radius: 50%;
    color: #96a777;
    flex-shrink: 0;
}
.order-steps .current {
    color: var(--purple);
    font-weight: 650;
}
.order-steps .current > span {
    background: var(--purple);
    color: white;
}
.order-steps .complete > span {
    background: #d8e8b7;
    color: #69813f;
}
.order-ticket {
    border: 1px solid #e8eddd;
    border-radius: 16px;
    padding: 22px;
    background: white;
    box-shadow: 0 10px 25px #25381004;
}
.ticket-top {
    display: flex;
    justify-content: space-between;
    align-items: center;
    border-bottom: 1px dashed #e5eadb;
    padding-bottom: 16px;
}
.ticket-top > strong {
    font-size: 18px;
    letter-spacing: -0.6px;
}
.ticket-top > span {
    font-size: 10px;
    color: #8d997d;
}
.ticket-line {
    display: flex;
    align-items: center;
    gap: 12px;
    padding-block: 22px;
}
.ticket-food {
    display: grid;
    place-items: center;
    width: 42px;
    height: 42px;
    border-radius: 10px;
    background: #f9edda;
    color: #b5a172;
}
.ticket-line > div {
    display: grid;
    gap: 5px;
}
.ticket-line strong {
    font-size: 11px;
}
.ticket-line > div > span {
    font-size: 9px;
    color: #9da78e;
}
.ticket-line > strong {
    margin-left: auto;
}
.ticket-status {
    display: flex;
    gap: 10px;
    align-items: center;
    font-size: 11px;
    padding: 13px;
    border-radius: 9px;
    transition:
        background 0.25s,
        color 0.25s;
}
.status-0 {
    background: #f5efdd;
    color: #a28b4d;
}
.status-1 {
    background: #eeedff;
    color: #7b71d8;
}
.status-2 {
    background: #edf4df;
    color: #729749;
}
.demo-next {
    width: 100%;
    margin-top: 20px;
    background: var(--ink);
    color: #fff;
    font-size: 11px;
    padding-block: 14px;
}
.demo-next:hover {
    background: #303d34;
}
.demo-disclaimer {
    color: #9ca58e;
    font-size: 9px;
    line-height: 1.7;
    text-align: center;
    margin-top: 13px !important;
}
.kitchen-section {
    padding-block: 110px 0;
}
.kitchen-story {
    position: relative;
    min-height: 480px;
    border-radius: 32px;
    overflow: hidden;
    background: #252c25;
    isolation: isolate;
}
.kitchen-story > img {
    position: absolute;
    width: 100%;
    height: 100%;
    object-fit: cover;
    object-position: center;
    z-index: -2;
}
.kitchen-shade {
    position: absolute;
    inset: 0;
    z-index: -1;
    background: linear-gradient(90deg, #131a17e6 0%, #17221ac2 37%, #17221a05 78%);
}
.kitchen-copy {
    padding: 66px 60px;
    max-width: 590px;
    color: #fff;
}
.story-tag {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    border: 1px solid #ffffff40;
    border-radius: 40px;
    padding: 8px 12px;
    font-size: 9px;
    letter-spacing: 0.6px;
    color: #e3e9d7;
}
.story-tag i {
    font-size: 10px;
    color: #d7ed9d;
}
.kitchen-copy h2 {
    margin-block: 25px 20px;
    font-size: 48px;
}
.kitchen-copy h2 em {
    color: var(--lime);
}
.kitchen-copy > p {
    color: #d1d7c8;
    font-size: 12px;
    line-height: 1.9;
    max-width: 330px;
}
.story-chips {
    display: flex;
    flex-wrap: wrap;
    gap: 18px;
    margin-top: 27px;
    font-size: 9px;
    color: #d7e0c7;
}
.story-chips span {
    display: flex;
    gap: 6px;
    align-items: center;
}
.story-chips i {
    color: #c2d78e;
}
.story-corner {
    position: absolute;
    bottom: 25px;
    right: 28px;
    display: grid;
    gap: 8px;
    color: #fff;
    text-align: right;
    font-size: 9px;
    max-width: 140px;
    line-height: 1.7;
}
.story-corner i {
    font-size: 26px;
    color: var(--lime);
}
.faq-section {
    padding-block: 110px;
    display: grid;
    grid-template-columns: 0.8fr 1.2fr;
    gap: 90px;
    scroll-margin-top: 100px;
}
.faq-intro h2 {
    margin-block: 17px;
}
.faq-intro h2 em {
    color: #8b9d62;
}
.faq-intro > p:last-child {
    font-size: 12px;
    line-height: 1.9;
    color: #8e9783;
    max-width: 270px;
}
.faq-list {
    display: grid;
    align-content: start;
}
.faq-list details {
    border-bottom: 1px solid #e2e6d9;
}
.faq-list details:first-child {
    border-top: 1px solid #e2e6d9;
}
.faq-list summary {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 20px;
    cursor: pointer;
    padding: 24px 0;
    font-size: 13px;
    font-weight: 650;
    list-style: none;
}
.faq-list summary::-webkit-details-marker {
    display: none;
}
.faq-list summary span {
    font-size: 23px;
    font-weight: 300;
    color: #91a365;
    transition: transform 0.2s;
}
.faq-list details[open] summary span {
    transform: rotate(45deg);
}
.faq-list details > p {
    font-size: 12px;
    line-height: 1.9;
    color: #7f8974;
    padding: 0 30px 23px 0;
}
.closing-section {
    padding-bottom: 66px;
}
.closing-card {
    position: relative;
    isolation: isolate;
    overflow: hidden;
    border-radius: 30px;
    background: var(--purple);
    color: white;
    padding: 65px 30px;
    text-align: center;
}
.closing-card .eyebrow {
    display: block;
    color: #d4d0ff;
    font-size: 9px;
    letter-spacing: 2px;
}
.closing-card h2 {
    font-size: 53px;
    margin-block: 19px;
}
.closing-card h2 em {
    color: var(--lime);
}
.closing-card > p:not(.eyebrow) {
    font-size: 12px;
    color: #d6d3ff;
    line-height: 1.9;
    max-width: 360px;
    margin: 0 auto 24px;
}
.button-lime {
    background: var(--lime);
    color: #28321b;
    font-size: 12px;
    padding-inline: 25px;
}
.button-lime:hover {
    background: #e3fa9e;
    box-shadow: 0 8px 20px #25244920;
}
.closing-note {
    display: block;
    margin-top: 15px;
    font-size: 9px;
    color: #cbc8f8;
}
.closing-orbit {
    position: absolute;
    height: 610px;
    width: 610px;
    border: 1px solid #ffffff15;
    border-radius: 50%;
    top: -64px;
    left: calc(50% - 305px);
    z-index: -1;
    box-shadow:
        0 0 0 65px #ffffff05,
        0 0 0 130px #ffffff03;
}
.closing-spark {
    position: absolute;
    right: 12%;
    top: 18%;
    font-size: 70px;
    color: #ffffff1c;
    animation: spin 30s linear infinite;
}
.site-footer {
    display: flex;
    justify-content: space-between;
    align-items: start;
    flex-wrap: wrap;
    gap: 30px;
    padding-block: 0 28px;
}
.site-footer .wordmark {
    font-size: 23px;
}
.site-footer .brand-mark {
    width: 29px;
    height: 29px;
    font-size: 24px;
    border-radius: 9px;
}
.site-footer > div > p {
    font-size: 11px;
    color: #9ba18f;
    margin-top: 13px;
    line-height: 1.8;
    max-width: 290px;
}
.footer-links {
    display: flex;
    gap: 30px;
    align-items: center;
    font-size: 11px;
    padding-top: 12px;
}
.footer-links a {
    color: #7d8870;
}
.footer-links a:hover {
    color: var(--purple);
}
.footer-bottom {
    flex: 0 0 100%;
    border-top: 1px solid #e6e9dd;
    margin-top: 10px;
    padding-top: 22px;
    display: flex;
    justify-content: space-between;
    gap: 20px;
    font-size: 9px;
    color: #a1a891;
}
.motion-ready [data-reveal] {
    opacity: 0;
    transform: translateY(24px);
    transition:
        opacity 0.7s ease,
        transform 0.7s ease;
}
.motion-ready [data-reveal].is-visible {
    opacity: 1;
    transform: none;
}
.preview-enter-active,
.preview-leave-active {
    transition:
        opacity 0.15s,
        transform 0.15s;
}
.preview-enter-from {
    opacity: 0;
    transform: translateY(8px);
}
.preview-leave-to {
    opacity: 0;
    transform: translateY(-8px);
}
.landing :deep(.p-select) {
    background: #fff;
    color: #36402e;
    border-color: #e1e5d8;
    font-size: 12px !important;
    min-width: 130px !important;
    box-shadow: none;
}
.landing :deep(.p-select-label) {
    padding-block: 10px;
}
.landing :deep(.p-select-dropdown) {
    width: 28px;
    color: #98a18d;
}
.landing :deep(.p-select img) {
    height: 13px;
    width: 20px;
}
@keyframes float {
    0%,
    100% {
        transform: translateY(0) rotate(-2deg);
    }
    50% {
        transform: translateY(-9px) rotate(0);
    }
}
@keyframes route {
    to {
        stroke-dashoffset: -190;
    }
}
@keyframes pulse {
    50% {
        box-shadow: 0 0 0 9px #94b65d00;
    }
}
@keyframes spin {
    to {
        transform: rotate(360deg);
    }
}
@keyframes photo-enter {
    from {
        transform: scale(1.06);
        opacity: 0.4;
    }
    to {
        transform: scale(1);
        opacity: 1;
    }
}
@media (min-width: 1440px) {
    .hero {
        gap: 80px;
        min-height: 780px;
    }
    .hero-photo {
        height: 565px;
    }
    .hero h1 {
        font-size: 85px;
    }
}
@media (max-width: 1100px) {
    .section-wrap {
        width: calc(100% - 56px);
    }
    .desktop-links {
        gap: 18px;
    }
    .nav-wrap {
        gap: 20px;
    }
    .nav-actions {
        gap: 16px;
    }
    .hero {
        gap: 30px;
    }
    .hero h1 {
        font-size: 61px;
        letter-spacing: -3.5px;
    }
    .hero-photo {
        height: 475px;
        border-radius: 145px 145px 30px 30px;
    }
    .floating-reservation {
        left: -14px;
        width: 242px;
        padding: 13px;
    }
    .floating-table {
        right: -10px;
        width: 193px;
    }
    .hero-actions {
        gap: 9px;
    }
    .button {
        padding: 16px;
        font-size: 12px;
        gap: 13px;
    }
    .button-quiet {
        padding-left: 0;
    }
    .hero-description {
        font-size: 14px;
    }
    .hero-note {
        font-size: 10px;
    }
    .platform-grid {
        gap: 40px;
    }
    .workflow-grid {
        gap: 65px;
    }
    .proof-strip {
        gap: 20px;
    }
    .proof-strip > div {
        gap: 10px;
    }
    .proof-strip strong {
        font-size: 25px;
    }
    .proof-strip > p {
        font-size: 18px;
        max-width: 170px;
    }
    .proof-strip span {
        font-size: 10px;
        max-width: 95px;
    }
    .faq-section {
        gap: 60px;
    }
    .feature-preview {
        padding-inline: 24px;
    }
    .delivery-map-art {
        margin-inline: -24px;
    }
}
@media (max-width: 820px) {
    .desktop-links {
        display: none;
    }
    .nav-wrap {
        height: 78px;
    }
    .hero {
        gap: 30px;
        min-height: auto;
        padding-block: 55px 60px;
    }
    .hero h1 {
        font-size: 53px;
        letter-spacing: -3px;
    }
    .hero-photo {
        height: 450px;
        border-radius: 130px 130px 24px 24px;
    }
    .hero-description {
        font-size: 13px;
        line-height: 1.9;
    }
    .hero-signature {
        margin-top: 25px;
    }
    .hero-signature svg {
        display: none;
    }
    .hero .eyebrow {
        font-size: 8px;
        letter-spacing: 1px;
    }
    .floating-table {
        top: 45px;
        width: 174px;
        gap: 8px;
        padding: 11px;
    }
    .floating-reservation {
        left: -10px;
        bottom: 50px;
        width: 208px;
        gap: 9px;
        padding: 12px;
    }
    .floating-reservation strong,
    .floating-table strong {
        font-size: 10px;
    }
    .floating-reservation span:not(.floating-icon):not(.micro-label),
    .floating-table > div > span {
        font-size: 8px;
    }
    .floating-icon,
    .table-code {
        height: 33px;
        width: 33px;
        font-size: 15px;
        border-radius: 9px;
    }
    .micro-label {
        font-size: 8px;
    }
    .hero-sticker {
        width: 66px;
        height: 66px;
        right: -5px;
    }
    .hero-sticker > i {
        font-size: 23px;
    }
    .photo-caption {
        left: 16px;
        right: 16px;
        font-size: 8px;
    }
    .hero-note {
        font-size: 9px;
    }
    .platform-section {
        padding-block: 75px;
    }
    .platform-grid {
        gap: 28px;
        grid-template-columns: 0.95fr 1.05fr;
    }
    .feature-tabs button {
        padding: 16px 10px;
        gap: 10px;
    }
    .feature-icon {
        width: 38px;
        height: 38px;
        font-size: 16px;
        border-radius: 10px;
    }
    .feature-tabs strong {
        font-size: 13px;
    }
    .feature-tabs button > span > span {
        font-size: 10px;
    }
    .feature-arrow {
        display: none;
    }
    .feature-preview {
        padding-inline: 18px;
    }
    .preview-top {
        padding-inline: 9px;
        font-size: 8px;
        gap: 7px;
    }
    .workflow-grid {
        gap: 40px;
    }
    .order-demo {
        padding: 22px;
    }
    .order-steps button {
        font-size: 8px;
        gap: 4px;
    }
    .order-steps button > span {
        height: 18px;
        width: 18px;
    }
    .order-ticket {
        padding: 17px;
    }
    .workflow-section {
        padding-block: 75px;
    }
    .landing h2 {
        font-size: 41px;
    }
    .kitchen-section {
        padding-top: 75px;
    }
    .kitchen-copy {
        padding: 50px 35px;
        max-width: 475px;
    }
    .kitchen-copy h2 {
        font-size: 42px;
    }
    .faq-section {
        gap: 35px;
        padding-block: 75px;
    }
    .faq-list summary {
        font-size: 12px;
        line-height: 1.7;
    }
    .closing-card h2 {
        font-size: 45px;
    }
    .footer-links {
        gap: 20px;
    }
    .delivery-map-art {
        margin-inline: -18px;
    }
    .menu-preview-heading h3 {
        font-size: 15px;
    }
}
@media (max-width: 640px) {
    .section-wrap {
        width: calc(100% - 40px);
    }
    .nav-wrap {
        width: calc(100% - 32px);
        height: 76px;
        gap: 10px;
    }
    .wordmark {
        font-size: 24px;
    }
    .brand-mark {
        width: 30px;
        height: 30px;
        margin-right: 7px;
        font-size: 25px;
        border-radius: 10px;
    }
    .nav-actions {
        gap: 9px;
    }
    .nav-login {
        display: none;
    }
    .mobile-toggle {
        display: grid;
        place-items: center;
        background: none;
        border: 0;
        color: var(--ink);
        width: 31px;
        height: 36px;
        font-size: 17px;
    }
    .landing :deep(.p-select) {
        min-width: 122px !important;
        font-size: 11px !important;
        border-radius: 10px !important;
    }
    .landing :deep(.p-select-label) {
        padding: 8px 8px;
    }
    .mobile-navigation {
        display: grid;
        position: absolute;
        left: -16px;
        right: -16px;
        top: 76px;
        background: var(--cream);
        border-bottom: 1px solid #e3e7d9;
        padding: 18px 24px;
        gap: 20px;
        box-shadow: 0 14px 20px #18271808;
    }
    .mobile-navigation a {
        color: var(--ink);
        font-size: 14px;
    }
    .hero {
        grid-template-columns: 1fr;
        gap: 35px;
        padding-block: 42px 39px;
    }
    .hero-copy {
        text-align: center;
    }
    .hero .eyebrow {
        justify-content: center;
        font-size: 8px;
        letter-spacing: 1px;
    }
    .hero h1 {
        font-size: clamp(48px, 12vw, 67px);
        margin-top: 22px;
        letter-spacing: -2.8px;
        line-height: 1.09;
    }
    .hero h1 em {
        margin-inline: auto;
        letter-spacing: -2.8px;
    }
    .hero h1 em svg {
        height: 13px;
        bottom: -2px;
    }
    .hero-description {
        font-size: 14px;
        max-width: 355px;
        margin: 23px auto 0 !important;
        line-height: 1.85;
    }
    .hero-actions {
        justify-content: center;
        gap: 15px;
        margin-top: 24px;
    }
    .hero-actions .button {
        font-size: 11px;
        padding: 16px 15px;
        gap: 10px;
    }
    .hero-actions .button-quiet {
        padding: 12px 0;
    }
    .play-icon {
        width: 29px;
        height: 29px;
        font-size: 9px;
    }
    .hero-note {
        justify-content: center;
        font-size: 9px;
        margin-top: 13px !important;
    }
    .hero-signature {
        display: none;
    }
    .hero-stage {
        padding: 8px 16px 27px;
        max-width: 465px;
        width: 100%;
        margin: auto;
    }
    .hero-photo {
        height: 420px;
        border-radius: 145px 145px 25px 25px;
    }
    .floating-table {
        right: 0;
        top: 48px;
        width: 188px;
        padding: 12px;
    }
    .floating-reservation {
        left: 0;
        bottom: 35px;
        width: 225px;
        padding: 13px;
    }
    .floating-reservation strong,
    .floating-table strong {
        font-size: 11px;
    }
    .floating-reservation span:not(.floating-icon):not(.micro-label),
    .floating-table > div > span {
        font-size: 9px;
    }
    .floating-icon,
    .table-code {
        width: 35px;
        height: 35px;
    }
    .hero-sticker {
        right: 0;
        bottom: 0;
        width: 70px;
        height: 70px;
    }
    .photo-caption {
        bottom: 21px;
    }
    .proof-strip {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 24px 8px;
        padding-block: 22px;
    }
    .proof-strip > p {
        grid-column: 1/-1;
        font-size: 19px;
        max-width: none;
        text-align: center;
    }
    .proof-strip > div {
        display: grid;
        text-align: center;
        justify-items: center;
        gap: 6px;
    }
    .proof-strip strong {
        font-size: 25px;
    }
    .proof-strip span {
        font-size: 9px;
        max-width: 100px;
    }
    .platform-section {
        padding-block: 65px;
    }
    .section-heading {
        margin-bottom: 27px;
        text-align: center;
    }
    .section-heading .eyebrow {
        justify-content: center;
        font-size: 9px;
    }
    .landing h2 {
        font-size: 37px;
        letter-spacing: -1.7px;
    }
    .landing h2 em {
        letter-spacing: -1.7px;
    }
    .section-heading h2 em {
        display: block;
        margin-left: 0;
    }
    .section-heading > p:last-child {
        font-size: 12px;
        line-height: 1.8;
        margin-top: 17px;
        max-width: 330px;
        margin-inline: auto;
    }
    .platform-grid {
        grid-template-columns: 1fr;
        gap: 25px;
    }
    .feature-tabs {
        grid-template-columns: repeat(2, 1fr);
        gap: 7px;
    }
    .feature-tabs button {
        padding: 13px 10px;
        gap: 8px;
        border: 1px solid #e7e9df;
        align-items: center;
    }
    .feature-tabs .feature-icon {
        width: 29px;
        height: 29px;
        border-radius: 8px;
        font-size: 13px;
    }
    .feature-tabs strong {
        font-size: 11px;
        line-height: 1.5;
    }
    .feature-tabs button > span > span {
        display: none;
    }
    .feature-preview {
        padding-inline: 25px;
        height: 360px;
    }
    .preview-shell {
        transform: none;
        border-radius: 20px;
    }
    .preview-top {
        font-size: 8px;
        padding-inline: 14px;
    }
    .menu-preview-heading h3 {
        font-size: 16px;
    }
    .delivery-map-art {
        margin-inline: -25px;
        height: 254px;
    }
    .qr-art {
        height: 145px;
        width: 145px;
    }
    .calendar-grid {
        gap: 8px;
    }
    .calendar-grid > span {
        height: 30px;
    }
    .workflow-section {
        padding-block: 62px;
    }
    .workflow-grid {
        grid-template-columns: 1fr;
        gap: 35px;
    }
    .workflow-copy > .eyebrow {
        font-size: 9px;
    }
    .workflow-copy > p:not(.eyebrow) {
        font-size: 13px;
        margin-top: 20px;
    }
    .workflow-copy ol {
        gap: 20px;
        margin-top: 25px;
    }
    .workflow-copy li p {
        font-size: 11px;
    }
    .order-demo {
        transform: none;
        padding: 22px;
        max-width: 450px;
        width: 100%;
        margin: auto;
    }
    .order-steps button {
        font-size: 9px;
        gap: 6px;
    }
    .order-demo-heading h3 {
        font-size: 18px;
    }
    .ticket-line strong {
        font-size: 12px;
    }
    .ticket-status {
        font-size: 12px;
    }
    .demo-disclaimer {
        font-size: 9px;
    }
    .kitchen-section {
        padding-top: 65px;
    }
    .kitchen-story {
        min-height: 500px;
        border-radius: 23px;
    }
    .kitchen-story > img {
        object-position: 63% center;
    }
    .kitchen-shade {
        background: linear-gradient(90deg, #131a17e8, #17221a8c);
    }
    .kitchen-copy {
        padding: 40px 25px;
        max-width: 100%;
    }
    .kitchen-copy h2 {
        font-size: 37px;
        line-height: 1.16;
    }
    .kitchen-copy > p {
        font-size: 12px;
        line-height: 1.9;
        max-width: 270px;
    }
    .story-tag {
        font-size: 8px;
    }
    .story-chips {
        gap: 13px;
        font-size: 9px;
    }
    .story-corner {
        right: 22px;
        bottom: 22px;
    }
    .faq-section {
        grid-template-columns: 1fr;
        gap: 28px;
        padding-block: 65px;
        scroll-margin-top: 90px;
    }
    .faq-intro > .eyebrow {
        font-size: 9px;
    }
    .faq-intro h2 em {
        display: inline;
        margin-left: 8px;
    }
    .faq-intro > p:last-child {
        max-width: 290px;
    }
    .faq-list summary {
        font-size: 12px;
        padding-block: 20px;
    }
    .faq-list details > p {
        font-size: 12px;
    }
    .closing-section {
        padding-bottom: 45px;
    }
    .closing-card {
        padding: 48px 22px;
        border-radius: 23px;
    }
    .closing-card h2 {
        font-size: 36px;
    }
    .closing-card > p:not(.eyebrow) {
        font-size: 11px;
    }
    .closing-spark {
        right: 4%;
        top: 8%;
        font-size: 45px;
    }
    .closing-card .button {
        font-size: 12px;
        padding: 17px 20px;
    }
    .closing-note {
        font-size: 8px;
    }
    .site-footer {
        gap: 25px;
        padding-bottom: 25px;
    }
    .site-footer > div > p {
        font-size: 11px;
        max-width: 230px;
    }
    .footer-links {
        gap: 22px;
        padding-top: 0;
        flex-wrap: wrap;
    }
    .footer-bottom {
        flex-direction: column;
        gap: 8px;
        font-size: 9px;
    }
    .photo-caption {
        font-size: 8px;
    }
    .hero-photo > img {
        object-position: 48% center;
    }
}
@media (max-width: 360px) {
    .section-wrap {
        width: calc(100% - 32px);
    }
    .nav-wrap {
        width: calc(100% - 24px);
    }
    .wordmark {
        font-size: 21px;
    }
    .brand-mark {
        width: 26px;
        height: 26px;
        font-size: 23px;
    }
    .landing :deep(.p-select) {
        min-width: 112px !important;
    }
    .hero h1 {
        font-size: 46px;
    }
    .hero-actions {
        gap: 10px;
    }
    .hero-actions .button {
        font-size: 10px;
        gap: 8px;
        padding-inline: 12px;
    }
    .hero-photo {
        height: 380px;
    }
    .feature-tabs strong {
        font-size: 10px;
    }
    .feature-preview {
        padding-inline: 18px;
    }
    .delivery-map-art {
        margin-inline: -18px;
    }
    .order-demo {
        padding: 17px;
    }
    .order-steps button {
        font-size: 8px;
    }
    .floating-reservation {
        width: 210px;
    }
}
@media (prefers-reduced-motion: reduce) {
    .landing *,
    .landing *::before,
    .landing *::after {
        animation: none !important;
        transition: none !important;
        scroll-behavior: auto !important;
    }
    .motion-ready [data-reveal] {
        opacity: 1;
        transform: none;
    }
}
</style>
