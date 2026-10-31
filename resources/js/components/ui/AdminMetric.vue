<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
defineProps<{ label: string; value: string | number; help: string; icon: string; href?: string; detail?: string }>();
</script>
<template>
    <article class="admin-metric">
        <div class="flex items-center justify-between gap-2">
            <span class="admin-metric-icon"><i :class="['pi', icon]" aria-hidden="true" /></span
            ><FieldHelp :label="label" :text="help" />
        </div>
        <div class="mt-4 text-3xl font-semibold tracking-tight tabular-nums">{{ value }}</div>
        <Link v-if="href" :href="href" class="mt-1 block text-sm font-medium hover:text-brand"
            >{{ label }} <i class="pi pi-arrow-up-right ml-1 text-xs" aria-hidden="true"
        /></Link>
        <p v-else class="mt-1 text-sm font-medium">{{ label }}</p>
        <p v-if="detail" class="muted mt-2 text-xs">{{ detail }}</p>
    </article>
</template>
