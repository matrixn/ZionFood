<script setup lang="ts">
import Tag from 'primevue/tag';
import type { OrderStatus } from '@/types';
defineProps<{ status: OrderStatus }>();
const severity: Record<OrderStatus, 'warn' | 'info' | 'success' | 'danger' | 'secondary'> = {
    pending: 'warn',
    accepted: 'info',
    preparing: 'info',
    ready: 'success',
    out_for_delivery: 'info',
    completed: 'success',
    rejected: 'danger',
    cancelled: 'secondary',
};
</script>
<template><Tag :value="$t('status_' + status)" :severity="severity[status]" class="capitalize" /></template>
