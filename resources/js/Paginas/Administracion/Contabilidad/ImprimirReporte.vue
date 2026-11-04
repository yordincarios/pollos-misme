<script setup>
import { onMounted } from 'vue';
import { Head } from '@inertiajs/vue3';
import { formatCurrency } from '../../../utilidades/moneda.js';

defineProps({
  filters: { type: Object, required: true },
  summary: { type: Object, required: true },
  sales: { type: Array, required: true },
});

const paymentMethodLabel = (method) => ({
  cash: 'Efectivo',
  qr: 'QR',
  card: 'Tarjeta (anterior)',
  transfer: 'Transferencia (anterior)',
}[method] || 'Otro');

const formatDate = (date) => new Date(date).toLocaleString('es-BO', {
  dateStyle: 'short',
  timeStyle: 'short',
});

const printReport = () => window.print();
const closePrintWindow = () => window.close();

onMounted(() => {
  window.print();
});
</script>

<template>
  <Head title="Imprimir reporte de ventas" />

  <main data-print-accounting class="mx-auto max-w-5xl space-y-6 bg-white p-6 text-slate-900 sm:p-10">
    <header class="flex items-start justify-between gap-6 border-b-2 border-slate-900 pb-4">
      <div>
        <p class="brand-wordmark text-lg">Pollos <span class="brand-wordmark-accent">Misme</span></p>
        <p class="text-xs font-bold uppercase tracking-[0.16em]">Contabilidad</p>
        <h1 class="mt-1 text-2xl font-black">Reporte resumido de ventas</h1>
        <p class="mt-2 text-sm">Período: {{ filters.from }} al {{ filters.to }}</p>
        <p class="mt-1 text-xs text-slate-500">Una fila por venta. El detalle de platos está disponible en el CSV.</p>
      </div>
      <div class="print-hidden flex gap-2">
        <button type="button" @click="printReport" class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-bold text-white">Imprimir</button>
        <button type="button" @click="closePrintWindow" class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-bold text-slate-700">Cerrar</button>
      </div>
    </header>

    <section class="grid grid-cols-2 gap-3 border-b border-slate-300 pb-5 sm:grid-cols-5">
      <div>
        <p class="text-xs text-slate-500">Total vendido</p>
        <p class="font-black">{{ formatCurrency(summary.total) }}</p>
      </div>
      <div>
        <p class="text-xs text-slate-500">Tickets</p>
        <p class="font-black">{{ summary.tickets }}</p>
      </div>
      <div>
        <p class="text-xs text-slate-500">Efectivo</p>
        <p class="font-black">{{ formatCurrency(summary.cash) }}</p>
      </div>
      <div>
        <p class="text-xs text-slate-500">QR</p>
        <p class="font-black">{{ formatCurrency(summary.qr) }}</p>
      </div>
      <div>
        <p class="text-xs text-slate-500">Otros métodos</p>
        <p class="font-black">{{ formatCurrency(summary.other) }}</p>
      </div>
    </section>

    <p v-if="!sales.length" class="py-10 text-center text-sm text-slate-500">No hay ventas en este período.</p>

    <table v-else class="w-full border-collapse text-xs">
      <thead>
        <tr class="border-y border-slate-300 bg-slate-50 text-left text-slate-600">
          <th class="py-2 pr-3 font-bold">Ticket</th>
          <th class="px-2 py-2 font-bold">Fecha y hora</th>
          <th class="px-2 py-2 font-bold">Método</th>
          <th class="px-2 py-2 text-right font-bold">Ítems</th>
          <th class="py-2 pl-2 text-right font-bold">Total</th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="sale in sales" :key="sale.id" data-print-accounting-sale class="border-b border-slate-200">
          <td class="py-2 pr-3 font-mono font-semibold">{{ sale.ticket_number }}</td>
          <td class="px-2 py-2 text-slate-600">{{ formatDate(sale.created_at) }}</td>
          <td class="px-2 py-2 text-slate-600">{{ paymentMethodLabel(sale.payment_method) }}</td>
          <td class="px-2 py-2 text-right text-slate-600">{{ sale.items_count }}</td>
          <td class="py-2 pl-2 text-right font-bold">{{ formatCurrency(sale.total) }}</td>
        </tr>
      </tbody>
      <tfoot>
        <tr class="border-t-2 border-slate-900 font-black">
          <td colspan="4" class="py-3 pr-3">Total del período</td>
          <td class="py-3 pl-2 text-right">{{ formatCurrency(summary.total) }}</td>
        </tr>
      </tfoot>
    </table>

    <footer class="flex flex-wrap items-center justify-between gap-2 border-t border-slate-200 pt-3 text-[10px] text-slate-500">
      <span>Reporte resumido generado el {{ new Date().toLocaleString('es-BO') }}.</span>
      <span>Para el detalle por plato, exporta el CSV desde Contabilidad.</span>
    </footer>
  </main>
</template>
