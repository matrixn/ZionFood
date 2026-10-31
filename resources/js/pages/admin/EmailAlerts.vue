<script setup lang="ts">
import { ref } from 'vue';
import { Head } from '@inertiajs/vue3';
import AdminLayout from '@/layouts/AdminLayout.vue';
import Button from 'primevue/button';
import ToggleSwitch from 'primevue/toggleswitch';
import { api } from '@/lib/http';
interface Settings {
    enabled: boolean;
    environment_enabled: boolean;
    suspended_at: string | null;
    reason: string | null;
    version: number;
    limit: number;
    admitted: number;
    delivery_attempts: number;
    accepted: number;
}
const props = defineProps<{ settings: Settings }>();
const state = ref({ ...props.settings });
const enabled = ref(state.value.enabled);
const busy = ref(false);
const error = ref('');
const saved = ref(false);
async function save(resume = false) {
    busy.value = true;
    error.value = '';
    saved.value = false;
    try {
        const result = await api<{ settings: Settings }>('/admin/email-alerts', 'PUT', {
            enabled: resume ? true : enabled.value,
            resume,
            version: state.value.version,
        });
        state.value = result.settings;
        enabled.value = result.settings.enabled;
        saved.value = true;
    } catch (e) {
        error.value = (e as Error).message;
    } finally {
        busy.value = false;
    }
}
</script>
<template>
    <AdminLayout
        ><Head :title="$t('pa_alerts')" />
        <div class="mx-auto max-w-3xl space-y-6">
            <h1 class="text-3xl font-semibold">{{ $t('pa_alerts') }}</h1>
            <p class="muted">{{ $t('alerts_explanation') }}</p>
            <div class="panel space-y-4">
                <p v-if="!state.environment_enabled" class="rounded-xl bg-amber-50 p-4 text-amber-900">
                    {{ $t('alerts_environment_off') }}
                </p>
                <div
                    role="status"
                    :class="state.suspended_at ? 'bg-rose-50 text-rose-800' : 'bg-emerald-50 text-emerald-800'"
                    class="rounded-xl p-4"
                >
                    <strong>{{
                        $t(
                            state.suspended_at
                                ? 'alerts_suspended'
                                : state.enabled
                                  ? 'alerts_active'
                                  : 'alerts_disabled',
                        )
                    }}</strong>
                    <p v-if="state.suspended_at" class="mt-2 text-sm">
                        {{ $t('alerts_suspended_help') }} · {{ state.suspended_at }} UTC
                    </p>
                </div>
                <div class="grid grid-cols-3 gap-3 text-center text-sm">
                    <div>
                        <strong class="block text-2xl">{{ state.admitted }}/5</strong>{{ $t('alerts_admitted') }}
                    </div>
                    <div>
                        <strong class="block text-2xl">{{ state.delivery_attempts }}/5</strong
                        >{{ $t('alerts_attempts') }}
                    </div>
                    <div>
                        <strong class="block text-2xl">{{ state.accepted }}</strong
                        >{{ $t('alerts_accepted') }}
                    </div>
                </div>
                <label class="flex items-center gap-3"
                    ><ToggleSwitch v-model="enabled" :aria-label="$t('alerts_enabled')" /><span>{{
                        $t('alerts_enabled')
                    }}</span
                    ><FieldHelp :label="$t('alerts_enabled')" :text="$t('alerts_explanation')"
                /></label>
                <p v-if="error" role="alert" class="text-red-700">{{ error }}</p>
                <p v-if="saved" role="status" class="text-emerald-700">{{ $t('alerts_saved') }}</p>
                <div class="flex flex-wrap gap-3">
                    <Button :label="$t('save')" :loading="busy" :disabled="busy" @click="save()" />
                    <Button
                        v-if="state.suspended_at"
                        :label="$t('alerts_resume')"
                        icon="pi pi-refresh"
                        :loading="busy"
                        :disabled="busy || !state.environment_enabled"
                        @click="save(true)"
                    />
                </div>
                <p class="muted text-xs">{{ $t('alerts_scope') }}</p>
            </div>
        </div>
    </AdminLayout>
</template>
