<script setup lang="ts">
import { computed, ref } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import Drawer from 'primevue/drawer';
import Button from 'primevue/button';
import AdminNavigation from '@/components/ui/AdminNavigation.vue';
import LanguageSelector from '@/components/ui/LanguageSelector.vue';
import ZFLogo from '@/components/ui/ZFLogo.vue';
import { adminActive, adminGroups } from '@/lib/adminNavigation';
const page = usePage();
const mobile = ref(false);
const dark = ref(document.documentElement.classList.contains('dark'));
const current = computed(
    () => adminGroups.flatMap((g) => g.items).find((item) => adminActive(page.url, item.href))?.key ?? 'pa_dashboard',
);
const user = computed(() => (page.props.auth as { user?: { name: string; email: string } })?.user);
function theme() {
    dark.value = !dark.value;
    document.documentElement.classList.toggle('dark', dark.value);
}
</script>
<template>
    <div class="platform-admin min-h-screen bg-[#f5f7fb] dark:bg-slate-950">
        <aside class="admin-sidebar hidden lg:flex">
            <Link href="/admin" class="admin-brand"
                ><ZFLogo :size="36" /><span
                    >Zion<span class="text-brand">Food</span><small>{{ $t('pa_console') }}</small></span
                ></Link
            >
            <AdminNavigation />
            <div class="admin-sidebar-footer">
                <span class="admin-avatar">{{ user?.name?.slice(0, 1) }}</span>
                <div class="min-w-0">
                    <strong class="block truncate text-sm">{{ user?.name }}</strong
                    ><span class="muted">{{ $t('platform_admin') }}</span>
                </div>
            </div>
        </aside>
        <Drawer v-model:visible="mobile" :header="$t('pa_console')" class="!w-80"
            ><AdminNavigation @navigate="mobile = false"
        /></Drawer>
        <div class="min-w-0 lg:ml-64">
            <header class="admin-topbar">
                <div class="flex min-w-0 items-center gap-3">
                    <Button
                        icon="pi pi-bars"
                        text
                        class="lg:!hidden"
                        :aria-label="$t('open_navigation')"
                        @click="mobile = true"
                    />
                    <div class="min-w-0">
                        <span class="hidden text-xs text-slate-500 sm:block">{{ $t('pa_console') }}</span
                        ><span class="block truncate text-sm font-semibold">{{ $t(current) }}</span>
                    </div>
                </div>
                <div class="flex items-center gap-1 sm:gap-3">
                    <Link href="/" class="admin-header-link hidden xl:flex"
                        ><i class="pi pi-external-link" aria-hidden="true" />{{ $t('pa_public_site') }}</Link
                    ><Link href="/app" class="admin-header-link hidden md:flex" :title="$t('pa_workspace_help')">{{
                        $t('restaurant_workspace')
                    }}</Link
                    ><Button
                        :icon="dark ? 'pi pi-sun' : 'pi pi-moon'"
                        text
                        :aria-label="$t('toggle_color_theme')"
                        @click="theme"
                    /><LanguageSelector /><Button
                        icon="pi pi-sign-out"
                        text
                        :aria-label="$t('sign_out')"
                        @click="router.post('/logout')"
                    />
                </div>
            </header>
            <main id="admin-content" class="mx-auto max-w-[1600px] p-4 sm:p-7 xl:p-10"><slot /></main>
            <footer
                class="mx-auto flex max-w-[1600px] flex-wrap justify-between gap-2 px-7 pb-6 text-xs text-slate-500"
            >
                <span>ZionFood · {{ $t('pa_console') }}</span
                ><span>v{{ page.props.version }}</span>
            </footer>
        </div>
    </div>
</template>
