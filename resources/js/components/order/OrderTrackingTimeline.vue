<script setup lang="ts">
import { useLocationDates } from '@/lib/locationDates';
import type { DateContext } from '@/lib/locationDates';
const dates = useLocationDates(() => props.location);
const props = defineProps<{ history: { to_status: string; created_at: string }[]; location?: DateContext }>();
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
                    {{ dates.dateTime(entry.created_at) }}
                </p>
            </div>
        </li>
    </ol>
</template>
