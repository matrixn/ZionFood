<script setup lang="ts">
import { useId, watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import Dialog from 'primevue/dialog';
import InputText from 'primevue/inputtext';
import InputNumber from 'primevue/inputnumber';
import Button from 'primevue/button';
import type { ModifierGroup } from '@/types';
const modifierFormId = useId();
const props = defineProps<{ locationId: string; group: ModifierGroup | null }>();
const visible = defineModel<boolean>('visible', { required: true });
const form = useForm({
    id: null as number | null,
    name: '',
    min_choices: 0,
    max_choices: 1,
    options: [{ name: '', price: 0 }],
});
watch(visible, (open) => {
    if (open) {
        form.id = props.group?.id ?? null;
        form.name = props.group?.name ?? '';
        form.min_choices = props.group?.min_choices ?? 0;
        form.max_choices = props.group?.max_choices ?? 1;
        form.options = props.group?.options.map((o) => ({ name: o.name, price: o.price })) ?? [{ name: '', price: 0 }];
        form.clearErrors();
    }
});
</script>
<template>
    <Dialog v-model:visible="visible" :header="$t('modifier_group')" modal class="w-full max-w-xl"
        ><form
            :id="modifierFormId"
            class="grid gap-5"
            @submit.prevent="
                form.post('/app/locations/' + locationId + '/modifiers', { onSuccess: () => (visible = false) })
            "
        >
            <p v-for="(error, key) in form.errors" :key="key" role="alert" class="text-red-600">{{ error }}</p>
            <label class="field"
                ><span class="field-label"
                    >{{ $t('group_name') }}<FieldHelp :label="$t('group_name')" field="group_name" /></span
                ><InputText v-model="form.name" :aria-label="$t('group_name')" required
            /></label>
            <div class="grid grid-cols-2 gap-3">
                <label class="field"
                    ><span class="field-label"
                        >{{ $t('minimum_choices')
                        }}<FieldHelp :label="$t('minimum_choices')" field="minimum_choices" /></span
                    ><InputNumber
                        v-model="form.min_choices"
                        :aria-label="$t('minimum_choices')"
                        :min="0"
                        :max="20" /></label
                ><label class="field"
                    ><span class="field-label"
                        >{{ $t('maximum_choices')
                        }}<FieldHelp :label="$t('maximum_choices')" field="maximum_choices" /></span
                    ><InputNumber v-model="form.max_choices" :aria-label="$t('maximum_choices')" :min="0" :max="20"
                /></label>
            </div>
            <div v-for="(option, i) in form.options" :key="i" class="flex min-w-0 gap-2">
                <span class="field-control-help"
                    ><InputText
                        v-model="option.name"
                        class="min-w-0 flex-1"
                        :placeholder="$t('option_name')"
                        :aria-label="$t('option_name')"
                        required /><FieldHelp :label="$t('option_name')" field="name" kind="InputText" /></span
                ><span class="field-control-help"
                    ><InputNumber
                        v-model="option.price"
                        class="!w-24 shrink-0"
                        input-class="w-full"
                        :min="0"
                        :aria-label="$t('option_price_in_bani')" /><FieldHelp
                        :label="$t('option_price_in_bani')"
                        field="price"
                        kind="InputNumber" /></span
                ><Button
                    class="shrink-0"
                    icon="pi pi-times"
                    text
                    :aria-label="$t('remove_option')"
                    @click="form.options.splice(i, 1)"
                />
            </div>
            <Button
                :label="$t('add_option')"
                text
                icon="pi pi-plus"
                @click="form.options.push({ name: '', price: 0 })"
            />
        </form>
        <template #footer
            ><Button
                :form="modifierFormId"
                :disabled="
                    !form.name.trim() ||
                    !form.options.length ||
                    form.options.some((o) => !o.name.trim()) ||
                    form.processing
                "
                :label="$t('save_group')"
                type="submit"
                :loading="form.processing"
        /></template>
    </Dialog>
</template>
