<script setup lang="ts">
import Drawer from 'primevue/drawer';
defineProps<{ title: string }>();
const visible = defineModel<boolean>('visible', { required: true });
</script>
<template>
    <Drawer v-model:visible="visible" :header="title" position="right" class="!w-full sm:!w-[34rem]"
        ><slot /><template v-if="$slots.footer" #footer><slot name="footer" /></template
    ></Drawer>
</template>
