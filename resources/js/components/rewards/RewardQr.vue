<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';
import QRCode from 'qrcode';
import type { RewardCode } from '@/lib/rewards';

const props = defineProps<{ ticket: RewardCode; payload: string }>();
const source = ref('');
const now = ref(Date.now());
const seconds = computed(() => Math.max(0, Math.ceil((Date.parse(props.ticket.expires_at) - now.value) / 1000)));
const time = computed(() => Math.floor(seconds.value / 60) + ':' + String(seconds.value % 60).padStart(2, '0'));
let timer: ReturnType<typeof setInterval>;

watch(
    () => props.payload,
    async (payload, _, cleanup) => {
        let current = true;
        cleanup(() => {
            current = false;
        });
        source.value = '';
        now.value = Date.now();
        const image = await QRCode.toDataURL(payload, { width: 224, margin: 3 });
        if (current) source.value = image;
    },
    { immediate: true },
);
onMounted(() => {
    timer = setInterval(() => {
        now.value = Date.now();
    }, 1000);
});
onUnmounted(() => clearInterval(timer));
</script>

<template>
    <div
        class="grid justify-items-center gap-2 rounded-2xl border border-slate-200 bg-slate-50 p-4 dark:border-slate-700 dark:bg-slate-900"
    >
        <template v-if="seconds">
            <img v-if="source" :src="source" width="224" height="224" :alt="$t('rewards_qr_alt')" class="rounded-xl" />
            <code class="text-lg font-bold tracking-widest">{{ ticket.code }}</code>
            <p class="muted text-sm">{{ $t('rewards_expires', { time }) }}</p>
        </template>
        <p v-else class="muted">{{ $t('rewards_expired') }}</p>
    </div>
</template>
