<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { formatCurrency } from '../utilidades/moneda.js';

const props = defineProps({
  value: { type: Number, default: 0 },
  format: { type: String, default: 'number' },
  duration: { type: Number, default: 850 },
});

const displayValue = ref(0);
const numberFormatter = new Intl.NumberFormat('es-BO');
let animationFrame = null;

const target = computed(() => Number(props.value) || 0);

const formatted = computed(() => props.format === 'currency'
  ? formatCurrency(displayValue.value)
  : numberFormatter.format(Math.round(displayValue.value)));

const prefersReducedMotion = () => window.matchMedia?.('(prefers-reduced-motion: reduce)').matches;

const stopAnimation = () => {
  if (animationFrame !== null) {
    cancelAnimationFrame(animationFrame);
    animationFrame = null;
  }
};

const animateTo = (nextValue) => {
  stopAnimation();

  if (prefersReducedMotion() || props.duration <= 0) {
    displayValue.value = nextValue;

    return;
  }

  const from = displayValue.value;
  const startedAt = performance.now();

  const step = (now) => {
    const progress = Math.min(1, (now - startedAt) / props.duration);
    const eased = 1 - Math.pow(1 - progress, 3);

    displayValue.value = from + (nextValue - from) * eased;

    if (progress < 1) {
      animationFrame = requestAnimationFrame(step);

      return;
    }

    displayValue.value = nextValue;
    animationFrame = null;
  };

  animationFrame = requestAnimationFrame(step);
};

onMounted(() => animateTo(target.value));
watch(target, animateTo);
onBeforeUnmount(stopAnimation);
</script>

<template>
  <span class="tabular-nums">{{ formatted }}</span>
</template>
