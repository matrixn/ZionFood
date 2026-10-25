<script setup lang="ts">
import DataTable from 'primevue/datatable';
defineProps<{ rows: object[]; loading?: boolean }>();
</script>
<template>
    <DataTable
        :value="rows"
        :loading="loading"
        striped-rows
        scrollable
        paginator
        :rows="20"
        class="overflow-hidden rounded-xl"
        ><slot /><template #empty><p class="muted p-6">No records to display.</p></template></DataTable
    >
</template>
