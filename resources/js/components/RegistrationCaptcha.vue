<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { i18n, tr as t } from '@/i18n';
import { api } from '@/lib/http';
import Button from 'primevue/button';
import InputText from 'primevue/inputtext';
import ZFFormField from '@/components/ui/ZFFormField.vue';

interface Challenge {
    id: string;
    mode: 'visual' | 'accessible';
    image: string | null;
    prompt: string | null;
    max_digits: number;
}
const props = defineProps<{ modelValue: string; error?: string }>();
const emit = defineEmits<{ 'update:modelValue': [string]; verified: [] }>();
const challenge = ref<Challenge | null>(null);
const mode = ref<'visual' | 'accessible'>('visual');
const answer = ref('');
const loading = ref(false);
const verifying = ref(false);
const localError = ref('');
let generation = 0;
let disposed = false;
let timer: ReturnType<typeof setTimeout> | undefined;
const ready = computed(
    () => !!challenge.value && (mode.value === 'visual' ? answer.value.length === 4 : answer.value.length > 0),
);

async function renew() {
    const current = ++generation;
    clearTimeout(timer);
    emit('update:modelValue', '');
    challenge.value = null;
    answer.value = '';
    localError.value = '';
    loading.value = true;
    verifying.value = false;
    try {
        const result = await api<Challenge>('/register/challenge', 'POST', { mode: mode.value });
        if (!disposed && current === generation) challenge.value = result;
    } catch {
        if (!disposed && current === generation) localError.value = t('captcha_unavailable');
    } finally {
        if (!disposed && current === generation) loading.value = false;
    }
}
async function verify() {
    if (!ready.value || !challenge.value || verifying.value) return;
    const current = generation;
    verifying.value = true;
    localError.value = '';
    try {
        const result = await api<{ proof: string; expires_in: number }>('/register/challenge/verify', 'POST', {
            challenge_id: challenge.value.id,
            captcha_answer: answer.value,
        });
        if (disposed || current !== generation) return;
        emit('update:modelValue', result.proof);
        emit('verified');
        answer.value = '';
        challenge.value = null;
        timer = setTimeout(() => {
            emit('update:modelValue', '');
            localError.value = t('captcha_expired');
        }, result.expires_in * 1000);
    } catch {
        if (!disposed && current === generation) {
            await renew();
            if (!disposed) localError.value = t('captcha_retry');
        }
    } finally {
        if (!disposed && current === generation) verifying.value = false;
    }
}
function digit(value: string) {
    if (!verifying.value && challenge.value) answer.value = (answer.value + value).slice(0, challenge.value.max_digits);
}
function changeMode() {
    mode.value = mode.value === 'visual' ? 'accessible' : 'visual';
    void renew();
}
watch(
    () => i18n.global.locale.value,
    () => {
        void renew();
    },
);
onMounted(renew);
onBeforeUnmount(() => {
    disposed = true;
    generation++;
    clearTimeout(timer);
});
defineExpose({ renew });
</script>

<template>
    <section
        class="receipt-check"
        data-testid="registration-captcha"
        :aria-label="$t('captcha_title')"
        :aria-busy="loading || verifying"
    >
        <div class="flex items-center justify-between gap-3">
            <div class="flex items-center gap-3">
                <span class="receipt-symbol" aria-hidden="true"
                    ><i :class="modelValue ? 'pi pi-check' : 'pi pi-receipt'"
                /></span>
                <div>
                    <p class="text-xs font-semibold uppercase tracking-widest text-brand">ZionFood Check</p>
                    <h2 class="text-base font-semibold">{{ $t('captcha_title') }}</h2>
                </div>
            </div>
            <span class="receipt-badge">{{ $t(modelValue ? 'captcha_done_badge' : 'captcha_private') }}</span>
        </div>
        <Transition name="receipt" mode="out-in">
            <div v-if="modelValue" key="done" class="receipt-success" role="status" data-testid="captcha-verified">
                <i class="pi pi-check-circle text-2xl" aria-hidden="true" />
                <div>
                    <p class="font-semibold">{{ $t('captcha_success') }}</p>
                    <p class="text-sm">{{ $t('captcha_success_hint') }}</p>
                </div>
            </div>
            <div v-else key="challenge" class="mt-4 space-y-3">
                <p class="text-sm text-slate-600 dark:text-slate-300">
                    {{ $t(mode === 'visual' ? 'captcha_visual_hint' : 'captcha_accessible_hint') }}
                </p>
                <div v-if="loading" role="status" class="receipt-loading">{{ $t('captcha_loading') }}</div>
                <template v-else-if="challenge">
                    <img
                        v-if="challenge.image"
                        :src="challenge.image"
                        :alt="$t('captcha_image_alt')"
                        width="640"
                        height="168"
                        class="receipt-image"
                        draggable="false"
                    />
                    <p v-else class="receipt-question" data-testid="captcha-question">{{ challenge.prompt }}</p>
                    <ZFFormField
                        :label="$t('captcha_answer')"
                        for="captcha-answer"
                        :help="$t('captcha_help')"
                        :help-tabbable="false"
                    >
                        <InputText
                            id="captcha-answer"
                            aria-describedby="captcha-answer-help"
                            :model-value="answer"
                            :aria-label="$t('captcha_answer')"
                            :maxlength="challenge.max_digits"
                            inputmode="numeric"
                            autocomplete="off"
                            :disabled="verifying"
                            class="receipt-answer"
                            @update:model-value="
                                answer = String($event ?? '')
                                    .replace(/[^0-9]/g, '')
                                    .slice(0, challenge?.max_digits ?? 4)
                            "
                            @keydown.enter.prevent="verify"
                        />
                    </ZFFormField>
                    <div class="receipt-keypad" :aria-label="$t('captcha_keypad')">
                        <button
                            v-for="n in ['1', '2', '3', '4', '5', '6', '7', '8', '9', '0']"
                            :key="n"
                            type="button"
                            :aria-label="$t('captcha_digit', { digit: n })"
                            :disabled="verifying"
                            @click="digit(n)"
                        >
                            {{ n }}
                        </button>
                    </div>
                    <div class="flex items-center justify-between gap-2">
                        <Button
                            type="button"
                            text
                            size="small"
                            icon="pi pi-delete-left"
                            :label="$t('captcha_clear')"
                            :disabled="verifying || !answer"
                            @click="answer = ''"
                        />
                        <Button
                            type="button"
                            size="small"
                            icon="pi pi-check"
                            :label="$t('captcha_verify')"
                            :loading="verifying"
                            :disabled="!ready"
                            @click="verify"
                        />
                    </div>
                </template>
                <div
                    class="flex flex-wrap items-center justify-between gap-2 border-t border-dashed border-slate-200 pt-2 dark:border-slate-700"
                >
                    <Button
                        type="button"
                        text
                        size="small"
                        icon="pi pi-refresh"
                        :label="$t('captcha_new')"
                        :disabled="loading || verifying"
                        @click="renew"
                    />
                    <button
                        type="button"
                        class="text-xs font-medium text-brand underline-offset-4 hover:underline"
                        :disabled="loading || verifying"
                        @click="changeMode"
                    >
                        {{ $t(mode === 'visual' ? 'captcha_accessible' : 'captcha_visual') }}
                    </button>
                </div>
            </div>
        </Transition>
        <p v-if="localError || props.error" role="alert" class="mt-3 text-sm text-red-700 dark:text-red-300">
            {{ localError || props.error }}
        </p>
    </section>
</template>

<style scoped>
.receipt-check {
    border: 1px solid #dedaf9;
    border-radius: 1.25rem;
    padding: 1.1rem;
    background: linear-gradient(145deg, #faf9ff, #fff);
    box-shadow: 0 6px 24px #635bff08;
}
.receipt-symbol {
    display: grid;
    place-items: center;
    width: 2.6rem;
    height: 2.6rem;
    border-radius: 0.9rem;
    color: #635bff;
    background: #eeecff;
}
.receipt-badge {
    font-size: 0.65rem;
    font-weight: 600;
    border: 1px solid #e5e2f4;
    border-radius: 999px;
    padding: 0.25rem 0.5rem;
    white-space: nowrap;
    color: #665c89;
}
.receipt-image {
    display: block;
    width: 100%;
    height: auto;
    border-radius: 0.75rem;
    border: 1px solid #ebe3d4;
}
.receipt-question {
    padding: 1rem;
    border-radius: 0.75rem;
    background: #fffaf0;
    color: #232840;
    font-weight: 600;
    text-align: center;
}
.receipt-loading {
    padding: 2rem 0.5rem;
    text-align: center;
    font-size: 0.875rem;
    color: #64748b;
}
.receipt-answer {
    width: 100%;
    text-align: center;
    font-size: 1.4rem;
    letter-spacing: 0.45rem;
    font-variant-numeric: tabular-nums;
}
.receipt-keypad {
    display: grid;
    grid-template-columns: repeat(5, minmax(0, 1fr));
    gap: 0.4rem;
}
.receipt-keypad button {
    min-height: 2.75rem;
    border: 1px solid #e2dff0;
    border-radius: 0.7rem;
    background: white;
    color: #333653;
    font-weight: 600;
    transition:
        background 0.15s,
        transform 0.15s;
}
.receipt-keypad button:hover {
    background: #eeecff;
}
.receipt-keypad button:active {
    transform: scale(0.94);
}
.receipt-keypad button:focus-visible {
    outline: 2px solid #635bff;
    outline-offset: 2px;
}
.receipt-success {
    display: flex;
    align-items: center;
    gap: 0.8rem;
    margin-top: 1rem;
    padding: 1rem;
    border-radius: 0.9rem;
    color: #066547;
    background: #e6f8ee;
}
.receipt-enter-active,
.receipt-leave-active {
    transition:
        opacity 0.18s,
        transform 0.18s;
}
.receipt-enter-from,
.receipt-leave-to {
    opacity: 0;
    transform: translateY(4px);
}
:global(.dark) .receipt-check {
    border-color: #3b3656;
    background: linear-gradient(145deg, #232136, #1d2434);
}
:global(.dark) .receipt-badge {
    border-color: #484159;
    color: #c7bddf;
}
@media (prefers-reduced-motion: reduce) {
    .receipt-enter-active,
    .receipt-leave-active,
    .receipt-keypad button {
        transition: none;
    }
}
</style>
