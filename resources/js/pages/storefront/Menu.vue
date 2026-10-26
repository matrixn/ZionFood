<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import StorefrontLayout from '@/layouts/StorefrontLayout.vue';
import AllergenNotice from '@/components/storefront/AllergenNotice.vue';
import ProductPhoto from '@/components/storefront/ProductPhoto.vue';
import ProductIngredients from '@/components/storefront/ProductIngredients.vue';
import { productMarks } from '@/lib/ingredients';
import ProductConfigurator from '@/components/storefront/ProductConfigurator.vue';
import CartDrawer from '@/components/storefront/CartDrawer.vue';
import CheckoutForm from '@/components/storefront/CheckoutForm.vue';
import Button from 'primevue/button';
import { useCart } from '@/stores/cart';
import { api, money } from '@/lib/http';
import type { Menu, Product, Zone, RestaurantBrand } from '@/types';
import type { MapConfig } from '@/lib/deliveryGeometry';
const props = defineProps<{
    location: { public_id: string; name: string; address: string; accepting_orders: boolean };
    restaurant: RestaurantBrand;
    menus: Menu[];
    fulfillment: { pickup_enabled: boolean; delivery_enabled: boolean; reservations_enabled?: boolean };
    zones: Zone[];
    mapConfig: MapConfig;
    mapCenter: number[];
    shareMeta?: { title: string; name: string; description: string; image: string | null; url: string; locale: string };
    tableContext?: { public_id: string; code: string; area: string; ordering_paused: boolean };
}>();
onMounted(() => document.querySelectorAll('[data-storefront-meta]').forEach((node) => node.remove()));
const cart = useCart();
cart.scope(props.location.public_id + (props.tableContext ? `:${props.tableContext.public_id}` : ''));
const sessionToken = ref<string | null>(null);
const menuPaused = ref(props.tableContext?.ordering_paused ?? false);
const tableError = ref('');
const tableBusy = ref(false);
async function connectTable() {
    if (!props.tableContext) return;
    const id = props.tableContext.public_id;
    const fragment = new URLSearchParams(window.location.hash.slice(1)).get('token');
    if (fragment?.length === 64) sessionStorage.setItem(`zf-table-qr-${id}`, fragment);
    history.replaceState(null, '', window.location.pathname);
    const proof = sessionStorage.getItem(`zf-table-qr-${id}`);
    if (!proof) {
        tableError.value = 'dining_scan_again';
        return;
    }
    tableBusy.value = true;
    tableError.value = '';
    try {
        const result = await api<{ session_id: string; token: string }>(`/api/v1/public/tables/${id}/connect`, 'POST', {
            qr_token: proof,
        });
        sessionToken.value = result.token;
        menuPaused.value = false;
        cart.scope(`${props.location.public_id}:${id}:${result.session_id}`);
    } catch (e) {
        sessionToken.value = null;
        tableError.value = (e as Error).message;
    } finally {
        tableBusy.value = false;
    }
}
onMounted(() => {
    if (props.tableContext) void connectTable();
});
const categories = computed(() => (menuPaused.value ? [] : props.menus.flatMap((m) => m.categories)));
const product = ref<Product | null>(null);
const configure = ref(false);
const drawer = ref(false);
const checkout = ref(false);
</script>
<template>
    <StorefrontLayout :name="restaurant.name" :brand="restaurant"
        ><Head :title="restaurant.name + ' ' + $t('digital_menu')">
            <meta
                head-key="store-description"
                name="description"
                :content="shareMeta?.description || restaurant.description || restaurant.name"
            />
            <meta head-key="og-title" property="og:title" :content="restaurant.name + ' ' + $t('digital_menu')" />
            <meta head-key="og-name" property="og:site_name" :content="restaurant.name" />
            <meta head-key="og-type" property="og:type" content="website" />
            <link v-if="shareMeta" head-key="canonical" rel="canonical" :href="shareMeta.url" />
            <meta head-key="twitter-card" name="twitter:card" content="summary" />
            <meta head-key="twitter-title" name="twitter:title" :content="restaurant.name + ' ' + $t('digital_menu')" />
            <meta v-if="shareMeta?.image" head-key="twitter-image" name="twitter:image" :content="shareMeta.image" />
            <meta v-if="shareMeta" head-key="og-locale" property="og:locale" :content="shareMeta.locale" />
            <meta v-if="shareMeta" head-key="og-url" property="og:url" :content="shareMeta.url" />
            <meta
                v-if="shareMeta"
                head-key="og-description"
                property="og:description"
                :content="shareMeta.description"
            />
            <meta v-if="shareMeta?.image" head-key="og-image" property="og:image" :content="shareMeta.image" />
            <meta
                v-if="shareMeta?.image"
                head-key="og-image-alt"
                property="og:image:alt"
                :content="restaurant.name"
            /> </Head
        ><template #actions
            ><Button
                :label="$t('cart_count', { count: cart.lines.length })"
                icon="pi pi-shopping-bag"
                :disabled="!!tableContext && !sessionToken"
                @click="drawer = true"
        /></template>
        <section v-if="tableContext" class="panel mb-6 space-y-3">
            <p v-if="menuPaused" class="rounded-xl bg-amber-50 p-4 text-amber-900">{{ $t('dining_menu_paused') }}</p>
            <h2 class="text-xl font-semibold">
                {{ $t('dining_table') }} {{ tableContext.code }} · {{ tableContext.area }}
            </h2>
            <p v-if="tableError" role="alert" class="text-red-700">
                {{ tableError === 'dining_scan_again' ? $t(tableError) : tableError }}
            </p>
            <p v-if="sessionToken" class="font-medium text-brand">{{ $t('dining_session_ready') }}</p>
            <Button v-else :label="$t('dining_connect')" :loading="tableBusy" @click="connectTable" />
        </section>
        <Link
            v-if="fulfillment.reservations_enabled && !tableContext"
            :href="`/reserve/${location.public_id}`"
            class="mb-6 inline-block rounded-xl bg-brand px-5 py-3 font-semibold text-white"
            >{{ $t('booking_title') }}</Link
        >
        <section class="relative mb-8 overflow-hidden rounded-3xl bg-ink p-8 text-white md:p-12">
            <img
                v-if="restaurant.cover_path"
                :src="'/storage/' + restaurant.cover_path"
                :alt="restaurant.name"
                class="pointer-events-none absolute inset-0 h-full w-full object-cover opacity-30"
            />
            <div class="relative">
                <span class="text-sm text-violet-300">{{ restaurant.tagline || $t('freshly_made_just_for_you') }}</span>
                <h1 class="mt-4 text-4xl font-semibold tracking-tight md:text-5xl">{{ restaurant.name }}</h1>
                <p class="mt-4 max-w-xl text-slate-300">
                    {{ restaurant.description || $t('good_food_brings_people_together_find_your_favourite_below') }}
                </p>
                <p class="mt-6 text-sm text-slate-400">
                    <i aria-hidden="true" class="pi pi-map-marker mr-2" />{{ location.address }}
                </p>
            </div>
        </section>
        <p v-if="!location.accepting_orders" role="status" class="mb-5 rounded-xl bg-amber-50 p-4 text-amber-900">
            {{ $t('online_orders_are_currently_paused_you_can_still_browse_the_menu') }}
        </p>
        <nav class="sticky top-0 z-10 mb-7 flex gap-2 overflow-auto rounded-xl bg-surface py-3 dark:bg-slate-950">
            <a
                v-for="c in categories"
                :key="c.id"
                :href="'#category-' + c.id"
                class="whitespace-nowrap rounded-full border border-slate-200 bg-white px-5 py-2 text-sm text-slate-600 dark:bg-slate-800 dark:text-slate-200"
                >{{ c.name }}</a
            >
        </nav>
        <section v-for="c in categories" :id="'category-' + c.id" :key="c.id" class="mb-10 scroll-mt-20">
            <h2 class="mb-5 text-2xl font-semibold">{{ c.name }}</h2>
            <div class="grid gap-4 lg:grid-cols-2">
                <button
                    v-for="p in c.items"
                    :key="p.public_id"
                    class="group flex w-full gap-4 rounded-2xl border border-slate-200 bg-white p-4 text-left shadow-sm transition hover:border-violet-200 hover:shadow-md dark:border-slate-700 dark:bg-slate-900"
                    :disabled="!location.accepting_orders"
                    data-testid="menu-product-card"
                    @click="
                        product = p;
                        configure = true;
                    "
                >
                    <div class="h-24 w-24 shrink-0 overflow-hidden rounded-xl sm:h-32 sm:w-32">
                        <ProductPhoto :path="p.image_path" :name="p.name" />
                    </div>
                    <div class="flex min-w-0 flex-1 flex-col gap-2">
                        <h3
                            class="text-base font-semibold leading-snug tracking-tight text-ink dark:text-white sm:text-lg"
                        >
                            {{ p.name }}
                        </h3>
                        <span
                            v-if="p.portion_size"
                            class="w-fit rounded-full bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-500 dark:bg-slate-800"
                            >{{ $t('product_serving') }} · {{ p.portion_size }}</span
                        >
                        <p v-if="p.description" class="text-sm leading-relaxed text-slate-600 dark:text-slate-300">
                            {{ p.description }}
                        </p>
                        <ProductIngredients :product="p" />
                        <div class="mt-auto flex items-end justify-between gap-2 pt-2">
                            <div>
                                <strong class="text-lg font-semibold">{{ money(p.price) }}</strong
                                ><span v-if="p.variants.length" class="ml-1 text-xs text-slate-400">{{
                                    $t('base_price')
                                }}</span>
                            </div>
                            <span
                                class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-brand text-white transition-transform motion-safe:group-hover:rotate-90"
                                ><i class="pi pi-plus text-xs" aria-hidden="true"
                            /></span>
                        </div>
                    </div>
                </button>
            </div>
        </section>
        <p v-if="!categories.length && !menuPaused" class="muted py-16 text-center">
            {{ $t('the_restaurant_is_preparing_its_menu_check_back_soon') }}
        </p>
        <AllergenNotice v-if="categories.some((c) => c.items.some((p) => productMarks(p)))" />
        <ProductConfigurator v-model:visible="configure" :product="product" /><CartDrawer
            v-model:visible="drawer"
            @checkout="checkout = true" /><CheckoutForm
            v-model:visible="checkout"
            :location-id="location.public_id"
            :fulfillment="fulfillment"
            :zones="zones"
            :table-session-token="sessionToken"
            :map-config="mapConfig"
            :map-center="mapCenter"
    /></StorefrontLayout>
</template>
