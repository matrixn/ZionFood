<script setup lang="ts">
defineProps<{ label: string; error?: string; for?: string; help?: string }>();
</script>
<template>
    <div class="field">
        <span class="field-label"
            ><label :for="$props.for">{{ label }}</label
            ><FieldHelp :label="label" :text="help" /></span
        ><slot /><small v-if="error" role="alert" class="text-red-600">{{ error }}</small>
    </div>
</template>
