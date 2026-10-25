<script setup lang="ts">
defineProps<{ title: string; description?: string }>();
</script>
<template>
    <div class="mb-7 flex flex-wrap items-center justify-between gap-4">
        <div>
            <h1 class="m-0 text-3xl font-semibold tracking-tight">{{ title }}</h1>
            <p v-if="description" class="muted mt-2">{{ description }}</p>
        </div>
        <slot />
    </div>
</template>
