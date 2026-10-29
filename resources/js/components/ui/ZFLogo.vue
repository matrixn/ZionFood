<script setup lang="ts">
withDefaults(defineProps<{ size?: number }>(), { size: 40 });
const base = '/images/logo/food.zion3d.ro_icons-';
const srcset = [16, 20, 24, 32, 40, 48, 64, 96, 128, 256]
    .map((size) => `${base}${size}x${size}.png ${size}w`)
    .join(', ');
</script>
<template>
    <img
        :src="base + '64x64.png'"
        :srcset="srcset"
        :sizes="`${size}px`"
        :width="size"
        :height="size"
        alt=""
        aria-hidden="true"
        decoding="async"
        class="inline-block shrink-0 object-contain"
        data-testid="zionfood-logo"
    />
</template>
