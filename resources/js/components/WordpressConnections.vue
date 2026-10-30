<script setup lang="ts">
import { onMounted, ref } from 'vue';
import Button from 'primevue/button';
import InputText from 'primevue/inputtext';
import MultiSelect from 'primevue/multiselect';
import Select from 'primevue/select';
import { api } from '@/lib/http';
import type { WorkspaceProps } from '@/types';
const props = defineProps<WorkspaceProps>();
interface Connection {
    public_id: string;
    name: string;
    site_url: string;
    active: boolean;
    abilities: string[];
    can_manage: boolean;
}
const connections = ref<Connection[]>([]);
const options = ref<string[]>([]);
const locations = ref<{ id: number; name: string }[]>([]);
const form = ref({
    name: 'WordPress',
    site_url: '',
    location_ids: [props.location.id],
    abilities: ['storefront:read', 'checkout:write', 'reservations:create'],
    expires_in_days: 90,
});
const issued = ref<{ token: string; webhook_secret: string; api_url: string } | null>(null);
const error = ref('');
const busy = ref(false);
const endpoint = `/app/locations/${props.location.public_id}/wordpress-connections`;
async function load() {
    const data = await api<{ connections: Connection[]; abilities: string[]; locations: typeof locations.value }>(
        endpoint,
    );
    connections.value = data.connections;
    options.value = data.abilities;
    locations.value = data.locations;
}
async function run(operation: () => Promise<void>) {
    busy.value = true;
    error.value = '';
    try {
        await operation();
        await load();
    } catch (e) {
        error.value = (e as Error).message;
    } finally {
        busy.value = false;
    }
}
async function create() {
    await run(async () => {
        issued.value = await api(endpoint, 'POST', form.value);
    });
}
async function rotate(id: string) {
    await run(async () => {
        issued.value = await api(`${endpoint}/${id}/rotate`, 'POST', {});
    });
}
async function revoke(id: string) {
    await run(async () => {
        await api(`${endpoint}/${id}`, 'DELETE');
        issued.value = null;
    });
}
onMounted(() => run(async () => {}));
</script>
<template>
    <section class="panel my-6 space-y-5">
        <div>
            <h2 class="text-xl font-semibold">{{ $t('wp_title') }}</h2>
            <p class="muted mt-2">{{ $t('wp_intro') }}</p>
        </div>
        <p v-if="error" role="alert" class="text-red-600">{{ error }}</p>
        <section v-if="issued" class="space-y-3 rounded-xl border border-emerald-300 bg-emerald-50 p-4 text-slate-900">
            <strong>{{ $t('wp_once') }}</strong>
            <p class="text-sm">{{ $t('wp_private') }}</p>
            <p class="break-all text-sm">API: {{ issued.api_url }}</p>
            <p>{{ $t('wp_token') }}</p>
            <code data-testid="wp-token" class="block break-all text-sm select-all">{{ issued.token }}</code>
            <p>{{ $t('wp_secret') }}</p>
            <code class="block break-all text-sm select-all">{{ issued.webhook_secret }}</code>
            <Button :label="$t('dismiss_token')" outlined @click="issued = null" />
        </section>
        <form class="grid gap-4 md:grid-cols-2" @submit.prevent="create">
            <label class="field"
                ><span class="field-label"
                    >{{ $t('integration_name')
                    }}<FieldHelp :label="$t('integration_name')" :text="$t('wp_name_help')" /></span
                ><InputText v-model="form.name" :aria-label="$t('integration_name')" required maxlength="100"
            /></label>
            <label class="field"
                ><span class="field-label"
                    >{{ $t('wp_site') }}<FieldHelp :label="$t('wp_site')" :text="$t('wp_site_help')" /></span
                ><InputText
                    v-model="form.site_url"
                    :aria-label="$t('wp_site')"
                    type="url"
                    required
                    placeholder="https://restaurant.ro"
            /></label>
            <label class="field"
                ><span class="field-label"
                    >{{ $t('locations') }}<FieldHelp :label="$t('locations')" :text="$t('wp_locations_help')" /></span
                ><MultiSelect
                    v-model="form.location_ids"
                    :aria-label="$t('locations')"
                    :options="locations"
                    option-label="name"
                    option-value="id"
            /></label>
            <label class="field"
                ><span class="field-label"
                    >{{ $t('permissions') }}<FieldHelp :label="$t('permissions')" :text="$t('wp_scopes_help')" /></span
                ><MultiSelect v-model="form.abilities" :aria-label="$t('permissions')" :options="options"
            /></label>
            <label class="field"
                ><span class="field-label"
                    >{{ $t('wp_expiry') }}<FieldHelp :label="$t('wp_expiry')" :text="$t('wp_expiry_help')" /></span
                ><Select v-model="form.expires_in_days" :aria-label="$t('wp_expiry')" :options="[30, 90, 180, 365]"
            /></label>
            <Button
                type="submit"
                class="self-end"
                :label="$t('wp_generate')"
                :loading="busy"
                :disabled="busy || !form.abilities.length || !form.location_ids.length"
            />
        </form>
        <div
            v-for="connection in connections"
            :key="connection.public_id"
            data-testid="wp-connection"
            class="flex flex-wrap items-center justify-between gap-4 border-t border-slate-200 py-4"
        >
            <div>
                <strong>{{ connection.name }}</strong>
                <p class="muted">{{ connection.site_url }}</p>
                <p class="muted text-xs">{{ connection.abilities.join(', ') }}</p>
                <p class="text-sm">{{ $t(connection.active ? 'wp_active' : 'wp_revoked') }}</p>
            </div>
            <div v-if="connection.active && connection.can_manage" class="flex gap-2">
                <Button
                    :label="$t('wp_rotate')"
                    outlined
                    :disabled="busy"
                    @click="rotate(connection.public_id)"
                /><Button
                    :label="$t('revoke')"
                    severity="danger"
                    outlined
                    :disabled="busy"
                    @click="revoke(connection.public_id)"
                />
            </div>
        </div>
        <p class="muted text-sm">{{ $t('wp_rotation_help') }}</p>
    </section>
</template>
