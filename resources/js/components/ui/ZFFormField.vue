<script setup lang="ts">
defineProps<{ label: string; error?: string; for?: string }>();
</script>
<template>
    <div class="field">
        <label :for="$props.for">{{ label }}</label
        ><slot /><small v-if="error" role="alert" class="text-red-600">{{ error }}</small>
    </div>
</template>
