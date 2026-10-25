<script setup lang="ts">
defineProps<{ title: string; description?: string }>();
</script>
<template>
    <div class="panel py-16 text-center">
        <i class="pi pi-inbox mb-4 text-4xl text-brand" />
        <h3 class="text-lg font-semibold">{{ title }}</h3>
        <p class="muted mt-2">{{ description }}</p>
        <div class="mt-5"><slot /></div>
    </div>
</template>
