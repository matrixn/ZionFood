<script setup lang="ts">
import { ref, watch } from 'vue';
const props = defineProps<{ path?: string | null; name: string }>();
const failed = ref(false);
watch(
    () => props.path,
    () => {
        failed.value = false;
    },
);
</script>
<template>
    <div
        class="relative h-full w-full overflow-hidden bg-gradient-to-br from-violet-50 via-orange-50 to-amber-100 dark:from-slate-800 dark:to-slate-700"
    >
        <img
            v-if="path && !failed"
            :src="'/storage/' + path"
            :alt="name"
            class="h-full w-full object-cover transition-transform duration-500 motion-safe:group-hover:scale-105"
            @error="failed = true"
        />
        <div v-else class="flex h-full w-full items-center justify-center text-brand/40">
            <i class="pi pi-image text-3xl" aria-hidden="true" />
        </div>
    </div>
</template>
