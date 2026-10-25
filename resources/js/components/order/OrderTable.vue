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
        ><Column :header="$t('order')"
            ><template #body="{ data }"
                ><strong>#{{ (data as Order).public_id.slice(-8) }}</strong></template
            ></Column
        ><Column field="customer_name" :header="$t('customer')" /><Column :header="$t('status')"
            ><template #body="{ data }"><ZFStatusBadge :status="(data as Order).status" /></template></Column
        ><Column :header="$t('method')"
            ><template #body="{ data }">{{ $t('fulfillment_' + (data as Order).fulfillment) }}</template></Column
        ><Column :header="$t('total')"
            ><template #body="{ data }">{{ money((data as Order).total) }}</template></Column
        ><Column :header="$t('received')"
            ><template #body="{ data }">{{
                new Date((data as Order).created_at).toLocaleString($i18n.locale === 'ro' ? 'ro-RO' : 'en-GB')
            }}</template></Column
        ><Column :header="$t('details')"
            ><template #body="{ data }"
                ><Button
                    icon="pi pi-arrow-right"
                    text
                    :aria-label="$t('view_order_details')"
                    @click="emit('select', data)" /></template></Column
    ></ZFDataTable>
</template>
