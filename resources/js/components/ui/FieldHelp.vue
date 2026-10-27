<script setup lang="ts">
import { computed, ref, useId } from 'vue';
import { fieldHelp } from '@/lib/fieldHelp';
import { tr as t } from '@/i18n';
const props = defineProps<{ label?: string; field?: string; text?: string; kind?: string }>();
const id = useId();
const visible = ref(false);
const position = ref({ left: '0px', top: '0px' });
const anchor = ref<HTMLElement>();
const help = computed(() => props.text || fieldHelp(props.field ?? '', props.label ?? '', props.kind ?? ''));
function show() {
    const bounds = anchor.value?.getBoundingClientRect();
    if (!bounds) return;
    position.value = {
        left: Math.max(12, Math.min(bounds.left, window.innerWidth - 300)) + 'px',
        top: Math.min(bounds.bottom + 8, window.innerHeight - 170) + 'px',
    };
    visible.value = true;
}
</script>
<template>
    <span ref="anchor" class="field-help shrink-0" @mouseenter="show" @mouseleave="visible = false">
        <button
            type="button"
            class="field-help-trigger"
            :aria-label="t('field_info', { field: label || t('field_value') })"
            :aria-describedby="visible ? id : undefined"
            @focus="show"
            @blur="visible = false"
            @click.stop.prevent="show"
            @keydown.esc.stop="visible = false"
        >
            <i class="pi pi-info-circle" aria-hidden="true" />
        </button>
        <Teleport to="body"
            ><div v-if="visible" :id="id" role="tooltip" class="field-help-popup" :style="position">
                {{ help }}
            </div></Teleport
        >
    </span>
</template>
