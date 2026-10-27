<script setup lang="ts">
import { tr as t } from '@/i18n';

import { computed, ref } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import Button from 'primevue/button';
import Select from 'primevue/select';
import Toast from 'primevue/toast';
import LanguageSelector from '@/components/ui/LanguageSelector.vue';
import type { Location, FeaturePermissions, StaffFeature } from '@/types';
const props = defineProps<{
    location?: Location;
    locations?: Location[];
    canManage?: boolean;
    permissions?: FeaturePermissions;
    mode?: string;
}>();
const page = usePage();
const capabilities = computed(() => props.permissions ?? (page.props.permissions as FeaturePermissions));
const featurePaths: Record<string, StaffFeature> = {
    '/orders': 'orders',
    '/reservations': 'reservations',
    '/tables': 'tables',
    '/kitchen': 'kitchen',
    '/menu': 'menu',
    '/ingredients': 'menu',
    '/menu-content': 'menu',
};
const mobile = ref(false);
const dark = ref(document.documentElement.classList.contains('dark'));
function theme() {
    dark.value = !dark.value;
    document.documentElement.classList.toggle('dark', dark.value);
}
const base = computed(() => `/app/locations/${props.location?.public_id}`);
const navGroups = computed(() =>
    [
        {
            id: 'operations',
            title: t('nav_operations'),
            items: [
                { name: t('overview'), icon: 'pi-chart-bar', path: '' },
                { name: t('orders'), icon: 'pi-shopping-bag', path: '/orders' },
                { name: t('booking_manage'), icon: 'pi-calendar', path: '/reservations' },
                { name: t('dining_tables'), icon: 'pi-qrcode', path: '/tables' },
                { name: t('kitchen_display'), icon: 'pi-th-large', path: '/kitchen' },
            ],
        },
        ...(props.canManage
            ? [
                  {
                      id: 'administration',
                      title: t('nav_administration'),
                      items: [
                          { name: t('menu_studio'), icon: 'pi-book', path: '/menu' },
                          { name: t('mc_title'), icon: 'pi-sparkles', path: '/menu-content' },
                          { name: t('ingredients_title'), icon: 'pi-list', path: '/ingredients' },
                          { name: t('staff'), icon: 'pi-users', path: '/staff' },
                          { name: t('nav_locations'), icon: 'pi-map-marker', path: '/locations' },
                          { name: t('restaurant_branding'), icon: 'pi-palette', path: '/branding' },
                          { name: t('nav_schedule_delivery'), icon: 'pi-cog', path: '/settings' },
                      ],
                  },
                  {
                      id: 'connections',
                      title: t('nav_connections'),
                      items: [
                          { name: t('integrations'), icon: 'pi-link', path: '/integrations' },
                          { name: t('webhooks'), icon: 'pi-send', path: '/webhooks' },
                      ],
                  },
              ]
            : capabilities.value?.menu !== 'none'
              ? [
                    {
                        id: 'administration',
                        title: t('nav_administration'),
                        items: [
                            { name: t('menu_studio'), icon: 'pi-book', path: '/menu' },
                            { name: t('mc_title'), icon: 'pi-sparkles', path: '/menu-content' },
                        ],
                    },
                ]
              : []),
    ]
        .map((group) => ({
            ...group,
            items: group.items.filter(
                (item) =>
                    !featurePaths[item.path] || (capabilities.value?.[featurePaths[item.path]] ?? 'none') !== 'none',
            ),
        }))
        .filter((group) => group.items.length),
);
function active(path: string) {
    const current = page.url.split('?')[0];
    return path === ''
        ? current === base.value
        : current === base.value + path || current.startsWith(base.value + path + '/');
}
function switchLocation(id: string) {
    const suffix = page.url.split('?')[0].startsWith(base.value + '/setup')
        ? page.url.split('?')[0].slice(base.value.length)
        : '';
    router.visit('/app/locations/' + id + suffix);
}
</script>
<template>
    <div class="min-h-screen">
        <Toast />
        <header
            class="flex min-h-20 items-center justify-between gap-2 border-b border-slate-200 bg-white px-4 py-4 dark:border-slate-700 dark:bg-slate-900 md:px-8"
        >
            <div class="flex items-center gap-2">
                <Button
                    icon="pi pi-bars"
                    text
                    :aria-label="$t('open_navigation')"
                    class="md:!hidden"
                    @click="mobile = !mobile"
                />
                <Link
                    href="/app"
                    class="flex items-center gap-2 text-2xl font-bold tracking-tight text-ink dark:text-white"
                    ><span class="rounded-xl bg-brand px-2.5 py-1 text-white">z</span
                    ><span class="hidden sm:inline">Zion<span class="text-brand">Food</span></span></Link
                ><span class="ml-4 hidden rounded-full bg-violet-50 px-3 py-1 text-xs text-brand lg:inline">{{
                    mode === 'Platform admin'
                        ? $t('platform_admin')
                        : mode === 'Kitchen operations'
                          ? $t('kitchen_operations')
                          : $t('restaurant_workspace')
                }}</span>
            </div>
            <div class="flex items-center gap-1.5">
                <Button
                    :icon="dark ? 'pi pi-sun' : 'pi pi-moon'"
                    text
                    :aria-label="$t('toggle_color_theme')"
                    @click="theme"
                /><Link
                    v-if="(page.props.auth as { user?: { is_platform_admin?: boolean } })?.user?.is_platform_admin"
                    href="/admin"
                    class="muted mr-2 hidden md:block"
                    >{{ $t('platform') }}</Link
                ><Button
                    icon="pi pi-sign-out"
                    text
                    :aria-label="$t('sign_out')"
                    @click="router.post('/logout')"
                /><LanguageSelector />
            </div>
        </header>
        <div class="flex">
            <aside
                v-if="location || mode === 'Platform admin'"
                :class="[
                    'w-64 shrink-0 border-r border-slate-200 bg-white p-5 dark:border-slate-700 dark:bg-slate-900 md:block',
                    mobile ? 'fixed bottom-0 top-20 z-30 block overflow-y-auto' : 'hidden',
                ]"
            >
                <p class="muted mb-3 text-xs uppercase tracking-widest">{{ $t('workspace') }}</p>
                <div v-if="location" class="mb-5 flex items-center gap-3">
                    <img
                        v-if="location.restaurant.logo_path"
                        :src="'/storage/' + location.restaurant.logo_path"
                        :alt="location.restaurant.name"
                        class="h-10 w-10 rounded-lg bg-white object-contain"
                    /><strong class="text-sm">{{ location.restaurant.name }}</strong>
                </div>
                <Select
                    v-if="locations?.length"
                    :model-value="location?.public_id"
                    :options="locations"
                    option-label="name"
                    option-value="public_id"
                    class="mb-3 w-full"
                    :aria-label="$t('active_location')"
                    @update:model-value="switchLocation"
                />
                <nav :aria-label="$t('nav_workspace')" class="space-y-6">
                    <Link
                        v-if="location && canManage"
                        :href="base + '/setup'"
                        class="flex items-center gap-3 rounded-xl border border-violet-100 px-4 py-3 text-sm font-semibold text-brand dark:border-violet-800"
                        :class="
                            active('/setup')
                                ? 'bg-violet-50 dark:bg-violet-950'
                                : 'hover:bg-violet-50 dark:hover:bg-violet-950'
                        "
                        :aria-current="active('/setup') ? 'page' : undefined"
                        @click="mobile = false"
                    >
                        <i aria-hidden="true" class="pi pi-sliders-h" />{{ $t('setup_title') }}
                    </Link>
                    <section
                        v-for="group in location ? navGroups : []"
                        :key="group.id"
                        :aria-labelledby="'nav-' + group.id"
                    >
                        <h2
                            :id="'nav-' + group.id"
                            class="mb-2 px-3 text-xs font-semibold uppercase tracking-wider text-slate-400"
                        >
                            {{ group.title }}
                        </h2>
                        <div class="grid gap-1">
                            <Link
                                v-for="item in group.items"
                                :key="item.path"
                                :href="base + item.path"
                                class="flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium"
                                :class="
                                    active(item.path)
                                        ? 'bg-violet-50 text-brand dark:bg-violet-950'
                                        : 'text-slate-500 hover:bg-slate-50 dark:hover:bg-slate-800'
                                "
                                :aria-current="active(item.path) ? 'page' : undefined"
                                @click="mobile = false"
                            >
                                <i aria-hidden="true" :class="['pi', item.icon]" />{{ item.name }}
                            </Link>
                        </div>
                    </section>
                    <template v-if="mode === 'Platform admin'">
                        <Link href="/admin" class="block p-3 text-brand">{{ $t('organizations') }}</Link>
                        <Link href="/admin/ingredients" class="block p-3 text-brand">{{
                            $t('ingredients_title')
                        }}</Link>
                        <Link href="/admin/menu-ai" class="block p-3 text-brand">{{ $t('mc_ai_settings') }}</Link>
                        <Link href="/admin/cuisines" class="block p-3 text-brand">{{ $t('cuisine_admin_title') }}</Link>
                    </template>
                </nav>
                <div v-if="location" class="mt-12 rounded-xl bg-surface p-4 dark:bg-slate-800">
                    <p class="text-sm font-semibold">{{ $t('your_storefront') }}</p>
                    <p class="muted my-2">{{ $t('make_something_delicious_happen') }}</p>
                    <a :href="location.storefront_path" target="_blank" class="text-sm font-medium text-brand"
                        >{{ $t('open_menu') }} <i aria-hidden="true" class="pi pi-arrow-up-right ml-1"
                    /></a>
                </div>
                <p class="muted mt-8 text-xs">ZionFood v{{ page.props.version }}</p>
            </aside>
            <main class="min-w-0 flex-1 p-5 md:p-9">
                <div
                    v-if="(page.props.flash as { success?: string })?.success"
                    role="status"
                    class="mb-5 rounded-xl bg-emerald-50 p-4 text-emerald-800"
                >
                    {{ (page.props.flash as { success: string }).success }}
                </div>
                <slot />
            </main>
        </div>
    </div>
</template>
