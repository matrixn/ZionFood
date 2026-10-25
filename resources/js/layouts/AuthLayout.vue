<script setup lang="ts">
import LanguageSelector from '@/components/ui/LanguageSelector.vue';
import { Link } from '@inertiajs/vue3';
</script>
<template>
    <div class="relative grid min-h-screen lg:grid-cols-2">
        <div class="absolute right-6 top-6 z-10"><LanguageSelector /></div>
        <div class="hidden flex-col justify-between bg-ink p-14 text-white lg:flex">
            <Link href="/" class="text-3xl font-bold text-white">zion<span class="text-violet-400">food.</span></Link>
            <div>
                <span class="rounded-full border border-white/20 px-4 py-2 text-sm">{{
                    $t('built_for_the_way_you_serve')
                }}</span>
                <h1 class="mt-8 max-w-xl text-6xl font-semibold leading-tight tracking-tight">
                    {{ $t('great_food') }}<br />{{ $t('seamless_service') }}
                </h1>
                <p class="mt-6 max-w-md text-lg text-slate-400">
                    {{ $t('your_menu_orders_and_kitchen_one_calm_connected_workspace') }}
                </p>
            </div>
            <p class="text-sm text-slate-400">{{ $t('zionfood_restaurant_operating_system') }}</p>
        </div>
        <div class="flex items-center justify-center px-6 pb-8 pt-24 lg:pb-12 lg:pt-24">
            <div class="w-full max-w-md">
                <Link href="/" class="mb-10 block text-2xl font-bold text-brand lg:hidden">ZionFood</Link><slot />
            </div>
        </div>
    </div>
</template>
