<script setup lang="ts">
import { computed, ref } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import Button from 'primevue/button';
import Select from 'primevue/select';
import Toast from 'primevue/toast';
import type { Location } from '@/types';
const props = defineProps<{ location?: Location; locations?: Location[]; canManage?: boolean; mode?: string }>();
const page = usePage();
const mobile = ref(false);
const dark = ref(document.documentElement.classList.contains('dark'));
function theme() {
    dark.value = !dark.value;
    document.documentElement.classList.toggle('dark', dark.value);
}
const base = computed(() => `/app/locations/${props.location?.public_id}`);
const nav = computed(() => [
    { name: 'Overview', icon: 'pi-chart-bar', path: '' },
    { name: 'Orders', icon: 'pi-shopping-bag', path: '/orders' },
    ...(props.canManage
        ? [
              { name: 'Menu studio', icon: 'pi-book', path: '/menu' },
              { name: 'Settings', icon: 'pi-cog', path: '/settings' },
              { name: 'Integrations', icon: 'pi-link', path: '/integrations' },
          ]
        : []),
    { name: 'Kitchen display', icon: 'pi-th-large', path: '/kitchen' },
]);
</script>
<template>
    <div class="min-h-screen">
        <Toast />
        <header
            class="flex h-20 items-center justify-between border-b border-slate-200 bg-white px-5 dark:border-slate-700 dark:bg-slate-900 md:px-8"
        >
            <div class="flex items-center gap-3">
                <Button
                    icon="pi pi-bars"
                    text
                    aria-label="Open navigation"
                    class="md:!hidden"
                    @click="mobile = !mobile"
                /><Link href="/app" class="text-2xl font-bold tracking-tight text-ink dark:text-white"
                    ><span class="mr-2 rounded-xl bg-brand px-2.5 py-1 text-white">z</span>Zion<span class="text-brand"
                        >Food</span
                    ></Link
                ><span class="ml-4 hidden rounded-full bg-violet-50 px-3 py-1 text-xs text-brand sm:inline">{{
                    mode ?? 'Restaurant workspace'
                }}</span>
            </div>
            <div class="flex items-center gap-2">
                <Button
                    :icon="dark ? 'pi pi-sun' : 'pi pi-moon'"
                    text
                    aria-label="Toggle color theme"
                    @click="theme"
                /><Link
                    v-if="(page.props.auth as { user?: { is_platform_admin?: boolean } })?.user?.is_platform_admin"
                    href="/admin"
                    class="muted mr-3"
                    >Platform</Link
                ><Button label="Sign out" icon="pi pi-sign-out" text size="small" @click="router.post('/logout')" />
            </div>
        </header>
        <div class="flex">
            <aside
                v-if="location || mode === 'Platform admin'"
                :class="[
                    'w-64 shrink-0 border-r border-slate-200 bg-white p-5 dark:border-slate-700 dark:bg-slate-900 md:block',
                    mobile ? 'fixed inset-y-20 z-30 block' : 'hidden',
                ]"
            >
                <p class="muted mb-3 text-xs uppercase tracking-widest">Workspace</p>
                <Select
                    v-if="locations?.length"
                    :model-value="location?.public_id"
                    :options="locations"
                    option-label="name"
                    option-value="public_id"
                    class="mb-7 w-full"
                    aria-label="Active location"
                    @update:model-value="router.visit('/app/locations/' + $event)"
                />
                <nav class="grid gap-2">
                    <Link
                        v-for="item in location ? nav : []"
                        :key="item.path"
                        :href="base + item.path"
                        :class="[
                            'flex items-center gap-3 rounded-xl px-4 py-3 text-sm font-medium',
                            page.url.split('?')[0] === base + item.path
                                ? 'bg-violet-50 text-brand dark:bg-violet-950'
                                : 'text-slate-500 hover:bg-slate-50 dark:hover:bg-slate-800',
                        ]"
                        ><i :class="['pi', item.icon]" />{{ item.name }}</Link
                    ><Link v-if="mode === 'Platform admin'" href="/admin" class="p-3 text-brand">Organizations</Link>
                </nav>
                <div v-if="location" class="mt-12 rounded-xl bg-surface p-4 dark:bg-slate-800">
                    <p class="text-sm font-semibold">Your storefront</p>
                    <p class="muted my-2">Make something delicious happen.</p>
                    <a :href="'/store/' + location.public_id" target="_blank" class="text-sm font-medium text-brand"
                        >Open menu <i class="pi pi-arrow-up-right ml-1"
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
