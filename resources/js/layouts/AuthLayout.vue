<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
</script>
<template>
    <div class="grid min-h-screen lg:grid-cols-2">
        <div class="hidden flex-col justify-between bg-ink p-14 text-white lg:flex">
            <Link href="/" class="text-3xl font-bold text-white">zion<span class="text-violet-400">food.</span></Link>
            <div>
                <span class="rounded-full border border-white/20 px-4 py-2 text-sm">Built for the way you serve.</span>
                <h1 class="mt-8 max-w-xl text-6xl font-semibold leading-tight tracking-tight">
                    Great food.<br />Seamless service.
                </h1>
                <p class="mt-6 max-w-md text-lg text-slate-400">
                    Your menu, orders and kitchen. One calm, connected workspace.
                </p>
            </div>
            <p class="text-sm text-slate-400">ZionFood · Restaurant operating system</p>
        </div>
        <div class="flex items-center justify-center p-6">
            <div class="w-full max-w-md">
                <Link href="/" class="mb-10 block text-2xl font-bold text-brand lg:hidden">ZionFood</Link><slot />
            </div>
        </div>
    </div>
</template>
