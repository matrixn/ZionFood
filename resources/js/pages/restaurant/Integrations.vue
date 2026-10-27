<script setup lang="ts">
import { useLocationDates } from '@/lib/locationDates';
const dates = useLocationDates();
import { onMounted, ref } from 'vue';
import { Head } from '@inertiajs/vue3';
import RestaurantLayout from '@/layouts/RestaurantLayout.vue';
import ZFPageHeader from '@/components/ui/ZFPageHeader.vue';
import InputText from 'primevue/inputtext';
import MultiSelect from 'primevue/multiselect';
import Button from 'primevue/button';
import { api } from '@/lib/http';
import type { WorkspaceProps } from '@/types';
const props = defineProps<WorkspaceProps>();
const tokens = ref<{ id: number; name: string; abilities: string[]; expires_at: string }[]>([]);
const name = ref('');
const abilities = ref(['menu:read']);
const issued = ref('');
const error = ref('');
const busy = ref(false);
const endpoint = `/api/v1/locations/${props.location.public_id}/integration-tokens`;
async function load() {
    try {
        tokens.value = (await api<{ tokens: typeof tokens.value }>(endpoint)).tokens;
    } catch (e) {
        error.value = (e as Error).message;
    }
}
async function create() {
    busy.value = true;
    issued.value = '';
    error.value = '';
    try {
        issued.value = (
            await api<{ token: string }>(endpoint, 'POST', { name: name.value, abilities: abilities.value })
        ).token;
        name.value = '';
        await load();
    } catch (e) {
        error.value = (e as Error).message;
    } finally {
        busy.value = false;
    }
}
async function revoke(id: number) {
    try {
        await api(endpoint + '/' + id, 'DELETE');
        await load();
    } catch (e) {
        error.value = (e as Error).message;
    }
}
onMounted(load);
</script>
<template>
    <RestaurantLayout v-bind="$props"
        ><Head :title="$t('integrations')" /><ZFPageHeader
            :title="$t('connect_your_tools')"
            :description="$t('organization_scoped_api_access_for_wordpress_and_other_integrations')"
        />
        <p v-if="error" role="alert" class="my-5 text-red-600">{{ error }}</p>
        <section v-if="issued" class="panel mb-6">
            <strong>{{ $t('copy_this_token_now_it_is_only_shown_once') }}</strong>
            <p class="muted my-3">{{ $t('store_it_securely_in_your_integrations_backend_expires_in_90_days') }}</p>
            <code class="block break-all rounded-xl bg-slate-100 p-4 text-sm dark:bg-slate-800">{{ issued }}</code
            ><Button :label="$t('dismiss_token')" text class="mt-3" @click="issued = ''" />
        </section>
        <form class="panel mb-6 grid max-w-xl gap-5" @submit.prevent="create">
            <label class="field"
                ><span class="field-label"
                    >{{ $t('integration_name')
                    }}<FieldHelp :label="$t('integration_name')" field="integration_name" /></span
                ><InputText v-model="name" :aria-label="$t('integration_name')" required maxlength="100" /></label
            ><label class="field"
                ><span class="field-label"
                    >{{ $t('permissions') }}<FieldHelp :label="$t('permissions')" field="permissions" /></span
                ><MultiSelect
                    v-model="abilities"
                    :aria-label="$t('permissions')"
                    :options="['menu:read', 'menu:write', 'orders:read', 'orders:manage']" /></label
            ><Button :label="$t('create_token')" type="submit" :loading="busy" />
        </form>
        <section class="panel">
            <h2 class="mb-4 text-lg font-semibold">{{ $t('your_integration_tokens') }}</h2>
            <div
                v-for="token in tokens"
                :key="token.id"
                class="flex flex-wrap items-center justify-between gap-4 border-t border-slate-200 py-4"
            >
                <div>
                    <strong>{{ token.name }}</strong>
                    <p class="muted mt-2">{{ token.abilities.join(', ') }}</p>
                    <p class="muted">
                        {{ $t('expires') }}
                        {{ dates.date(token.expires_at) }}
                    </p>
                </div>
                <Button :label="$t('revoke')" severity="danger" outlined @click="revoke(token.id)" />
            </div>
            <p v-if="!tokens.length" class="muted">{{ $t('no_integration_tokens_yet') }}</p>
        </section>
        <p class="muted mt-6">
            {{ $t('api_docs_wordpress') }}
        </p></RestaurantLayout
    >
</template>
