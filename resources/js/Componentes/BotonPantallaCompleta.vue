<script setup>
import { onBeforeUnmount, onMounted, ref } from 'vue';
import UiIcon from './Icono.vue';

const isFullscreen = ref(false);
const isSupported = ref(true);

const updateState = () => {
  isFullscreen.value = Boolean(document.fullscreenElement);
};

const toggleFullscreen = async () => {
  if (! document.fullscreenEnabled) {
    isSupported.value = false;

    return;
  }

  try {
    if (document.fullscreenElement) {
      await document.exitFullscreen();

      return;
    }

    await document.documentElement.requestFullscreen();
  } catch {
    isSupported.value = false;
  }
};

const handleShortcut = (event) => {
  if (! event.altKey || event.key !== 'Enter') {
    return;
  }

  const target = event.target;

  if (target instanceof HTMLElement
    && (['INPUT', 'TEXTAREA', 'SELECT'].includes(target.tagName) || target.isContentEditable)) {
    return;
  }

  event.preventDefault();
  event.stopPropagation();
  toggleFullscreen();
};

onMounted(() => {
  isSupported.value = Boolean(document.fullscreenEnabled);
  updateState();
  document.addEventListener('fullscreenchange', updateState);
  window.addEventListener('keydown', handleShortcut);
});

onBeforeUnmount(() => {
  document.removeEventListener('fullscreenchange', updateState);
  window.removeEventListener('keydown', handleShortcut);

  if (document.fullscreenElement) {
    document.exitFullscreen().catch(() => {});
  }
});
</script>

<template>
  <button
    v-if="isSupported"
    type="button"
    :aria-pressed="isFullscreen"
    aria-keyshortcuts="Alt+Enter"
    :aria-label="isFullscreen ? 'Salir de pantalla completa (Alt+Enter)' : 'Pantalla completa (Alt+Enter)'"
    :title="isFullscreen ? 'Salir de pantalla completa (Alt+Enter)' : 'Pantalla completa (Alt+Enter)'"
    class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl border border-slate-200 bg-white text-slate-700 transition hover:bg-slate-100 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500"
    @click="toggleFullscreen"
  >
    <UiIcon :name="isFullscreen ? 'compress' : 'expand'" :size="18" />
  </button>
</template>
