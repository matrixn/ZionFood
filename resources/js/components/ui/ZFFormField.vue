<script setup lang="ts">
import { fieldHelp } from '@/lib/fieldHelp';

withDefaults(defineProps<{ label: string; error?: string; for?: string; help?: string; helpTabbable?: boolean }>(), {
    error: undefined,
    for: undefined,
    help: undefined,
    helpTabbable: true,
});
</script>
<template>
    <div class="field">
        <span class="field-label"
            ><label :for="$props.for">{{ label }}</label
            ><FieldHelp :label="label" :text="help" :tabbable="helpTabbable" /></span
        ><span v-if="helpTabbable === false && $props.for" :id="$props.for + '-help'" class="sr-only">{{
            help || fieldHelp('', label)
        }}</span
        ><slot /><small v-if="error" role="alert" class="text-red-600">{{ error }}</small>
    </div>
</template>
