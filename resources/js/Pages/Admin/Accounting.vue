<script setup>
import { computed, ref } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import AdminLayout from '../../Layouts/AdminLayout.vue';
import UiIcon from '../../Components/UiIcon.vue';
import { formatCurrency } from '../../utils/currency.js';

const props = defineProps({
  filters: { type: Object, required: true },
  today: { type: String, required: true },
  summary: { type: Object, required: true },
  paymentBreakdown: { type: Array, default: () => [] },
  sales: { type: Object, required: true },
});

const filtersForm = useForm({
  from: props.filters.from,
  to: props.filters.to,
});

const expandedSaleIds = ref([]);
const printUrl = computed(() => `/admin/accounting/print?${new URLSearchParams(props.filters).toString()}`);

const applyFilters = () => {
  filtersForm.get('/admin/accounting', {
    preserveScroll: true,
    preserveState: true,
    replace: true,
  });
};

const paymentMethodLabel = (method) => ({
  cash: 'Efectivo',
  qr: 'QR',
  card: 'Tarjeta (anterior)',
  transfer: 'Transferencia (anterior)',
}[method] || 'Otro');

const saleDate = (date) => new Date(date).toLocaleString('es-BO', {
  dateStyle: 'short',
  timeStyle: 'short',
});

const goToPage = (url) => {
  if (url) {
    router.visit(url, { preserveScroll: true, preserveState: true });
  }
};

const toggleSaleDetails = (saleId) => {
  expandedSaleIds.value = expandedSaleIds.value.includes(saleId)
    ? expandedSaleIds.value.filter((id) => id !== saleId)
    : [...expandedSaleIds.value, saleId];
};

const saleItemName = (item) => item.dish_name || item.dish?.name || 'Plato no disponible';

const saleItemSummary = (items) => items.length
  ? items.map((item) => `${saleItemName(item)} ×${item.quantity}`).join(', ')
  : 'Detalle de platos no disponible';

const dateInputValue = (date) => [
  date.getFullYear(),
  String(date.getMonth() + 1).padStart(2, '0'),
  String(date.getDate()).padStart(2, '0'),
].join('-');

const setPeriodPreset = (preset) => {
  const [year, month, day] = props.today.split('-').map(Number);
  const today = new Date(year, month - 1, day);
  const from = new Date(today);
  const to = new Date(today);

  if (preset === 'yesterday') {
    from.setDate(from.getDate() - 1);
    to.setDate(to.getDate() - 1);
  } else if (preset === 'last-seven-days') {
    from.setDate(from.getDate() - 6);
  }

  filtersForm.from = dateInputValue(from);
  filtersForm.to = dateInputValue(to);
  applyFilters();
};

const paymentPercentage = (method) => props.summary.total > 0
  ? Math.min(100, Math.max(0, (method.total / props.summary.total) * 100))
  : 0;
</script>

<template>
  <AdminLayout title="Ventas y caja" description="Reporte de ventas registradas">
    <div class="mx-auto max-w-7xl space-y-6">
      <section class="flex flex-wrap items-end justify-between gap-4">
        <div>
          <p class="text-xs font-extrabold uppercase tracking-[0.16em] text-emerald-700">Contabilidad</p>
          <h1 class="mt-1 text-2xl font-black tracking-tight text-slate-900 sm:text-3xl">Ventas y caja</h1>
          <p class="mt-2 text-sm text-slate-600">Resumen de ventas cobradas; no incluye costos ni gastos.</p>
        </div>

        <div class="flex flex-wrap items-end gap-3">
          <div class="flex flex-wrap gap-2" role="group" aria-label="Períodos rápidos">
            <button type="button" @click="setPeriodPreset('today')" class="min-h-10 rounded-lg border border-slate-300 bg-white px-3 text-xs font-bold text-slate-700 transition hover:bg-slate-100">Hoy</button>
            <button type="button" @click="setPeriodPreset('yesterday')" class="min-h-10 rounded-lg border border-slate-300 bg-white px-3 text-xs font-bold text-slate-700 transition hover:bg-slate-100">Ayer</button>
            <button type="button" @click="setPeriodPreset('last-seven-days')" class="min-h-10 rounded-lg border border-slate-300 bg-white px-3 text-xs font-bold text-slate-700 transition hover:bg-slate-100">7 días</button>
          </div>
          <form class="flex flex-wrap items-end gap-3" @submit.prevent="applyFilters">
            <label class="grid gap-1 text-xs font-bold text-slate-600">
              Desde
              <input v-model="filtersForm.from" type="date" required class="min-h-10 rounded-lg border-slate-300 bg-white px-3 text-sm text-slate-800 focus:border-emerald-500 focus:ring-emerald-500" />
            </label>
            <label class="grid gap-1 text-xs font-bold text-slate-600">
              Hasta
              <input v-model="filtersForm.to" type="date" required :min="filtersForm.from" class="min-h-10 rounded-lg border-slate-300 bg-white px-3 text-sm text-slate-800 focus:border-emerald-500 focus:ring-emerald-500" />
            </label>
            <button type="submit" :disabled="filtersForm.processing" class="inline-flex min-h-10 items-center gap-2 rounded-lg bg-emerald-600 px-4 text-sm font-bold text-white transition hover:bg-emerald-700 disabled:opacity-60">
              <UiIcon name="search" :size="16" />
              Filtrar
            </button>
          </form>
          <a :href="printUrl" target="_blank" rel="noopener" class="inline-flex min-h-10 items-center gap-2 rounded-lg border border-slate-300 bg-white px-4 text-sm font-bold text-slate-700 no-underline transition hover:bg-slate-50">
            <UiIcon name="printer" :size="16" />
            Imprimir período
          </a>
        </div>
      </section>

      <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4" aria-label="Resumen de caja">
        <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
          <p class="text-sm font-semibold text-slate-500">Total vendido</p>
          <p class="mt-2 text-2xl font-black text-slate-900">{{ formatCurrency(summary.total) }}</p>
        </article>
        <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
          <p class="text-sm font-semibold text-slate-500">Ventas registradas</p>
          <p class="mt-2 text-2xl font-black text-slate-900">{{ summary.tickets }}</p>
        </article>
        <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
          <p class="text-sm font-semibold text-slate-500">Efectivo</p>
          <p class="mt-2 text-2xl font-black text-amber-800">{{ formatCurrency(summary.cash) }}</p>
        </article>
        <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
          <p class="text-sm font-semibold text-slate-500">QR</p>
          <p class="mt-2 text-2xl font-black text-emerald-700">{{ formatCurrency(summary.qr) }}</p>
        </article>
      </section>

      <section class="grid gap-6 xl:grid-cols-[minmax(0,1.5fr)_minmax(18rem,0.7fr)]">
        <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
          <div class="border-b border-slate-200 px-5 py-4">
            <h2 class="font-extrabold text-slate-900">Ventas del período</h2>
            <p class="mt-1 text-xs text-slate-500">{{ sales.total }} {{ sales.total === 1 ? 'venta encontrada' : 'ventas encontradas' }}</p>
          </div>

          <div v-if="sales.data.length" class="overflow-x-auto">
            <table class="w-full min-w-[40rem] text-left text-sm">
              <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                <tr>
                  <th class="px-5 py-3 font-bold">Platos vendidos</th>
                  <th class="px-5 py-3 font-bold">Fecha</th>
                  <th class="px-5 py-3 font-bold">Método</th>
                  <th class="px-5 py-3 text-right font-bold">Total</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-slate-100">
                <tr v-for="sale in sales.data" :key="sale.id" class="hover:bg-slate-100">
                  <td class="px-5 py-3">
                    <p class="max-w-md font-semibold leading-snug text-slate-900">{{ saleItemSummary(sale.items) }}</p>
                    <p class="mt-1 font-mono text-[10px] text-slate-500">Ticket {{ sale.ticket_number }}</p>
                    <button
                      type="button"
                      :aria-expanded="expandedSaleIds.includes(sale.id)"
                      :aria-controls="`sale-details-${sale.id}`"
                      @click="toggleSaleDetails(sale.id)"
                      class="mt-1 text-xs font-bold text-emerald-700 underline decoration-emerald-300 underline-offset-2 hover:text-emerald-900"
                    >
                      {{ expandedSaleIds.includes(sale.id) ? 'Ocultar detalle' : `Ver ${sale.items.length} platos` }}
                    </button>
                  </td>
                  <td class="px-5 py-3 text-slate-600">{{ saleDate(sale.created_at) }}</td>
                  <td class="px-5 py-3 text-slate-600">{{ paymentMethodLabel(sale.payment_method) }}</td>
                  <td class="px-5 py-3 text-right font-extrabold text-slate-900">{{ formatCurrency(sale.total) }}</td>
                </tr>
                <tr v-for="sale in sales.data.filter((record) => expandedSaleIds.includes(record.id))" :key="`details-${sale.id}`">
                  <td :id="`sale-details-${sale.id}`" colspan="4" class="bg-slate-50 px-5 py-4">
                    <div class="overflow-x-auto rounded-xl border border-slate-200 bg-white">
                      <table class="w-full min-w-[34rem] text-left text-xs">
                        <thead class="bg-slate-100 uppercase tracking-wide text-slate-500">
                          <tr>
                            <th class="px-3 py-2 font-bold">Plato</th>
                            <th class="px-3 py-2 text-right font-bold">Cantidad</th>
                            <th class="px-3 py-2 text-right font-bold">Precio unitario</th>
                            <th class="px-3 py-2 text-right font-bold">Subtotal</th>
                          </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                          <tr v-for="item in sale.items" :key="item.id">
                            <td class="px-3 py-2 font-semibold text-slate-800">
                              {{ saleItemName(item) }}
                              <span v-if="item.notes" class="mt-0.5 block font-normal text-slate-500">Nota: {{ item.notes }}</span>
                            </td>
                            <td class="px-3 py-2 text-right text-slate-600">{{ item.quantity }}</td>
                            <td class="px-3 py-2 text-right text-slate-600">{{ formatCurrency(item.price) }}</td>
                            <td class="px-3 py-2 text-right font-bold text-slate-800">{{ formatCurrency(item.subtotal) }}</td>
                          </tr>
                          <tr v-if="!sale.items.length">
                            <td colspan="4" class="px-3 py-3 text-center text-slate-500">Este ticket no tiene detalle de platos.</td>
                          </tr>
                        </tbody>
                      </table>
                    </div>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
          <div v-else class="px-5 py-10 text-center text-sm text-slate-500">No hay ventas en este período.</div>

          <div v-if="sales.last_page > 1" class="flex items-center justify-between border-t border-slate-200 px-5 py-3">
            <button type="button" :disabled="!sales.prev_page_url" @click="goToPage(sales.prev_page_url)" class="rounded-lg px-3 py-2 text-sm font-bold text-slate-700 hover:bg-slate-100 disabled:cursor-not-allowed disabled:opacity-40">Anterior</button>
            <span class="text-xs font-semibold text-slate-500">Página {{ sales.current_page }} de {{ sales.last_page }}</span>
            <button type="button" :disabled="!sales.next_page_url" @click="goToPage(sales.next_page_url)" class="rounded-lg px-3 py-2 text-sm font-bold text-slate-700 hover:bg-slate-100 disabled:cursor-not-allowed disabled:opacity-40">Siguiente</button>
          </div>
        </div>

        <aside class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
          <div class="mb-4">
            <h2 class="font-extrabold text-slate-900">Desglose por método</h2>
            <p class="mt-1 text-xs text-slate-500">Incluye registros anteriores si existen.</p>
          </div>
          <div v-if="paymentBreakdown.length" class="space-y-3">
            <div v-for="method in paymentBreakdown" :key="method.method" class="flex items-center justify-between gap-3 border-b border-slate-100 pb-3 last:border-0 last:pb-0">
              <div class="min-w-0 flex-1">
                <div class="flex items-center justify-between gap-3">
                  <div>
                    <p class="text-sm font-bold text-slate-700">{{ paymentMethodLabel(method.method) }}</p>
                    <p class="mt-0.5 text-xs text-slate-500">{{ method.tickets }} {{ method.tickets === 1 ? 'venta' : 'ventas' }}</p>
                  </div>
                  <div class="text-right">
                    <p class="text-sm font-extrabold text-slate-900">{{ formatCurrency(method.total) }}</p>
                    <p class="text-xs font-semibold text-slate-500">{{ paymentPercentage(method).toFixed(1) }}%</p>
                  </div>
                </div>
                <div class="mt-2 h-2 overflow-hidden rounded-full bg-slate-100" role="progressbar" :aria-label="`${paymentMethodLabel(method.method)} del total vendido`" :aria-valuenow="paymentPercentage(method).toFixed(1)" aria-valuemin="0" aria-valuemax="100">
                  <div class="h-full rounded-full bg-emerald-600 transition-[width]" :style="{ width: `${paymentPercentage(method)}%` }"></div>
                </div>
              </div>
            </div>
          </div>
          <p v-else class="text-sm text-slate-500">No hay pagos registrados en el período.</p>
        </aside>
      </section>
    </div>
  </AdminLayout>
</template>
