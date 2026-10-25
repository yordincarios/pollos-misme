<script setup>
import { onMounted } from 'vue';
import { Head } from '@inertiajs/vue3';
import { formatCurrency } from '../../utils/currency.js';

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

const itemName = (item) => item.dish_name || item.dish?.name || 'Plato no disponible';
const printReport = () => window.print();
const closePrintWindow = () => window.close();

onMounted(() => {
  window.print();
});
</script>

<template>
  <Head title="Imprimir registros de ventas" />

  <main data-print-accounting class="mx-auto max-w-5xl space-y-6 bg-white p-6 text-slate-900 sm:p-10">
    <header class="flex items-start justify-between gap-6 border-b-2 border-slate-900 pb-4">
      <div>
        <p class="text-xs font-bold uppercase tracking-[0.16em]">Restaurante 2 · Contabilidad</p>
        <h1 class="mt-1 text-2xl font-black">Registro detallado de ventas</h1>
        <p class="mt-2 text-sm">Período: {{ filters.from }} al {{ filters.to }}</p>
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

    <section v-for="sale in sales" :key="sale.id" data-print-accounting-sale class="space-y-3 border-b border-slate-300 pb-5">
      <header class="flex flex-wrap items-end justify-between gap-x-6 gap-y-2">
        <div>
          <h2 class="font-mono text-sm font-black">Ticket {{ sale.ticket_number }}</h2>
          <p class="text-xs text-slate-600">{{ formatDate(sale.created_at) }} · {{ paymentMethodLabel(sale.payment_method) }}</p>
        </div>
        <p class="text-sm font-black">Total: {{ formatCurrency(sale.total) }}</p>
      </header>

      <table class="w-full border-collapse text-xs">
        <thead>
          <tr class="border-y border-slate-300 text-left text-slate-600">
            <th class="py-2 pr-3 font-bold">Plato</th>
            <th class="px-2 py-2 text-right font-bold">Cant.</th>
            <th class="px-2 py-2 text-right font-bold">Precio unitario</th>
            <th class="py-2 pl-2 text-right font-bold">Subtotal</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="item in sale.items" :key="item.id" class="border-b border-slate-100">
            <td class="py-2 pr-3">
              <span class="font-semibold">{{ itemName(item) }}</span>
              <span v-if="item.notes" class="block text-slate-500">Nota: {{ item.notes }}</span>
            </td>
            <td class="px-2 py-2 text-right">{{ item.quantity }}</td>
            <td class="px-2 py-2 text-right">{{ formatCurrency(item.price) }}</td>
            <td class="py-2 pl-2 text-right font-semibold">{{ formatCurrency(item.subtotal) }}</td>
          </tr>
          <tr v-if="!sale.items.length">
            <td colspan="4" class="py-2 text-center text-slate-500">Este ticket no tiene detalle de platos.</td>
          </tr>
        </tbody>
      </table>
    </section>

    <footer class="text-right text-[10px] text-slate-500">Reporte generado el {{ new Date().toLocaleString('es-BO') }}</footer>
  </main>
</template>
