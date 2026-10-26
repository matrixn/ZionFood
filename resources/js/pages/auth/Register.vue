<script setup lang="ts">
import AuthForm from '@/components/AuthForm.vue';
defineProps<{ status?: string; invitedEmail?: string | null }>();
</script>
<template>
    <AuthForm mode="register" :status="status" :email="invitedEmail ?? undefined" :invited="!!invitedEmail" />
</template>
