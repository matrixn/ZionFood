<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import StorefrontLayout from '@/layouts/StorefrontLayout.vue';
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
    tableContext?: { public_id: string; code: string; area: string; ordering_paused: boolean };
}>();
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
        ><Head :title="restaurant.name" /><template #actions
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
            <div class="grid gap-4 md:grid-cols-2">
                <button
                    v-for="p in c.items"
                    :key="p.public_id"
                    class="panel flex gap-5 text-left transition-shadow hover:shadow-md"
                    :disabled="!location.accepting_orders"
                    @click="
                        product = p;
                        configure = true;
                    "
                >
                    <div class="min-w-0 flex-1">
                        <h3 class="text-lg font-semibold">{{ p.name }}{{ productMarks(p) }}</h3>
                        <p class="muted my-3 line-clamp-2">{{ p.description }}</p>
                        <ProductIngredients :product="p" />
                        <strong>{{ money(p.price) }}</strong
                        ><span v-if="p.variants.length" class="muted ml-2">{{ $t('base_price') }}</span>
                    </div>
                    <img
                        v-if="p.image_path"
                        :src="'/storage/' + p.image_path"
                        :alt="p.name"
                        class="h-28 w-28 rounded-xl object-cover"
                    /><span v-else class="flex h-12 w-12 items-center justify-center rounded-xl bg-violet-50 text-brand"
                        ><i aria-hidden="true" class="pi pi-plus"
                    /></span>
                </button>
            </div>
        </section>
        <p v-if="!categories.length && !menuPaused" class="muted py-16 text-center">
            {{ $t('the_restaurant_is_preparing_its_menu_check_back_soon') }}
        </p>
        <section
            v-if="categories.some((c) => c.items.some((p) => productMarks(p)))"
            class="panel my-8 space-y-4"
            data-testid="allergen-notice"
        >
            <h2 class="text-xl font-semibold">{{ $t('ingredients_notice_title') }}</h2>
            <p class="whitespace-pre-line text-sm leading-relaxed text-slate-600 dark:text-slate-300">
                {{ $t('ingredients_notice') }}
            </p>
            <p class="text-xs leading-relaxed text-slate-500">
                {{ $t('ingredients_legal_note') }}
                <a
                    href="https://eur-lex.europa.eu/eli/reg/2011/1169/oj"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="underline"
                    >{{ $t('ingredients_legal_reference') }}</a
                >
            </p>
        </section>
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
