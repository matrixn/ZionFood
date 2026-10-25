<script setup lang="ts">
import Column from 'primevue/column';
import Button from 'primevue/button';
import ZFDataTable from '@/components/ui/ZFDataTable.vue';
import ZFStatusBadge from '@/components/ui/ZFStatusBadge.vue';
import { money } from '@/lib/http';
import type { Order } from '@/types';
defineProps<{ orders: Order[]; loading: boolean }>();
const emit = defineEmits<{ select: [order: Order] }>();
</script>
<template>
    <ZFDataTable :rows="orders" :loading="loading"
        ><Column header="Order"
            ><template #body="{ data }"
                ><strong>#{{ (data as Order).public_id.slice(-8) }}</strong></template
            ></Column
        ><Column field="customer_name" header="Customer" /><Column header="Status"
            ><template #body="{ data }"><ZFStatusBadge :status="(data as Order).status" /></template></Column
        ><Column field="fulfillment" header="Method" /><Column header="Total"
            ><template #body="{ data }">{{ money((data as Order).total) }}</template></Column
        ><Column header="Received"
            ><template #body="{ data }">{{ new Date((data as Order).created_at).toLocaleString() }}</template></Column
        ><Column header="Details"
            ><template #body="{ data }"
                ><Button
                    icon="pi pi-arrow-right"
                    text
                    aria-label="View order details"
                    @click="emit('select', data)" /></template></Column
    ></ZFDataTable>
</template>
