<script setup lang="ts">
import { nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import type * as Leaflet from 'leaflet';
import 'leaflet/dist/leaflet.css';
import '@geoman-io/leaflet-geoman-free/dist/leaflet-geoman.css';
import { i18n } from '@/i18n';
import type { DeliveryPoint, DeliveryPolygon, MapConfig } from '@/lib/deliveryGeometry';
import type { Zone } from '@/types';

const props = defineProps<{
    mode: 'draw' | 'pick';
    geometry?: DeliveryPolygon | null;
    point?: DeliveryPoint | null;
    zones?: Zone[];
    center?: number[];
    mapConfig: MapConfig;
}>();
const emit = defineEmits<{
    'update:geometry': [value: DeliveryPolygon | null];
    'update:point': [value: DeliveryPoint];
}>();
const container = ref<HTMLDivElement | null>(null);
const failed = ref(false);
const tileFailed = ref(false);
let map: Leaflet.Map | null = null;
let group: Leaflet.FeatureGroup | null = null;
let marker: Leaflet.Marker | null = null;
let L: typeof Leaflet;
let last = '';
let disposed = false;
function language() {
    if (!map || props.mode !== 'draw') return;
    map.pm.setLang(
        i18n.global.locale.value,
        {
            buttonTitles: {
                drawPolyButton: i18n.global.t('map_draw'),
                editButton: i18n.global.t('map_edit'),
                deleteButton: i18n.global.t('map_delete'),
            },
            actions: {
                finish: i18n.global.t('map_finish'),
                cancel: i18n.global.t('map_cancel'),
                removeLastVertex: i18n.global.t('map_undo'),
            },
            tooltips: {
                firstVertex: i18n.global.t('map_first_vertex'),
                continueLine: i18n.global.t('map_continue'),
                finishPoly: i18n.global.t('map_finish_poly'),
            },
        },
        'en',
    );
}
function bind(layer: Leaflet.Layer) {
    layer.on('pm:edit', () => publish(layer));
}
function publish(layer: Leaflet.Layer) {
    const geometry = (layer as Leaflet.Polygon).toGeoJSON().geometry as DeliveryPolygon;
    last = JSON.stringify(geometry);
    emit('update:geometry', geometry);
}
function renderGeometry(fit = false) {
    if (!map || !group) return;
    group.clearLayers();
    last = JSON.stringify(props.geometry ?? null);
    if (props.geometry) {
        const geo = L.geoJSON(props.geometry, { style: { color: '#6952ff', weight: 3, fillOpacity: 0.2 } });
        geo.eachLayer((layer) => {
            group!.addLayer(layer);
            bind(layer);
        });
        if (fit && geo.getBounds().isValid()) map.fitBounds(geo.getBounds(), { padding: [30, 30], maxZoom: 16 });
    }
}
function setPoint(point: DeliveryPoint) {
    if (!map) return;
    if (marker) marker.setLatLng([point.latitude, point.longitude]);
    else {
        marker = L.marker([point.latitude, point.longitude], {
            draggable: true,
            icon: L.divIcon({
                className: 'zf-map-pin',
                html: '<span></span>',
                iconSize: [24, 24],
                iconAnchor: [12, 12],
            }),
        }).addTo(map);
        marker.on('dragend', () => {
            const pos = marker!.getLatLng();
            emit('update:point', { latitude: pos.lat, longitude: pos.lng });
        });
    }
}
onMounted(async () => {
    try {
        L = await import('leaflet');
        await import('@geoman-io/leaflet-geoman-free');
        await nextTick();
        if (disposed || !container.value) return;
        const center = props.center ?? [44.4268, 26.1025];
        map = L.map(container.value).setView([center[0], center[1]], 13);
        L.tileLayer(props.mapConfig.tile_url, { attribution: props.mapConfig.attribution, maxZoom: 19 })
            .on('tileerror', () => {
                tileFailed.value = true;
            })
            .addTo(map);
        group = L.featureGroup().addTo(map);
        if (props.mode === 'draw') {
            map.pm.addControls({
                position: 'topleft',
                drawMarker: false,
                drawCircleMarker: false,
                drawPolyline: false,
                drawRectangle: false,
                drawCircle: false,
                drawText: false,
                cutPolygon: false,
                rotateMode: false,
                dragMode: false,
                drawPolygon: true,
                editMode: true,
                removalMode: true,
            });
            map.pm.setGlobalOptions({ allowSelfIntersection: false });
            language();
            renderGeometry(true);
            map.on('pm:create', (event) => {
                const layer = event.layer;
                group!.clearLayers();
                group!.addLayer(layer);
                bind(layer);
                publish(layer);
            });
            map.on('pm:remove', () => {
                last = 'null';
                emit('update:geometry', null);
            });
        } else {
            const bounds = L.latLngBounds([]);
            for (const zone of props.zones ?? [])
                if (zone.geometry) {
                    const layer = L.geoJSON(zone.geometry, {
                        style: { color: '#6952ff', weight: 2, fillOpacity: 0.12 },
                        pmIgnore: true,
                    });
                    layer.addTo(map);
                    bounds.extend(layer.getBounds());
                }
            if (bounds.isValid()) map.fitBounds(bounds, { padding: [20, 20], maxZoom: 15 });
            if (props.point) setPoint(props.point);
            map.on('click', (event: Leaflet.LeafletMouseEvent) => {
                const point = { latitude: event.latlng.lat, longitude: event.latlng.lng };
                setPoint(point);
                emit('update:point', point);
            });
        }
        map.invalidateSize();
    } catch {
        failed.value = true;
    }
});
watch(
    () => props.geometry,
    () => {
        if (JSON.stringify(props.geometry ?? null) !== last) renderGeometry();
    },
    { deep: true },
);
watch(
    () => props.point,
    (point) => {
        if (point) setPoint(point);
    },
    { deep: true },
);
watch(() => i18n.global.locale.value, language);
onBeforeUnmount(() => {
    disposed = true;
    map?.remove();
    map = null;
});
</script>
<template>
    <div class="space-y-3">
        <p class="text-sm text-slate-500">{{ mode === 'draw' ? $t('map_draw_hint') : $t('map_pick_hint') }}</p>
        <p v-if="failed" role="alert" class="rounded-lg bg-red-50 p-3 text-red-700">{{ $t('map_load_error') }}</p>
        <p v-if="tileFailed" role="status" class="rounded-lg bg-amber-50 p-3 text-sm text-amber-900">
            {{ $t('map_tiles_error') }}
        </p>
        <div
            ref="container"
            :aria-label="$t(mode === 'draw' ? 'map_zone_editor' : 'map_delivery_location')"
            :data-testid="mode === 'draw' ? 'zone-map' : 'delivery-map'"
            class="zf-delivery-map relative z-0 h-96 w-full rounded-xl border border-slate-200 bg-slate-100"
        />
    </div>
</template>
<style>
.zf-delivery-map .leaflet-pm-toolbar {
    z-index: 700;
}
.zf-map-pin span {
    display: block;
    width: 24px;
    height: 24px;
    background: #6952ff;
    border: 4px solid white;
    border-radius: 50%;
    box-shadow: 0 1px 5px #3338;
}
</style>
