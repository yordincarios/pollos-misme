<script setup>
import { computed } from 'vue';
import { formatCurrency } from '../../utils/currency.js';
import UiIcon from '../../Components/UiIcon.vue';

const props = defineProps({
  sale: {
    type: Object,
    default: null,
  },
});

const emit = defineEmits(['close']);
const paymentMethodLabel = computed(() => ({
  cash: 'Efectivo',
  qr: 'QR',
  card: 'Tarjeta',
  transfer: 'Transferencia',
}[props.sale?.payment_method] || '—'));

const printReceipt = () => {
  window.print();
};
</script>

<template>
  <div v-if="sale" data-print-receipt class="fixed inset-0 z-50 overflow-y-auto" role="dialog" aria-modal="true" aria-labelledby="receipt-title">
    <div data-print-receipt-backdrop class="fixed inset-0 bg-slate-900/60 backdrop-blur-xs transition-opacity print:hidden" @click="emit('close')"></div>

    <div data-print-receipt-container class="flex min-h-full items-center justify-center p-4">
      <div 
        data-print-receipt-card
        class="relative w-full max-w-md transform overflow-hidden rounded-2xl bg-white p-6 shadow-2xl transition-all border border-slate-100 print:shadow-none print:border-none print:w-full print:max-w-none"
        @click.stop
      >
        <!-- Success Badge -->
        <div class="text-center pb-4 border-b border-dashed border-slate-200">
          <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-emerald-100 text-emerald-600 mb-2">
            <svg class="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7" />
            </svg>
          </div>
          <h3 id="receipt-title" class="text-xl font-black text-slate-900">¡Venta Exitosa!</h3>
          <p class="text-xs text-slate-500 font-mono tracking-wider mt-0.5">Ticket #{{ sale.ticket_number }}</p>
          <p class="text-[11px] text-slate-400 mt-0.5">{{ new Date(sale.created_at || Date.now()).toLocaleString() }}</p>
        </div>

        <!-- Receipt Items -->
        <div data-print-receipt-items class="py-4 space-y-2 border-b border-dashed border-slate-200 max-h-56 overflow-y-auto">
          <div 
            v-for="item in sale.items" 
            :key="item.id"
            class="text-xs space-y-0.5"
          >
            <div class="flex justify-between font-bold text-slate-800">
              <span>{{ item.quantity }}x {{ item.dish_name || item.dish?.name || 'Platillo' }}</span>
              <span>{{ formatCurrency(item.subtotal) }}</span>
            </div>
            <div class="flex justify-between text-slate-500 text-[11px]">
              <span>Precio unitario: {{ formatCurrency(item.price) }}</span>
              <span v-if="item.notes" class="text-amber-700 italic font-medium bg-amber-50 px-1.5 rounded">"{{ item.notes }}"</span>
            </div>
          </div>
        </div>

        <!-- Totals -->
        <div class="py-4 space-y-2 border-b border-slate-200 text-sm">
          <div class="flex justify-between font-bold text-slate-600">
            <span>Método de pago:</span>
            <span class="uppercase font-semibold text-slate-800">{{ paymentMethodLabel }}</span>
          </div>
          <div class="flex justify-between items-center text-lg font-black text-slate-900">
            <span>TOTAL:</span>
            <span class="text-2xl text-emerald-600">{{ formatCurrency(sale.total) }}</span>
          </div>
          <div v-if="sale.payment_method === 'cash'" class="flex justify-between text-slate-600 font-medium">
            <span>Efectivo Recibido:</span>
            <span class="font-bold text-slate-800">{{ formatCurrency(sale.cash_received) }}</span>
          </div>
          <div v-if="sale.payment_method === 'cash'" class="flex justify-between text-slate-900 font-black text-base bg-emerald-50 p-2 rounded-xl text-emerald-800">
            <span>Cambio Entregado:</span>
            <span>{{ formatCurrency(sale.change_given) }}</span>
          </div>
        </div>

        <!-- Actions (Hidden in Print) -->
        <div class="pt-4 flex gap-3 print:hidden">
          <button
            type="button"
            @click="printReceipt"
            class="flex-1 py-3 px-4 rounded-xl border border-slate-300 font-bold text-slate-700 hover:bg-slate-100 transition flex items-center justify-center gap-2 text-sm shadow-xs"
          >
            <UiIcon name="printer" :size="18" /> Imprimir
          </button>
          <button
            type="button"
            @click="emit('close')"
            class="flex-1 py-3 px-4 rounded-xl bg-emerald-600 font-bold text-white hover:bg-emerald-700 shadow-md transition text-sm"
          >
            Nueva Venta
          </button>
        </div>
      </div>
    </div>
  </div>
</template>
