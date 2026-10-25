<script setup lang="ts">
import LanguageSelector from '@/components/ui/LanguageSelector.vue';
import { Head, Link } from '@inertiajs/vue3';
</script>
<template>
    <Head :title="$t('restaurant_ordering_connected')" />
    <div class="mx-auto max-w-6xl px-6">
        <nav class="flex items-center justify-between py-8">
            <strong class="text-2xl tracking-tight">zion<span class="text-brand">food.</span></strong>
            <div class="flex items-center gap-4">
                <Link href="/login" :aria-label="$t('sign_in')" class="font-medium text-brand"
                    ><span class="hidden sm:inline">{{ $t('sign_in') }}</span>
                    <i aria-hidden="true" class="pi pi-arrow-right ml-2" /></Link
                ><LanguageSelector />
            </div>
        </nav>
        <section class="grid items-center gap-16 py-20 md:grid-cols-2">
            <div>
                <span class="rounded-full bg-violet-100 px-4 py-2 text-sm font-medium text-brand">{{
                    $t('your_restaurant_in_sync')
                }}</span>
                <h1 class="mt-8 text-6xl font-semibold leading-[1.08] tracking-tight md:text-7xl">
                    {{ $t('less_friction') }}<br />{{ $t('more') }} <span class="text-brand">{{ $t('flavour') }}</span>
                </h1>
                <p class="muted my-7 max-w-lg !text-lg leading-relaxed">
                    {{
                        $t('bring_your_menu_online_orders_and_kitchen_together_a_thoughtful_workspace_for_the_people_b')
                    }}
                </p>
                <Link
                    href="/register"
                    class="inline-flex items-center gap-4 rounded-xl bg-brand px-6 py-4 font-medium text-white"
                    >{{ $t('set_up_your_restaurant') }} <i aria-hidden="true" class="pi pi-arrow-right"
                /></Link>
            </div>
            <div class="rounded-3xl bg-ink p-8 text-white shadow-xl">
                <div class="flex items-center justify-between">
                    <span class="text-sm text-violet-300">{{ $t('one_connected_workspace') }}</span
                    ><i aria-hidden="true" class="pi pi-sparkles text-violet-300" />
                </div>
                <h2 class="my-8 text-3xl font-semibold">
                    {{ $t('from_first_click') }}<br />{{ $t('to_the_final_plate') }}
                </h2>
                <div
                    v-for="(item, index) in [
                        $t('publish_your_menu'),
                        $t('receive_orders'),
                        $t('keep_your_kitchen_moving'),
                    ]"
                    :key="item"
                    class="mt-4 flex items-center gap-4 rounded-xl border border-white/10 p-5"
                >
                    <span class="text-violet-300">0{{ index + 1 }}</span
                    ><span>{{ item }}</span
                    ><i aria-hidden="true" class="pi pi-check-circle ml-auto text-emerald-400" />
                </div>
            </div>
        </section>
        <footer class="py-10 text-sm text-slate-500">ZionFood · v0.0.1</footer>
    </div>
</template>
