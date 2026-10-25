<script setup lang="ts">
import { watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import Dialog from 'primevue/dialog';
import InputText from 'primevue/inputtext';
import InputNumber from 'primevue/inputnumber';
import Button from 'primevue/button';
import type { ModifierGroup } from '@/types';
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
            class="grid gap-5"
            @submit.prevent="
                form.post('/app/locations/' + locationId + '/modifiers', { onSuccess: () => (visible = false) })
            "
        >
            <p v-for="(error, key) in form.errors" :key="key" role="alert" class="text-red-600">{{ error }}</p>
            <label class="field">{{ $t('group_name') }}<InputText v-model="form.name" required /></label>
            <div class="grid grid-cols-2 gap-3">
                <label class="field"
                    >{{ $t('minimum_choices') }}<InputNumber v-model="form.min_choices" :min="0" :max="20" /></label
                ><label class="field"
                    >{{ $t('maximum_choices') }}<InputNumber v-model="form.max_choices" :min="0" :max="20"
                /></label>
            </div>
            <div v-for="(option, i) in form.options" :key="i" class="flex gap-2">
                <InputText
                    v-model="option.name"
                    :placeholder="$t('option_name')"
                    :aria-label="$t('option_name')"
                    required
                /><InputNumber v-model="option.price" :min="0" :aria-label="$t('option_price_in_bani')" /><Button
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
            /><Button :label="$t('save_group')" type="submit" :loading="form.processing" /></form
    ></Dialog>
</template>
