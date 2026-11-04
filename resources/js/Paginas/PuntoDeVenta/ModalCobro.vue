<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { formatCurrency } from '../../utilidades/moneda.js';
import UiIcon from '../../Componentes/Icono.vue';

const props = defineProps({
  open: { type: Boolean, default: false },
  total: { type: Number, required: true },
  processing: { type: Boolean, default: false },
  error: { type: String, default: '' },
});

const emit = defineEmits(['close', 'confirm']);

const paymentMethod = ref(null);
const qrConfirmed = ref(false);
const qrCheckbox = ref(null);
const confirmButton = ref(null);
const dialogPanel = ref(null);

watch(() => props.open, async (isOpen) => {
  if (isOpen) {
    paymentMethod.value = 'cash';
    qrConfirmed.value = false;
    await nextTick();
    confirmButton.value?.focus();
  }
});

watch(paymentMethod, async (method) => {
  if (method === 'cash') {
    await nextTick();
    confirmButton.value?.focus();
  }

  if (method === 'qr') {
    await nextTick();
    qrCheckbox.value?.focus();
  }
});

const handleKeydown = (event) => {
  if (!props.open) {
    return;
  }

  if (event.key === 'Escape') {
    event.preventDefault();
    close();

    return;
  }

  if (event.altKey && event.key === '1') {
    event.preventDefault();
    paymentMethod.value = 'cash';

    return;
  }

  if (event.altKey && event.key === '2') {
    event.preventDefault();
    paymentMethod.value = 'qr';

    return;
  }

  if ((event.ctrlKey || event.metaKey) && event.key === 'Enter') {
    event.preventDefault();
    confirm();

    return;
  }

  if (event.key !== 'Tab' || !dialogPanel.value) {
    return;
  }

  const focusableElements = [...dialogPanel.value.querySelectorAll(
    'button:not([disabled]), input:not([disabled]), [href], select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])',
  )];

  if (focusableElements.length === 0) {
    event.preventDefault();
    dialogPanel.value.focus();

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

onMounted(() => window.addEventListener('keydown', handleKeydown));
onBeforeUnmount(() => window.removeEventListener('keydown', handleKeydown));

const canConfirm = computed(() => {
  if (props.processing || props.total <= 0) {
    return false;
  }

  if (paymentMethod.value === 'cash') {
    return true;
  }

  if (paymentMethod.value === 'qr') {
    return qrConfirmed.value;
  }

  return false;
});

const confirmLabel = computed(() => {
  if (props.processing) {
    return 'Registrando…';
  }

  return paymentMethod.value === 'qr'
    ? 'Confirmar pago QR'
    : `Confirmar cobro · ${formatCurrency(props.total)}`;
});

const close = () => {
  if (!props.processing) {
    emit('close');
  }
};

const confirm = () => {
  if (!canConfirm.value) {
    return;
  }

  emit('confirm', {
    paymentMethod: paymentMethod.value,
    qrConfirmed: qrConfirmed.value,
  });
};
</script>

<template>
  <Teleport to="body">
    <div
      v-if="open"
      class="pos-modal-backdrop fixed inset-0 z-[110] flex items-center justify-center bg-slate-950/70 p-3 backdrop-blur-sm"
      role="dialog"
      aria-modal="true"
      aria-labelledby="checkout-title"
      @mousedown.self.prevent
    >
      <section ref="dialogPanel" tabindex="-1" class="pos-modal-panel max-h-[calc(100dvh-1.5rem)] w-full max-w-lg overflow-y-auto rounded-2xl border border-slate-200 bg-white shadow-2xl focus:outline-none">
        <header class="flex items-start justify-between gap-4 border-b border-slate-200 px-5 py-4">
          <div>
            <p class="text-xs font-extrabold uppercase tracking-wide text-primary-700">Venta actual</p>
            <h2 id="checkout-title" class="mt-1 text-xl font-black text-slate-900">Cobrar</h2>
            <p class="mt-1 text-sm text-slate-600">Elige cómo pagó el cliente y confirma el importe.</p>
          </div>
          <button type="button" @click="close" :disabled="processing" aria-label="Cerrar cobro" class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg text-xl font-bold text-slate-500 hover:bg-slate-100 disabled:opacity-40">×</button>
        </header>

        <div class="space-y-5 p-5">
          <div class="flex items-center justify-between rounded-xl bg-slate-50 px-4 py-3">
            <span class="text-sm font-bold text-slate-600">Total a cobrar</span>
            <span class="text-2xl font-black text-slate-900">{{ formatCurrency(total) }}</span>
          </div>

          <div>
            <p class="mb-2 text-xs font-extrabold uppercase tracking-wide text-slate-500">Método de pago</p>
            <div class="grid grid-cols-2 gap-3" role="group" aria-label="Método de pago">
              <button
                type="button"
                :aria-pressed="paymentMethod === 'cash'"
                aria-keyshortcuts="Alt+1"
                :disabled="processing"
                :class="[
                  'flex min-h-20 flex-col items-center justify-center gap-1 rounded-xl border-2 px-3 py-3 text-sm font-extrabold transition',
                  paymentMethod === 'cash' ? 'border-primary-600 bg-primary-50 text-primary-800' : 'border-slate-200 text-slate-600 hover:border-primary-300 hover:bg-primary-50/50',
                ]"
                @click="paymentMethod = 'cash'"
              >
                <UiIcon name="cash" :size="24" />
                <span>Efectivo</span>
                <span :class="['text-[10px] font-semibold', paymentMethod === 'cash' ? 'text-primary-700' : 'text-slate-500']">Cobro en caja</span>
              </button>
              <button
                type="button"
                :aria-pressed="paymentMethod === 'qr'"
                aria-keyshortcuts="Alt+2"
                :disabled="processing"
                :class="[
                  'flex min-h-20 flex-col items-center justify-center gap-1 rounded-xl border-2 px-3 py-3 text-sm font-extrabold transition',
                  paymentMethod === 'qr' ? 'border-primary-600 bg-primary-50 text-primary-800' : 'border-slate-200 text-slate-600 hover:border-primary-300 hover:bg-primary-50/50',
                ]"
                @click="paymentMethod = 'qr'"
              >
                <UiIcon name="qr" :size="24" />
                <span>QR</span>
                <span :class="['text-[10px] font-semibold', paymentMethod === 'qr' ? 'text-primary-700' : 'text-slate-500']">Verificación manual</span>
              </button>
            </div>
          </div>

          <div v-if="paymentMethod === 'cash'" class="rounded-xl border border-primary-200 bg-primary-50 p-4">
            <div class="flex items-center gap-2 text-sm font-extrabold text-primary-900">
              <UiIcon name="cash" :size="18" />
              Pago en efectivo
            </div>
            <p class="mt-1.5 text-xs leading-relaxed text-primary-800">
              Cobra <strong>{{ formatCurrency(total) }}</strong> al cliente. El cambio se entrega directamente en caja.
            </p>
          </div>

          <div v-if="paymentMethod === 'qr'" class="space-y-4">
            <div class="flex items-start gap-3 rounded-xl border border-slate-200 bg-slate-50 p-4 text-sm leading-relaxed text-slate-700">
              <UiIcon name="qr" class="mt-0.5 shrink-0 text-primary-700" :size="20" />
              <p>El cliente paga <strong>{{ formatCurrency(total) }}</strong> por QR fuera del sistema. Verifica el abono en tu aplicación bancaria antes de confirmar.</p>
            </div>
            <label class="flex cursor-pointer items-start gap-3 rounded-xl border border-success-200 bg-success-50 p-4 text-sm font-semibold text-success-900">
              <input ref="qrCheckbox" v-model="qrConfirmed" type="checkbox" class="mt-0.5 h-5 w-5 shrink-0 rounded border-success-300 text-success-600 focus:ring-success-500" />
              <span>Ya verifiqué el pago en la aplicación bancaria.</span>
            </label>
          </div>

          <p v-if="error" class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-semibold text-rose-800" role="alert">{{ error }}</p>
        </div>

        <footer class="flex justify-end gap-2 border-t border-slate-200 bg-slate-50 px-5 py-4">
          <button type="button" @click="close" :disabled="processing" class="rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-sm font-bold text-slate-700 hover:bg-slate-100 disabled:opacity-50">Cancelar</button>
          <button ref="confirmButton" type="button" @click="confirm" :disabled="!canConfirm" aria-keyshortcuts="Control+Enter" class="inline-flex min-h-11 items-center gap-2 rounded-xl bg-primary-700 px-5 py-2.5 text-sm font-bold text-white hover:bg-primary-800 disabled:cursor-not-allowed disabled:opacity-50">
            <svg v-if="processing" class="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24">
              <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
              <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
            </svg>
            {{ confirmLabel }}
          </button>
        </footer>
      </section>
    </div>
  </Teleport>
</template>
