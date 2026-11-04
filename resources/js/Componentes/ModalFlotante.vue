<script setup>
import { nextTick, onBeforeUnmount, ref, watch } from 'vue';

const props = defineProps({
  open: { type: Boolean, default: false },
  title: { type: String, required: true },
  description: { type: String, required: true },
  titleId: { type: String, required: true },
});

const emit = defineEmits(['update:open']);
const panel = ref(null);
let previousFocus = null;
let previousBodyOverflow = '';

const close = () => emit('update:open', false);

const handleKeydown = (event) => {
  if (event.key === 'Escape') {
    event.preventDefault();
    close();

    return;
  }

  if (event.key !== 'Tab' || !panel.value) {
    return;
  }

  const focusableElements = [...panel.value.querySelectorAll(
    'a[href], button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])',
  )].filter((element) => element.getAttribute('aria-hidden') !== 'true');

  if (!focusableElements.length) {
    event.preventDefault();
    panel.value.focus();

    return;
  }

  const firstElement = focusableElements[0];
  const lastElement = focusableElements[focusableElements.length - 1];

  if (event.shiftKey && document.activeElement === firstElement) {
    event.preventDefault();
    lastElement.focus();
  } else if (!event.shiftKey && document.activeElement === lastElement) {
    event.preventDefault();
    firstElement.focus();
  }
};

const restorePageFocus = () => {
  window.removeEventListener('keydown', handleKeydown);
  document.body.style.overflow = previousBodyOverflow;
  previousFocus?.focus?.();
  previousFocus = null;
};

watch(() => props.open, async (isOpen) => {
  if (typeof document === 'undefined') {
    return;
  }

  if (!isOpen) {
    restorePageFocus();

    return;
  }

  previousFocus = document.activeElement;
  previousBodyOverflow = document.body.style.overflow;
  document.body.style.overflow = 'hidden';
  window.addEventListener('keydown', handleKeydown);
  await nextTick();
  panel.value?.querySelector('[data-modal-autofocus]')?.focus();
}, { flush: 'post' });

onBeforeUnmount(() => {
  if (props.open && typeof document !== 'undefined') {
    restorePageFocus();
  }
});
</script>

<template>
  <Teleport to="body">
    <div
      v-if="open"
      class="fixed inset-0 z-[100] flex items-center justify-center bg-slate-950/65 p-2 backdrop-blur-sm sm:p-5"
      role="dialog"
      aria-modal="true"
      :aria-labelledby="titleId"
      :aria-describedby="`${titleId}-description`"
      @click.self="close"
    >
      <section ref="panel" tabindex="-1" class="flex max-h-[calc(100dvh-1rem)] w-full max-w-6xl flex-col overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-2xl sm:max-h-[92dvh]">
        <header class="flex shrink-0 items-start justify-between gap-4 border-b border-slate-200 px-4 py-4 sm:px-6">
          <div class="min-w-0">
            <h2 :id="titleId" class="text-lg font-black text-slate-900 sm:text-xl">{{ title }}</h2>
            <p :id="`${titleId}-description`" class="mt-1 text-xs leading-relaxed text-slate-600 sm:text-sm">{{ description }}</p>
          </div>
          <button type="button" @click="close" aria-label="Cerrar ventana" class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg text-xl font-bold text-slate-500 hover:bg-slate-100 hover:text-slate-900 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500">×</button>
        </header>

        <div class="min-h-0 flex-1 overflow-y-auto p-4 sm:p-6">
          <slot />
        </div>
      </section>
    </div>
  </Teleport>
</template>
