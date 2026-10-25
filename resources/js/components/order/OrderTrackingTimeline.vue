<script setup lang="ts">
defineProps<{ history: { to_status: string; created_at: string }[] }>();
</script>
<template>
    <ol class="grid gap-5">
        <li v-for="(entry, i) in history" :key="i" class="flex items-center gap-4">
            <span class="flex h-9 w-9 items-center justify-center rounded-full bg-emerald-50 text-success"
                ><i aria-hidden="true" class="pi pi-check"
            /></span>
            <div>
                <strong class="capitalize">{{ $t('status_' + entry.to_status) }}</strong>
                <p class="muted">
                    {{ new Date(entry.created_at).toLocaleString($i18n.locale === 'ro' ? 'ro-RO' : 'en-GB') }}
                </p>
            </div>
        </li>
    </ol>
</template>
