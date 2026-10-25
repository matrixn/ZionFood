<script setup lang="ts">
defineProps<{ history: { to_status: string; created_at: string }[] }>();
</script>
<template>
    <ol class="grid gap-5">
        <li v-for="(entry, i) in history" :key="i" class="flex items-center gap-4">
            <span class="flex h-9 w-9 items-center justify-center rounded-full bg-emerald-50 text-success"
                ><i class="pi pi-check"
            /></span>
            <div>
                <strong class="capitalize">{{ entry.to_status.replaceAll('_', ' ') }}</strong>
                <p class="muted">{{ new Date(entry.created_at).toLocaleString() }}</p>
            </div>
        </li>
    </ol>
</template>
