<script setup lang="ts">
import { ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { useToast } from 'primevue/usetoast';
import Button from 'primevue/button';
import Select from 'primevue/select';
import Dialog from 'primevue/dialog';
import AdminLayout from '@/layouts/AdminLayout.vue';
import ZFPageHeader from '@/components/ui/ZFPageHeader.vue';
import ZFFormField from '@/components/ui/ZFFormField.vue';
import { api } from '@/lib/http';
import { localeTag, tr as t } from '@/i18n';
interface Lead {
    public_id: string;
    name: string;
    email: string;
    phone: string;
    status: string;
    locale: string;
    created_at: string;
}
const props = defineProps<{
    leads: { data: Lead[]; current_page: number; last_page: number; total: number };
    filters: { status?: string };
    summary: Record<string, number>;
}>();
const states = ['new', 'contacted', 'qualified', 'closed'];
const busy = ref('');
const deleting = ref<Lead | null>(null);
const toast = useToast();
const options = () => states.map((value) => ({ value, label: t('leads_status_' + value) }));
function filter(status: string | null) {
    router.get('/admin/leads', status ? { status } : {}, { preserveState: true, preserveScroll: true });
}
function pageUrl(page: number) {
    return `/admin/leads?page=${page}${props.filters.status ? `&status=${props.filters.status}` : ''}`;
}
async function update(lead: Lead, status: string) {
    if (busy.value) return;
    busy.value = lead.public_id;
    try {
        await api(`/admin/leads/${lead.public_id}`, 'PATCH', { status });
        toast.add({ severity: 'success', summary: t('leads_saved'), life: 4000 });
        router.reload({ only: ['leads', 'summary'] });
    } catch (error) {
        toast.add({ severity: 'error', summary: t('request_failed'), detail: (error as Error).message, life: 6000 });
    } finally {
        busy.value = '';
    }
}
async function remove() {
    if (!deleting.value || busy.value) return;
    busy.value = deleting.value.public_id;
    try {
        await api(`/admin/leads/${deleting.value.public_id}`, 'DELETE');
        deleting.value = null;
        toast.add({ severity: 'success', summary: t('leads_deleted'), life: 4000 });
        router.reload({ only: ['leads', 'summary'] });
    } catch (error) {
        toast.add({ severity: 'error', summary: t('request_failed'), detail: (error as Error).message, life: 6000 });
    } finally {
        busy.value = '';
    }
}
function date(value: string) {
    return new Intl.DateTimeFormat(localeTag(), {
        dateStyle: 'medium',
        timeStyle: 'short',
        timeZone: 'Europe/Bucharest',
    }).format(new Date(value));
}
</script>
<template>
    <AdminLayout
        ><Head :title="$t('leads_title')" /><ZFPageHeader
            :title="$t('leads_title')"
            :description="$t('leads_description')"
        />
        <div class="mb-6 grid grid-cols-2 gap-3 lg:grid-cols-4">
            <div v-for="state in states" :key="state" class="panel !p-4">
                <p class="text-xs font-medium text-slate-500">{{ $t('leads_status_' + state) }}</p>
                <strong class="mt-2 block text-3xl">{{ summary[state] ?? 0 }}</strong>
            </div>
        </div>
        <div class="panel mb-6 flex flex-wrap items-end justify-between gap-4">
            <ZFFormField :label="$t('leads_filter')" :help="$t('leads_filter_help')" for="lead-status-filter"
                ><Select
                    input-id="lead-status-filter"
                    :model-value="filters.status ?? null"
                    :options="[{ value: null, label: $t('leads_all') }, ...options()]"
                    option-value="value"
                    option-label="label"
                    :aria-label="$t('leads_filter')"
                    @update:model-value="filter"
            /></ZFFormField>
            <p class="text-sm text-slate-500">
                {{ $t(leads.total === 1 ? 'leads_total_single' : 'leads_total', { count: leads.total }) }}
            </p>
        </div>
        <p v-if="!leads.data.length" class="panel text-center text-slate-500">{{ $t('leads_empty') }}</p>
        <div class="grid gap-4 xl:grid-cols-2">
            <article
                v-for="lead in leads.data"
                :key="lead.public_id"
                class="panel min-w-0"
                :data-lead-id="lead.public_id"
            >
                <div class="flex items-start justify-between gap-4">
                    <div class="min-w-0">
                        <h2 class="break-words text-lg font-semibold">{{ lead.name }}</h2>
                        <p class="mt-1 text-xs text-slate-500">
                            {{ date(lead.created_at) }} · {{ lead.locale === 'ro' ? '🇷🇴 Română' : '🇬🇧 English' }}
                        </p>
                    </div>
                    <span
                        class="rounded-full px-3 py-1 text-xs font-semibold"
                        :class="lead.status === 'new' ? 'bg-violet-100 text-violet-800' : 'bg-slate-100 text-slate-600'"
                        >{{ $t('leads_status_' + lead.status) }}</span
                    >
                </div>
                <div class="my-5 grid gap-2 text-sm">
                    <a :href="'mailto:' + encodeURIComponent(lead.email)" class="break-all text-brand"
                        ><i class="pi pi-envelope mr-2" aria-hidden="true" />{{ lead.email }}</a
                    ><a :href="'tel:' + lead.phone.replace(/[^0-9+]/g, '')" class="text-slate-600 dark:text-slate-300"
                        ><i class="pi pi-phone mr-2" aria-hidden="true" />{{ lead.phone }}</a
                    >
                </div>
                <div class="flex items-end justify-between gap-3 border-t border-slate-100 pt-4 dark:border-slate-700">
                    <ZFFormField :label="$t('leads_status')" :help="$t('leads_status_help')"
                        ><Select
                            :model-value="lead.status"
                            :options="options()"
                            option-label="label"
                            option-value="value"
                            :disabled="!!busy"
                            :aria-label="$t('leads_status_for', { name: lead.name })"
                            @update:model-value="update(lead, $event)" /></ZFFormField
                    ><span class="field-control-help"
                        ><Button
                            icon="pi pi-trash"
                            severity="danger"
                            text
                            :aria-label="$t('leads_delete_for', { name: lead.name })"
                            :disabled="!!busy"
                            @click="deleting = lead" /><FieldHelp
                            :label="$t('leads_delete')"
                            :text="$t('leads_delete_help')"
                    /></span>
                </div>
            </article>
        </div>
        <div v-if="leads.last_page > 1" class="mt-6 flex items-center justify-center gap-4">
            <Link v-if="leads.current_page > 1" :href="pageUrl(leads.current_page - 1)" class="text-brand">{{
                $t('leads_previous')
            }}</Link
            ><span class="text-sm text-slate-500">{{ leads.current_page }} / {{ leads.last_page }}</span
            ><Link
                v-if="leads.current_page < leads.last_page"
                :href="pageUrl(leads.current_page + 1)"
                class="text-brand"
                >{{ $t('leads_next') }}</Link
            >
        </div>
        <Dialog
            :visible="!!deleting"
            modal
            :header="$t('leads_delete')"
            class="w-full max-w-lg"
            @update:visible="!$event && (deleting = null)"
            ><p>{{ $t('leads_delete_confirm', { name: deleting?.name ?? '' }) }}</p>
            <template #footer
                ><Button :label="$t('cancel')" text :disabled="!!busy" @click="deleting = null" /><Button
                    :label="$t('leads_delete')"
                    severity="danger"
                    :loading="!!busy"
                    @click="remove" /></template
        ></Dialog>
    </AdminLayout>
</template>
