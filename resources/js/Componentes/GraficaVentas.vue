<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { formatCurrency } from '../utilidades/moneda.js';

const props = defineProps({
  days: { type: Array, default: () => [] },
  height: { type: Number, default: 270 },
  mode: { type: String, default: 'ventas' },
});

const wrapper = ref(null);
const chartWidth = ref(720);
const activeIndex = ref(null);
const clipId = `plot-clip-${Math.random().toString(36).slice(2, 9)}`;
let resizeObserver = null;

const esFinanzas = computed(() => props.mode === 'finanzas');
const primaryColor = computed(() => esFinanzas.value ? '#f87171' : 'var(--chart-current)');
const secondaryColor = computed(() => esFinanzas.value ? '#4ade80' : 'var(--chart-previous)');
const primaryLabel = computed(() => esFinanzas.value ? 'Gastos' : 'Esta semana');
const secondaryLabel = computed(() => esFinanzas.value ? 'Ganancia' : 'Semana anterior');

const primaryValue = (day) => esFinanzas.value ? Number(day.gastos) || 0 : Number(day.total) || 0;
const secondaryValue = (day) => esFinanzas.value ? Number(day.ganancia) || 0 : Number(day.previous_total) || 0;

const padding = computed(() => ({
  top: 20,
  right: 18,
  bottom: 36,
  left: 58,
}));

const plotWidth = computed(() => Math.max(1, chartWidth.value - padding.value.left - padding.value.right));
const plotHeight = computed(() => Math.max(1, props.height - padding.value.top - padding.value.bottom));

const niceMax = (value) => {
  if (value <= 0) {
    return 100;
  }

  const magnitude = 10 ** Math.floor(Math.log10(value));
  const normalized = value / magnitude;
  const step = normalized <= 1 ? 1 : normalized <= 2 ? 2 : normalized <= 2.5 ? 2.5 : normalized <= 5 ? 5 : 10;

  return step * magnitude;
};

const maxValue = computed(() => {
  const max = Math.max(0, ...props.days.flatMap((day) => [primaryValue(day), secondaryValue(day)]));

  if (max > 0) {
    return niceMax(max);
  }

  return 100;
});

const minValue = computed(() => {
  const min = Math.min(0, ...props.days.flatMap((day) => [primaryValue(day), secondaryValue(day)]));

  return min < 0 ? -niceMax(Math.abs(min)) : 0;
});

const valueRange = computed(() => Math.max(1, maxValue.value - minValue.value));

const axisTicks = computed(() => Array.from({ length: 5 }, (_, index) => maxValue.value - ((maxValue.value - minValue.value) / 4) * index));

const xFor = (index) => {
  if (props.days.length <= 1) {
    return padding.value.left + plotWidth.value / 2;
  }

  return padding.value.left + (index / (props.days.length - 1)) * plotWidth.value;
};

const yFor = (value) => padding.value.top + plotHeight.value - (((Number(value) || 0) - minValue.value) / valueRange.value) * plotHeight.value;

const currentPoints = computed(() => props.days.map((day, index) => ({
  ...day,
  x: xFor(index),
  y: yFor(primaryValue(day)),
})));

const previousPoints = computed(() => props.days.map((day, index) => ({
  ...day,
  x: xFor(index),
  y: yFor(secondaryValue(day)),
})));

const linePath = (points) => {
  if (!points.length) {
    return '';
  }

  let path = `M ${points[0].x} ${points[0].y}`;

  for (let index = 1; index < points.length; index++) {
    const previous = points[index - 1];
    const current = points[index];
    const middle = (previous.x + current.x) / 2;

    path += ` C ${middle} ${previous.y}, ${middle} ${current.y}, ${current.x} ${current.y}`;
  }

  return path;
};

const currentPath = computed(() => linePath(currentPoints.value));
const previousPath = computed(() => linePath(previousPoints.value));
const currentAreaPath = computed(() => {
  if (!currentPoints.value.length) {
    return '';
  }

  const baseline = padding.value.top + plotHeight.value;
  const first = currentPoints.value[0];
  const last = currentPoints.value[currentPoints.value.length - 1];

  return `${currentPath.value} L ${last.x} ${baseline} L ${first.x} ${baseline} Z`;
});

const hitWidth = computed(() => plotWidth.value / Math.max(props.days.length, 1));

const activePoint = computed(() => activeIndex.value === null
  ? null
  : currentPoints.value[activeIndex.value] ?? null);

const tooltipStyle = computed(() => {
  if (!activePoint.value) {
    return {};
  }

  const point = activePoint.value;
  const left = Math.min(Math.max(point.x, 90), chartWidth.value - 90);
  const above = point.y > 96;

  return {
    left: `${left}px`,
    top: `${point.y + (above ? -14 : 16)}px`,
    transform: above ? 'translate(-50%, -100%)' : 'translate(-50%, 0)',
  };
});

const dayLabel = (day) => chartWidth.value < 600 ? day.label.split(' ')[0] : day.label;

const axisLabel = (value) => {
  if (value >= 1000) {
    const compact = new Intl.NumberFormat('es-BO', { maximumFractionDigits: 1 }).format(value / 1000);

    return `Bs ${compact}k`;
  }

  return `Bs ${new Intl.NumberFormat('es-BO', {
    maximumFractionDigits: maxValue.value < 10 ? 2 : 0,
  }).format(value)}`;
};

const chartAriaLabel = computed(() => props.days.length
  ? esFinanzas.value
    ? `Gastos y ganancia diaria. ${props.days.map((day) => `${day.label}: ${formatCurrency(day.gastos)} en gastos, ${formatCurrency(day.ganancia)} de ganancia`).join('; ')}`
    : `Ventas diarias. ${props.days.map((day) => `${day.label}: ${formatCurrency(day.total)} esta semana, ${formatCurrency(day.previous_total)} la semana anterior`).join('; ')}`
  : 'Sin movimientos en los últimos siete días');

const updateWidth = () => {
  chartWidth.value = Math.max(280, wrapper.value?.clientWidth || 720);
};

onMounted(() => {
  updateWidth();

  if (typeof ResizeObserver !== 'undefined' && wrapper.value) {
    resizeObserver = new ResizeObserver(updateWidth);
    resizeObserver.observe(wrapper.value);
  }
});

onBeforeUnmount(() => {
  resizeObserver?.disconnect();
});
</script>

<template>
  <div ref="wrapper" class="chart-enter relative w-full">
    <div class="relative" :style="{ height: `${height}px` }" @mouseleave="activeIndex = null">
      <svg
        :width="chartWidth"
        :height="height"
        :viewBox="`0 0 ${chartWidth} ${height}`"
        class="block overflow-hidden"
        role="img"
        :aria-label="chartAriaLabel"
      >
      <defs>
        <linearGradient id="sales-current-area" x1="0" y1="0" x2="0" y2="1">
          <stop offset="0%" class="chart-area-start" />
          <stop offset="100%" class="chart-area-end" />
        </linearGradient>
        <clipPath :id="clipId">
          <rect :x="padding.left" :y="padding.top" :width="plotWidth" :height="plotHeight" />
        </clipPath>
      </defs>

      <g>
        <template v-for="(tick, index) in axisTicks" :key="`tick-${index}`">
          <line
            :x1="padding.left"
            :x2="chartWidth - padding.right"
            :y1="yFor(tick)"
            :y2="yFor(tick)"
            stroke="var(--chart-grid)"
            :stroke-dasharray="index === axisTicks.length - 1 ? undefined : '3 5'"
          />
          <text :x="padding.left - 10" :y="yFor(tick) + 3" text-anchor="end" fill="var(--chart-axis)" font-size="10" font-weight="600">
            {{ axisLabel(tick) }}
          </text>
        </template>
      </g>

      <g :clip-path="`url(#${clipId})`">
        <path v-if="!esFinanzas" :d="currentAreaPath" fill="url(#sales-current-area)" class="sales-area" />
        <path
          :d="previousPath"
          fill="none"
          :stroke="secondaryColor"
          stroke-width="2.4"
          stroke-linecap="round"
          stroke-linejoin="round"
          pathLength="1"
          class="sales-line sales-line-previous"
        />
        <path
          :d="currentPath"
          fill="none"
          :stroke="primaryColor"
          stroke-width="3.2"
          stroke-linecap="round"
          stroke-linejoin="round"
          pathLength="1"
          class="sales-line sales-line-current"
        />

        <g v-for="(point, index) in previousPoints" :key="`previous-${point.date}`">
          <circle
            :cx="point.x"
            :cy="point.y"
            r="3"
            :fill="secondaryColor"
            stroke="var(--surface-color, #ffffff)"
            stroke-width="1.5"
            class="sales-point"
            :style="{ animationDelay: `${600 + index * 45}ms` }"
          />
        </g>

        <g v-for="(point, index) in currentPoints" :key="`current-${point.date}`">
          <circle
            :cx="point.x"
            :cy="point.y"
            r="4.2"
            fill="var(--surface-color, #ffffff)"
            :stroke="primaryColor"
            stroke-width="2.6"
            class="sales-point"
            :style="{ animationDelay: `${520 + index * 45}ms` }"
          />
        </g>

        <line
          v-if="activePoint"
          :x1="activePoint.x"
          :x2="activePoint.x"
          :y1="padding.top"
          :y2="padding.top + plotHeight"
          stroke="var(--chart-grid)"
          stroke-width="1.2"
          stroke-dasharray="4 4"
        />
      </g>

      <g v-for="(day, index) in days" :key="`hit-${day.date}`">
        <rect
          :x="xFor(index) - hitWidth / 2"
          :y="padding.top"
          :width="hitWidth"
          :height="plotHeight"
          fill="transparent"
          class="cursor-pointer outline-none"
          tabindex="0"
          role="button"
          :aria-label="esFinanzas
            ? `${day.label}: ${formatCurrency(day.gastos)} en gastos; ${formatCurrency(day.ganancia)} de ganancia`
            : `${day.label}: ${formatCurrency(day.total)} esta semana; ${formatCurrency(day.previous_total)} la semana anterior; ${day.tickets} ventas`"
          @mouseenter="activeIndex = index"
          @focus="activeIndex = index"
          @blur="activeIndex = null"
          @click="activeIndex = index"
          @keydown.enter="activeIndex = index"
          @keydown.space.prevent="activeIndex = index"
        />
      </g>

      <g>
        <text
          v-for="(day, index) in days"
          :key="`label-${day.date}`"
          :x="xFor(index)"
          :y="height - 12"
          text-anchor="middle"
          fill="var(--chart-axis)"
          font-size="10"
          font-weight="700"
        >
          {{ dayLabel(day) }}
        </text>
      </g>
    </svg>

    <div
      v-if="activePoint"
      class="pointer-events-none absolute z-20 w-48 rounded-xl border border-slate-200 bg-white p-3 text-left shadow-xl"
      :style="tooltipStyle"
    >
      <p class="text-[10px] font-extrabold uppercase tracking-[0.14em] text-slate-400">{{ activePoint.label }}</p>
      <div class="mt-2 space-y-1.5 text-xs">
        <div class="flex items-center justify-between gap-3">
          <span class="font-semibold text-slate-500">{{ primaryLabel }}</span>
          <strong class="text-slate-900">{{ formatCurrency(esFinanzas ? activePoint.gastos : activePoint.total) }}</strong>
        </div>
        <div class="flex items-center justify-between gap-3">
          <span class="font-semibold text-slate-500">{{ secondaryLabel }}</span>
          <strong class="text-slate-900">{{ formatCurrency(esFinanzas ? activePoint.ganancia : activePoint.previous_total) }}</strong>
        </div>
        <div v-if="!esFinanzas" class="flex items-center justify-between gap-3 border-t border-slate-100 pt-1.5">
          <span class="font-semibold text-slate-500">Ventas</span>
          <strong class="text-slate-900">{{ activePoint.tickets }}</strong>
        </div>
      </div>
    </div>

    </div>

    <div class="mt-2 flex flex-wrap items-center gap-x-4 gap-y-1 text-[10px] font-semibold text-slate-500">
      <span class="inline-flex items-center gap-1.5">
        <span class="h-2 w-2 rounded-full" :style="{ backgroundColor: primaryColor }"></span>
        {{ primaryLabel }}
      </span>
      <span class="inline-flex items-center gap-1.5">
        <span class="h-2 w-2 rounded-full" :style="{ backgroundColor: secondaryColor }"></span>
        {{ secondaryLabel }}
      </span>
      <span class="ml-auto hidden sm:inline">Pasa el cursor o toca un día para ver el detalle</span>
    </div>
  </div>
</template>
