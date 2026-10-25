<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
</script>
<template>
    <Head title="Restaurant ordering, connected" />
    <div class="mx-auto max-w-6xl px-6">
        <nav class="flex items-center justify-between py-8">
            <strong class="text-2xl tracking-tight">zion<span class="text-brand">food.</span></strong
            ><Link href="/login" class="font-medium text-brand">Sign in <i class="pi pi-arrow-right ml-2" /></Link>
        </nav>
        <section class="grid items-center gap-16 py-20 md:grid-cols-2">
            <div>
                <span class="rounded-full bg-violet-100 px-4 py-2 text-sm font-medium text-brand"
                    >Your restaurant. In sync.</span
                >
                <h1 class="mt-8 text-6xl font-semibold leading-[1.08] tracking-tight md:text-7xl">
                    Less friction.<br />More <span class="text-brand">flavour.</span>
                </h1>
                <p class="muted my-7 max-w-lg !text-lg leading-relaxed">
                    Bring your menu, online orders and kitchen together. A thoughtful workspace for the people behind
                    every great meal.
                </p>
                <Link
                    href="/register"
                    class="inline-flex items-center gap-4 rounded-xl bg-brand px-6 py-4 font-medium text-white"
                    >Set up your restaurant <i class="pi pi-arrow-right"
                /></Link>
            </div>
            <div class="rounded-3xl bg-ink p-8 text-white shadow-xl">
                <div class="flex items-center justify-between">
                    <span class="text-sm text-violet-300">ONE CONNECTED WORKSPACE</span
                    ><i class="pi pi-sparkles text-violet-300" />
                </div>
                <h2 class="my-8 text-3xl font-semibold">From first click<br />to the final plate.</h2>
                <div
                    v-for="(item, index) in ['Publish your menu', 'Receive orders', 'Keep your kitchen moving']"
                    :key="item"
                    class="mt-4 flex items-center gap-4 rounded-xl border border-white/10 p-5"
                >
                    <span class="text-violet-300">0{{ index + 1 }}</span
                    ><span>{{ item }}</span
                    ><i class="pi pi-check-circle ml-auto text-emerald-400" />
                </div>
            </div>
        </section>
        <footer class="py-10 text-sm text-slate-500">ZionFood · v0.0.1</footer>
    </div>
</template>
