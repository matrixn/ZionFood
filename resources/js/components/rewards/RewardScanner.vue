<script setup lang="ts">
import { onBeforeUnmount, ref } from 'vue';
import Button from 'primevue/button';

const emit = defineEmits<{ scan: [code: string] }>();
const video = ref<HTMLVideoElement>();
const running = ref(false);
const failed = ref(false);
let stream: MediaStream | undefined;
let timer: number | undefined;
let generation = 0;

function stop() {
    generation++;
    running.value = false;
    window.clearTimeout(timer);
    stream?.getTracks().forEach((track) => track.stop());
    stream = undefined;
}

async function start() {
    const attempt = ++generation;
    failed.value = false;
    running.value = true;
    try {
        const { default: decode } = await import('jsqr');
        if (generation !== attempt) return;
        const camera = await navigator.mediaDevices.getUserMedia({
            video: { facingMode: { ideal: 'environment' } },
            audio: false,
        });
        if (generation !== attempt || !video.value) {
            camera.getTracks().forEach((track) => track.stop());
            return;
        }
        stream = camera;
        const player = video.value;
        player.srcObject = camera;
        await player.play();
        if (generation !== attempt) return;
        const canvas = document.createElement('canvas');
        const context = canvas.getContext('2d', { willReadFrequently: true });
        if (!context) throw new Error();
        function frame() {
            if (!context || generation !== attempt) return;
            if (player.videoWidth && player.readyState >= 2) {
                canvas.width = Math.min(640, player.videoWidth);
                canvas.height = Math.round((canvas.width * player.videoHeight) / player.videoWidth);
                context.drawImage(player, 0, 0, canvas.width, canvas.height);
                const image = context.getImageData(0, 0, canvas.width, canvas.height);
                const result = decode(image.data, image.width, image.height, { inversionAttempts: 'dontInvert' });
                if (result && /^ZFR:[A-Z0-9]{12}$/.test(result.data)) {
                    stop();
                    emit('scan', result.data.slice(4));
                    return;
                }
            }
            timer = window.setTimeout(frame, 180);
        }
        frame();
    } catch {
        if (generation === attempt) {
            stop();
            failed.value = true;
        }
    }
}
onBeforeUnmount(stop);
</script>

<template>
    <div class="grid gap-2">
        <video
            v-if="running"
            ref="video"
            playsinline
            muted
            class="aspect-video max-w-sm rounded-xl bg-slate-900 object-contain"
        />
        <div>
            <Button
                v-if="running"
                type="button"
                size="small"
                severity="secondary"
                :label="$t('rewards_stop_scan')"
                @click="stop"
            />
            <Button
                v-else
                type="button"
                size="small"
                outlined
                icon="pi pi-camera"
                :label="$t('rewards_scan')"
                @click="start"
            />
        </div>
        <small v-if="failed" role="status" class="muted">{{ $t('rewards_camera_error') }}</small>
    </div>
</template>
