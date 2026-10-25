<script setup lang="ts">
import { computed, ref } from 'vue';
import { Head } from '@inertiajs/vue3';
import StorefrontLayout from '@/layouts/StorefrontLayout.vue';
import ProductConfigurator from '@/components/storefront/ProductConfigurator.vue';
import CartDrawer from '@/components/storefront/CartDrawer.vue';
import CheckoutForm from '@/components/storefront/CheckoutForm.vue';
import Button from 'primevue/button';
import { useCart } from '@/stores/cart';
import { money } from '@/lib/http';
import type { Menu, Product, Zone } from '@/types';
const props = defineProps<{
    location: { public_id: string; name: string; address: string; accepting_orders: boolean };
    restaurant: { name: string; description: string | null };
    menus: Menu[];
    fulfillment: { pickup_enabled: boolean; delivery_enabled: boolean };
    zones: Zone[];
}>();
const cart = useCart();
cart.scope(props.location.public_id);
const categories = computed(() => props.menus.flatMap((m) => m.categories));
const product = ref<Product | null>(null);
const configure = ref(false);
const drawer = ref(false);
const checkout = ref(false);
</script>
<template>
    <StorefrontLayout :name="restaurant.name"
        ><Head :title="restaurant.name" /><template #actions
            ><Button :label="'Your cart (' + cart.lines.length + ')'" icon="pi pi-shopping-bag" @click="drawer = true"
        /></template>
        <section class="mb-8 rounded-3xl bg-ink p-8 text-white md:p-12">
            <span class="text-sm text-violet-300">FRESHLY MADE. JUST FOR YOU.</span>
            <h1 class="mt-4 text-4xl font-semibold tracking-tight md:text-5xl">{{ restaurant.name }}</h1>
            <p class="mt-4 max-w-xl text-slate-300">
                {{ restaurant.description || 'Good food brings people together. Find your favourite below.' }}
            </p>
            <p class="mt-6 text-sm text-slate-400"><i class="pi pi-map-marker mr-2" />{{ location.address }}</p>
        </section>
        <p v-if="!location.accepting_orders" role="status" class="mb-5 rounded-xl bg-amber-50 p-4 text-amber-900">
            Online orders are currently paused. You can still browse the menu.
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
                    <div class="flex-1">
                        <h3 class="text-lg font-semibold">{{ p.name }}</h3>
                        <p class="muted my-3 line-clamp-2">{{ p.description }}</p>
                        <strong>{{ money(p.price) }}</strong
                        ><span v-if="p.variants.length" class="muted ml-2">base price</span>
                    </div>
                    <img
                        v-if="p.image_path"
                        :src="'/storage/' + p.image_path"
                        :alt="p.name"
                        class="h-28 w-28 rounded-xl object-cover"
                    /><span v-else class="flex h-12 w-12 items-center justify-center rounded-xl bg-violet-50 text-brand"
                        ><i class="pi pi-plus"
                    /></span>
                </button>
            </div>
        </section>
        <p v-if="!categories.length" class="muted py-16 text-center">
            The restaurant is preparing its menu. Check back soon.
        </p>
        <ProductConfigurator v-model:visible="configure" :product="product" /><CartDrawer
            v-model:visible="drawer"
            @checkout="checkout = true" /><CheckoutForm
            v-model:visible="checkout"
            :location-id="location.public_id"
            :fulfillment="fulfillment"
            :zones="zones"
    /></StorefrontLayout>
</template>
