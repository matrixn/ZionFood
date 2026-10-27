<script setup lang="ts">
import { ref } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import AdminLayout from '@/layouts/AdminLayout.vue';
import InputText from 'primevue/inputtext';
import InputNumber from 'primevue/inputnumber';
import Select from 'primevue/select';
import ToggleSwitch from 'primevue/toggleswitch';
import Checkbox from 'primevue/checkbox';
import Button from 'primevue/button';
import { api } from '@/lib/http';
const props = defineProps<{
    settings: { enabled: boolean; provider: string; model: string; daily_limit: number; has_key: boolean };
}>();
const form = ref({ ...props.settings, api_key: '', clear_key: false });
const error = ref('');
const saved = ref(false);
const busy = ref(false);
async function save() {
    busy.value = true;
    error.value = '';
    saved.value = false;
    try {
        await api('/admin/menu-ai', 'POST', form.value);
        form.value.has_key = !!form.value.api_key || (!form.value.clear_key && form.value.has_key);
        form.value.api_key = '';
        form.value.clear_key = false;
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
        ><Head :title="$t('mc_ai_settings')" />
        <div class="mx-auto max-w-2xl space-y-6">
            <Link href="/admin" class="text-brand">← {{ $t('platform_overview') }}</Link>
            <h1 class="text-3xl font-semibold">{{ $t('mc_ai_settings') }}</h1>
            <p class="muted">{{ $t('mc_provider_hint') }}</p>
            <form class="panel grid gap-5" @submit.prevent="save">
                <p v-if="error" role="alert" class="text-red-700">{{ error }}</p>
                <p v-if="saved" role="status" class="text-emerald-700">{{ $t('mc_saved') }}</p>
                <label class="flex items-center gap-3"
                    ><ToggleSwitch v-model="form.enabled" :aria-label="$t('mc_enable_ai')" /><span class="field-label"
                        >{{ $t('mc_enable_ai') }}<FieldHelp :label="$t('mc_enable_ai')" field="mc_enable_ai" /></span
                ></label>
                <label class="field"
                    ><span class="field-label"
                        >{{ $t('mc_provider') }}<FieldHelp :label="$t('mc_provider')" field="mc_provider" /></span
                    ><Select
                        v-model="form.provider"
                        :aria-label="$t('mc_provider')"
                        :options="['groq', 'openai']"
                        @change="
                            form.model = form.provider === 'groq' ? 'openai/gpt-oss-20b' : '';
                            form.api_key = '';
                            form.clear_key = true;
                        "
                /></label>
                <label class="field"
                    ><span class="field-label"
                        >{{ $t('mc_model') }}<FieldHelp :label="$t('mc_model')" field="mc_model" /></span
                    ><InputText v-model="form.model" :aria-label="$t('mc_model')" required class="w-full min-w-0"
                /></label>
                <p class="muted">{{ $t('mc_groq_models') }}</p>
                <label class="field"
                    ><span class="field-label"
                        >{{ $t('mc_api_key') }}<FieldHelp :label="$t('mc_api_key')" field="mc_api_key" /></span
                    ><InputText
                        v-model="form.api_key"
                        :aria-label="$t('mc_api_key')"
                        type="password"
                        autocomplete="new-password"
                        class="w-full min-w-0"
                /></label>
                <p class="muted">{{ form.has_key ? $t('mc_key_present') : $t('mc_key_missing') }}</p>
                <label class="flex items-center gap-3"
                    ><Checkbox v-model="form.clear_key" :aria-label="$t('mc_clear_key')" binary /><span
                        class="field-label"
                        >{{ $t('mc_clear_key') }}<FieldHelp :label="$t('mc_clear_key')" field="mc_clear_key" /></span
                ></label>
                <label class="field"
                    ><span class="field-label"
                        >{{ $t('mc_daily_limit')
                        }}<FieldHelp :label="$t('mc_daily_limit')" field="mc_daily_limit" /></span
                    ><InputNumber
                        v-model="form.daily_limit"
                        :aria-label="$t('mc_daily_limit')"
                        class="w-full min-w-0"
                        input-class="w-full min-w-0"
                        :min="1"
                        :max="1000"
                /></label>
                <Button type="submit" :label="$t('save')" :loading="busy" :disabled="busy || !form.model" />
            </form>
            <p class="muted">{{ $t('mc_data_sent') }}</p>
            <a
                href="https://console.groq.com/docs/rate-limits"
                target="_blank"
                rel="noopener noreferrer"
                class="text-brand"
                >{{ $t('mc_provider_limits') }} ↗</a
            >
        </div>
    </AdminLayout>
</template>
