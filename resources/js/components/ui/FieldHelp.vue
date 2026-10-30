<script setup lang="ts">
import { computed, nextTick, onBeforeUnmount, onMounted, onUpdated, ref, useId } from 'vue';
import { fieldHelp } from '@/lib/fieldHelp';
import { tr as t } from '@/i18n';
const props = withDefaults(
    defineProps<{
        label?: string;
        field?: string;
        text?: string;
        kind?: string;
        tabbable?: boolean;
        descriptionId?: string;
    }>(),
    {
        label: undefined,
        field: undefined,
        text: undefined,
        kind: undefined,
        tabbable: undefined,
        descriptionId: undefined,
    },
);
const id = useId();
const visible = ref(false);
const position = ref({ left: '0px', top: '0px' });
const anchor = ref<HTMLElement>();
const help = computed(() => props.text || fieldHelp(props.field ?? '', props.label ?? '', props.kind ?? ''));
const description = computed(() => props.descriptionId || id + '-description');
const hasControls = ref(false);
const inTabOrder = computed(() => props.tabbable ?? !hasControls.value);
const controlSelector =
    'input:not([type="hidden"]):not([type="button"]):not([type="submit"]),textarea,select,[role="combobox"],[role="checkbox"],[role="switch"],[role="spinbutton"],[role="slider"]';
const attached = new Map<HTMLElement, { description: string; addedShortcut: boolean }>();
let observer: MutationObserver | undefined;
let observed: HTMLElement | null = null;
let pending = false;
let disposed = false;
function fieldKey(event: KeyboardEvent) {
    if (event.key === 'F1') {
        event.preventDefault();
        show();
    }
    if (event.key === 'Escape' && visible.value) {
        event.stopPropagation();
        visible.value = false;
    }
}
function hide() {
    visible.value = false;
}
function detach(control: HTMLElement) {
    const link = attached.get(control);
    if (!link) return;
    const ids = (control.getAttribute('aria-describedby') || '')
        .split(/\s+/)
        .filter((value) => value && value !== link.description);
    if (ids.length) control.setAttribute('aria-describedby', ids.join(' '));
    else control.removeAttribute('aria-describedby');
    if (link.addedShortcut) {
        const keys = (control.getAttribute('aria-keyshortcuts') || '')
            .split(/\s+/)
            .filter((value) => value && value !== 'F1');
        if (keys.length) control.setAttribute('aria-keyshortcuts', keys.join(' '));
        else control.removeAttribute('aria-keyshortcuts');
    }
    control.removeEventListener('keydown', fieldKey);
    control.removeEventListener('blur', hide);
    attached.delete(control);
}
function synchronize() {
    if (disposed || !anchor.value) return;
    const label = anchor.value.closest('label') || anchor.value.closest('.field-label')?.querySelector('label[for]');
    const root =
        label instanceof HTMLLabelElement && label.htmlFor
            ? label.closest<HTMLElement>('.field') || label.parentElement
            : label ||
              anchor.value.closest<HTMLElement>('.field-control-help') ||
              anchor.value.closest('.field-label')?.parentElement ||
              null;
    const target = label instanceof HTMLLabelElement && label.htmlFor ? document.getElementById(label.htmlFor) : null;
    const controls = target
        ? target.matches(controlSelector)
            ? [target]
            : [...target.querySelectorAll<HTMLElement>(controlSelector)]
        : root
          ? [...root.querySelectorAll<HTMLElement>(controlSelector)]
          : [];
    for (const [control, link] of attached)
        if (!controls.includes(control) || link.description !== description.value) detach(control);
    for (const control of controls) {
        if (!attached.has(control)) {
            attached.set(control, {
                description: description.value,
                addedShortcut: !(control.getAttribute('aria-keyshortcuts') || '').split(/\s+/).includes('F1'),
            });
            control.addEventListener('keydown', fieldKey);
            control.addEventListener('blur', hide);
        }
        const ids = (control.getAttribute('aria-describedby') || '').split(/\s+/).filter(Boolean);
        if (!ids.includes(description.value))
            control.setAttribute('aria-describedby', [...ids, description.value].join(' '));
        const keys = (control.getAttribute('aria-keyshortcuts') || '').split(/\s+/).filter(Boolean);
        if (!keys.includes('F1')) control.setAttribute('aria-keyshortcuts', [...keys, 'F1'].join(' '));
    }
    hasControls.value = controls.length > 0;
    if (root !== observed) {
        observer?.disconnect();
        observed = root;
        if (root) {
            observer ??= new MutationObserver(schedule);
            observer.observe(root, {
                childList: true,
                subtree: true,
                attributes: true,
                attributeFilter: ['aria-describedby', 'aria-keyshortcuts', 'id', 'role', 'type'],
            });
        }
    }
}
function schedule() {
    if (pending || disposed) return;
    pending = true;
    void nextTick(() => {
        pending = false;
        synchronize();
    });
}
onMounted(schedule);
onUpdated(schedule);
onBeforeUnmount(() => {
    disposed = true;
    observer?.disconnect();
    for (const control of attached.keys()) detach(control);
});
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
            :tabindex="inTabOrder ? undefined : -1"
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
            ><span v-if="hasControls" :id="description" class="sr-only"
                >{{ help }} {{ t('field_help_keyboard_hint') }}</span
            >
            <div v-if="visible" :id="id" role="tooltip" class="field-help-popup" :style="position">
                {{ help }}
                <p v-if="hasControls" class="mt-2 text-xs opacity-80">{{ t('field_help_keyboard_hint') }}</p>
            </div></Teleport
        >
    </span>
</template>
