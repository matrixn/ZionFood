<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
defineProps<{ name?: string }>();
</script>
<template>
    <div class="min-h-screen">
        <header class="border-b border-slate-200 bg-white px-6 py-5 dark:bg-slate-900">
            <div class="mx-auto flex max-w-6xl items-center justify-between">
                <span class="text-xl font-bold">{{ name ?? 'Your order' }}</span
                ><slot name="actions" />
            </div>
        </header>
        <main class="mx-auto max-w-6xl p-5 md:p-8"><slot /></main>
        <footer class="mt-12 border-t border-slate-200 p-6 text-center text-sm text-slate-500">
            Ordering powered by <Link href="/" class="font-semibold text-brand">ZionFood</Link>
        </footer>
    </div>
</template>
