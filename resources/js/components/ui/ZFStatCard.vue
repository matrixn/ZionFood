<script setup lang="ts">
defineProps<{ label: string; value: string | number; icon: string }>();
</script>
<template>
    <div class="panel flex items-center justify-between">
        <div>
            <p class="muted mb-2">{{ label }}</p>
            <strong class="text-3xl">{{ value }}</strong>
        </div>
        <i :class="['pi text-2xl text-brand', icon]" />
    </div>
</template>
