<script setup>
import { onBeforeUnmount, onMounted, ref, watch } from 'vue';
import L from 'leaflet';

const props = defineProps({
  markers: { type: Array, default: () => [] },
  pickLocation: { type: Boolean, default: false },
  compact: { type: Boolean, default: false },
});

const emit = defineEmits(['location-selected']);

let map;
let markerLayer;
let resizeObserver;
let resizeFrame;
const mapElement = ref(null);

const invalidateMapSize = () => {
  if (!map || resizeFrame) {
    return;
  }

  resizeFrame = requestAnimationFrame(() => {
    map?.invalidateSize({ pan: false, debounceMoveend: true });
    resizeFrame = undefined;
  });
};

const markerColor = (kind) => ({
  stall: '#2563eb',
  inactive: '#64748b',
  order: '#b4532a',
  selected: '#059669',
}[kind] || '#2563eb');

const popupElement = (marker) => {
  const container = document.createElement('div');
  const title = document.createElement('strong');
  title.textContent = marker.label || 'Ubicación';
  container.append(title);

  if (marker.description) {
    const description = document.createElement('div');
    description.textContent = marker.description;
    container.append(description);
  }

  return container;
};

const renderMarkers = () => {
  if (!map || !markerLayer) {
    return;
  }

  markerLayer.clearLayers();
  const validMarkers = props.markers.filter((marker) => Number.isFinite(Number(marker.latitude))
    && Number.isFinite(Number(marker.longitude)));
  const coordinates = [];

  validMarkers.forEach((marker) => {
    const position = [Number(marker.latitude), Number(marker.longitude)];
    coordinates.push(position);
    L.circleMarker(position, {
      radius: marker.kind === 'selected' ? 9 : 8,
      color: markerColor(marker.kind),
      fillColor: markerColor(marker.kind),
      fillOpacity: 0.9,
      weight: 2,
    }).bindPopup(popupElement(marker)).addTo(markerLayer);
  });

  if (coordinates.length === 1) {
    map.setView(coordinates[0], Math.max(map.getZoom(), 14));
  } else if (coordinates.length > 1) {
    map.fitBounds(coordinates, { padding: [32, 32], maxZoom: 15 });
  }

  invalidateMapSize();
};

const handleMapClick = (event) => {
  if (!props.pickLocation) {
    return;
  }

  emit('location-selected', {
    latitude: Number(event.latlng.lat.toFixed(7)),
    longitude: Number(event.latlng.lng.toFixed(7)),
  });
};

onMounted(() => {
  map = L.map(mapElement.value, {
    scrollWheelZoom: false,
  }).setView([-16.2902, -63.5887], 5);

  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    maxZoom: 19,
    attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
  }).addTo(map);

  markerLayer = L.layerGroup().addTo(map);
  map.on('click', handleMapClick);
  if ('ResizeObserver' in window) {
    resizeObserver = new ResizeObserver(invalidateMapSize);
    resizeObserver.observe(mapElement.value);
  }
  window.addEventListener('resize', invalidateMapSize);
  renderMarkers();
});

watch(() => props.markers, renderMarkers, { deep: true });
watch(() => props.pickLocation, invalidateMapSize);

onBeforeUnmount(() => {
  resizeObserver?.disconnect();
  window.removeEventListener('resize', invalidateMapSize);

  if (resizeFrame) {
    cancelAnimationFrame(resizeFrame);
  }

  if (map) {
    map.off('click', handleMapClick);
    map.remove();
    map = undefined;
  }
});
</script>

<template>
  <div
    ref="mapElement"
    :class="[
      'z-0 w-full rounded-xl bg-slate-200',
      compact ? 'h-56 sm:h-72' : 'h-80 sm:h-[26rem]',
      pickLocation ? 'cursor-crosshair' : '',
    ]"
    role="application"
    aria-label="Mapa de ubicaciones de puestos y pedidos"
  ></div>
</template>
