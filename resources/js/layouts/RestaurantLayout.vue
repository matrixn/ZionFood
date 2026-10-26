<script setup lang="ts">
import ZFShell from '@/components/ui/ZFShell.vue';
import type { WorkspaceProps } from '@/types';
defineProps<WorkspaceProps>();
</script>
<template>
    <div v-if="embedded" class="embedded-editor min-w-0"><slot /></div>
    <ZFShell v-else v-bind="$props"><slot /></ZFShell>
</template>

<style scoped>
.embedded-editor :deep(.field),
.embedded-editor :deep(.panel),
.embedded-editor :deep(form),
.embedded-editor :deep(.grid) {
    min-width: 0;
}
.embedded-editor :deep(.p-inputtext),
.embedded-editor :deep(.p-textarea),
.embedded-editor :deep(.p-select),
.embedded-editor :deep(.p-multiselect),
.embedded-editor :deep(.p-inputnumber),
.embedded-editor :deep(input[type='file']) {
    width: 100%;
    max-width: 100%;
    min-width: 0;
}
</style>
