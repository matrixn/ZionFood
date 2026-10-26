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
        class="flex h-12 w-12 shrink-0 items-center justify-center overflow-hidden rounded-lg bg-violet-50 text-brand sm:h-14 sm:w-14"
    >
        <img
            v-if="path && !failed"
            :src="'/storage/' + path"
            :alt="name"
            class="h-full w-full object-cover"
            @error="failed = true"
        />
        <i v-else class="pi pi-image" aria-hidden="true" />
    </div>
</template>
