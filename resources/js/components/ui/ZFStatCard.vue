<script setup lang="ts">
defineProps<{ label: string; value: string | number; icon: string }>();
</script>
<template>
    <div class="panel h-full min-w-0 !p-4 md:!p-5">
        <div class="mb-3 flex items-start gap-2 pr-5">
            <i aria-hidden="true" :class="['pi mt-0.5 shrink-0 text-sm text-brand', icon]" />
            <p class="min-w-0 text-xs leading-relaxed text-slate-500">{{ label }}</p>
        </div>
        <strong class="block break-words text-lg font-semibold tabular-nums md:text-2xl">{{
            String(value).replaceAll('\u00a0', ' ')
        }}</strong>
    </div>
</template>
