<script setup lang="ts">
withDefaults(defineProps<{ label: string; error?: string; for?: string; help?: string; helpTabbable?: boolean }>(), {
    error: undefined,
    for: undefined,
    help: undefined,
    helpTabbable: false,
});
</script>
<template>
    <div class="field">
        <span class="field-label"
            ><label :for="$props.for">{{ label }}</label
            ><FieldHelp
                :label="label"
                :text="help"
                :tabbable="helpTabbable"
                :description-id="$props.for ? $props.for + '-help' : undefined" /></span
        ><slot /><small v-if="error" role="alert" class="text-red-600">{{ error }}</small>
    </div>
</template>
