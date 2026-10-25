<script setup lang="ts">
import ZFShell from '@/components/ui/ZFShell.vue';
import type { WorkspaceProps } from '@/types';
defineProps<WorkspaceProps>();
</script>
<template>
    <ZFShell v-bind="$props"><slot /></ZFShell>
</template>
