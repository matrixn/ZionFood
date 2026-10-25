<script setup lang="ts">
import AuthForm from '@/components/AuthForm.vue';
defineProps<{ status?: string }>();
</script>
<template><AuthForm mode="forgot" :status="status" /></template>
