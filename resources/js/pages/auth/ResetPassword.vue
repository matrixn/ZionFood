<script setup lang="ts">
import AuthForm from '@/components/AuthForm.vue';
defineProps<{ token: string; email: string }>();
</script>
<template><AuthForm mode="reset" :token="token" :email="email" /></template>
