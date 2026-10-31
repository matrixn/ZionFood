<script setup lang="ts">
import { onMounted, onBeforeUnmount, ref, watch } from 'vue';
import type * as Leaflet from 'leaflet';
import 'leaflet/dist/leaflet.css';
import type { ImportedZone } from '@/lib/migrationSettings';
import type { MapConfig } from '@/lib/deliveryGeometry';

const props = defineProps<{ zones: ImportedZone[]; mapConfig: MapConfig; label: string }>();
const container = ref<HTMLDivElement | null>(null);
let map: Leaflet.Map | undefined;
let layer: Leaflet.FeatureGroup | undefined;
let L: typeof Leaflet;
let disposed = false;
function render() {
    if (!map || !layer) return;
    layer.clearLayers();
    for (const zone of props.zones) {
        if (zone.geometry) {
            const polygon = L.geoJSON(zone.geometry, {
                style: { color: zone.id ? '#64748b' : '#6555df', weight: 3, fillOpacity: 0.16 },
            });
            const label = document.createElement('span');
            label.textContent = zone.name;
            polygon.bindTooltip(label);
            layer.addLayer(polygon);
        }
    }
    if (layer.getBounds().isValid()) map.fitBounds(layer.getBounds(), { padding: [15, 15], maxZoom: 15 });
}
onMounted(async () => {
    L = await import('leaflet');
    if (disposed || !container.value) return;
    map = L.map(container.value, { scrollWheelZoom: false }).setView([44.4268, 26.1025], 10);
    L.tileLayer(props.mapConfig.tile_url, { attribution: props.mapConfig.attribution, maxZoom: 19 }).addTo(map);
    layer = L.featureGroup().addTo(map);
    render();
});
watch(() => props.zones, render, { deep: true });
onBeforeUnmount(() => {
    disposed = true;
    map?.remove();
});
</script>
<template>
    <div
        ref="container"
        role="img"
        :aria-label="label"
        class="relative z-0 mt-3 h-52 rounded-xl border border-slate-200"
    />
</template>
