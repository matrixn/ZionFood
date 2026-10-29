<script setup lang="ts">
import { useLocationDates } from '@/lib/locationDates';
const dates = useLocationDates();
import Column from 'primevue/column';
import Button from 'primevue/button';
import ZFDataTable from '@/components/ui/ZFDataTable.vue';
import ZFStatusBadge from '@/components/ui/ZFStatusBadge.vue';
import { money } from '@/lib/http';
import type { Order } from '@/types';
import { orderRejectionReason } from '@/lib/orderRejection';
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
            ><template #body="{ data }"
                ><span class="inline-flex items-center gap-2"
                    ><ZFStatusBadge :status="(data as Order).status" /><FieldHelp
                        v-if="orderRejectionReason(data as Order)"
                        :label="$t('reason_for_rejection')"
                        :text="orderRejectionReason(data as Order)!" /></span></template></Column
        ><Column :header="$t('method')"
            ><template #body="{ data }">{{ $t('fulfillment_' + (data as Order).fulfillment) }}</template></Column
        ><Column :header="$t('total')"
            ><template #body="{ data }">{{ money((data as Order).total) }}</template></Column
        ><Column :header="$t('received')"
            ><template #body="{ data }">{{ dates.dateTime((data as Order).created_at) }}</template></Column
        ><Column :header="$t('details')"
            ><template #body="{ data }"
                ><Button
                    icon="pi pi-arrow-right"
                    text
                    :aria-label="$t('view_order_details')"
                    @click="emit('select', data)" /></template></Column
    ></ZFDataTable>
</template>
