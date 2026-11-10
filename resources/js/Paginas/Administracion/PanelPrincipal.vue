<script setup>
import { computed } from 'vue';
import AdminLayout from '../../Plantillas/PlantillaAdministracion.vue';
import AnimatedValue from '../../Componentes/ValorAnimado.vue';
import SalesTrendChart from '../../Componentes/GraficaVentas.vue';
import UiIcon from '../../Componentes/Icono.vue';
import { formatCurrency } from '../../utilidades/moneda.js';

const props = defineProps({
  today: { type: String, required: true },
  period: { type: Object, required: true },
  summary: { type: Object, required: true },
  weekSummary: { type: Object, required: true },
  paymentBreakdown: { type: Array, default: () => [] },
  productSales: { type: Object, required: true },
  recentSales: { type: Array, default: () => [] },
  dailySales: { type: Array, default: () => [] },
  finanzas: { type: Object, required: true },
});

const todayLabel = computed(() => new Date(`${props.today}T00:00:00`).toLocaleDateString('es-BO', {
  dateStyle: 'full',
}));

const paymentMethodLabel = (method) => ({
  cash: 'Efectivo',
  qr: 'QR',
}[method] || 'Otro');

const weekTotal = computed(() => Number(props.weekSummary.total) || 0);
const balanceHoy = computed(() => Number(props.finanzas.balance_hoy) || 0);
const weekExpenses = computed(() => props.dailySales.reduce(
  (total, day) => total + (Number(day.gastos) || 0),
  0,
));
const weekProfit = computed(() => weekTotal.value - weekExpenses.value);

const previousWeekTotal = computed(() => props.dailySales.reduce(
  (total, day) => total + (Number(day.previous_total) || 0),
  0,
));

const bestDay = computed(() => props.dailySales.reduce(
  (best, day) => Number(day.total) > Number(best?.total ?? -1) ? day : best,
  null,
));

const lowestDay = computed(() => props.dailySales.reduce(
  (lowest, day) => Number(day.total) < Number(lowest?.total ?? Number.POSITIVE_INFINITY) ? day : lowest,
  null,
));

const saleDate = (date) => new Date(date).toLocaleString('es-BO', {
  dateStyle: 'short',
  timeStyle: 'short',
});
</script>

<template>
  <AdminLayout title="Panel principal" description="Resumen de ventas y caja">
    <div class="mx-auto max-w-7xl space-y-6">
      <section class="flex flex-wrap items-end justify-between gap-3">
        <div>
          <p class="text-xs font-extrabold uppercase tracking-[0.16em] text-primary-700">Dashboard</p>
          <h1 class="mt-1 text-2xl font-black tracking-tight text-slate-900 sm:text-3xl">Resumen de Pollos Misme</h1>
          <p class="mt-2 text-sm capitalize text-slate-600">{{ todayLabel }}</p>
        </div>
        <p class="text-xs font-semibold text-slate-500">Últimos 7 días: {{ period.from }} — {{ period.to }}</p>
      </section>

      <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3" aria-label="Indicadores de ventas de hoy">
        <article class="dashboard-kpi rounded-2xl border border-slate-200 bg-white p-5 shadow-sm" style="animation-delay: 40ms">
          <div class="flex items-center justify-between gap-3">
            <p class="text-sm font-semibold text-slate-500">Ingresos de hoy</p>
            <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-primary-50 text-primary-700"><UiIcon name="chart" :size="19" /></span>
          </div>
          <p class="mt-3 text-2xl font-black text-slate-900"><AnimatedValue :value="summary.total" format="currency" /></p>
        </article>
        <article class="dashboard-kpi rounded-2xl border border-slate-200 bg-white p-5 shadow-sm" style="animation-delay: 100ms">
          <div class="flex items-center justify-between gap-3">
            <p class="text-sm font-semibold text-slate-500">Ventas de hoy</p>
            <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-success-50 text-success-700"><UiIcon name="receipt" :size="19" /></span>
          </div>
          <p class="mt-3 text-2xl font-black text-slate-900"><AnimatedValue :value="summary.tickets" /></p>
        </article>
        <article class="dashboard-kpi rounded-2xl border border-slate-200 bg-white p-5 shadow-sm" style="animation-delay: 160ms">
          <div class="flex items-center justify-between gap-3">
            <p class="text-sm font-semibold text-slate-500">Efectivo</p>
            <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-amber-50 text-amber-800"><UiIcon name="cash" :size="19" /></span>
          </div>
          <p class="mt-3 text-2xl font-black text-slate-900"><AnimatedValue :value="summary.cash" format="currency" /></p>
        </article>
        <article class="dashboard-kpi rounded-2xl border border-slate-200 bg-white p-5 shadow-sm" style="animation-delay: 220ms">
          <div class="flex items-center justify-between gap-3">
            <p class="text-sm font-semibold text-slate-500">QR</p>
            <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-violet-50 text-violet-700"><UiIcon name="qr" :size="19" /></span>
          </div>
          <p class="mt-3 text-2xl font-black text-slate-900"><AnimatedValue :value="summary.qr" format="currency" /></p>
        </article>
        <article class="dashboard-kpi rounded-2xl border border-slate-200 bg-white p-5 shadow-sm" style="animation-delay: 280ms">
          <div class="flex items-center justify-between gap-3">
            <p class="text-sm font-semibold text-slate-500">Gastos de hoy</p>
            <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-rose-50 text-rose-700"><UiIcon name="wallet" :size="19" /></span>
          </div>
          <p class="mt-3 text-2xl font-black text-rose-700"><AnimatedValue :value="finanzas.gastos_hoy" format="currency" /></p>
        </article>
        <article class="dashboard-kpi rounded-2xl border border-slate-200 bg-white p-5 shadow-sm" style="animation-delay: 340ms">
          <div class="flex items-center justify-between gap-3">
            <p class="text-sm font-semibold text-slate-500">Ganancia de hoy</p>
            <span :class="['flex h-10 w-10 items-center justify-center rounded-xl', balanceHoy >= 0 ? 'bg-success-50 text-success-700' : 'bg-rose-50 text-rose-700']"><UiIcon name="chart" :size="19" /></span>
          </div>
          <p :class="['mt-3 text-2xl font-black', balanceHoy >= 0 ? 'text-success-700' : 'text-rose-700']"><AnimatedValue :value="balanceHoy" format="currency" /></p>
          <p class="mt-1 text-xs text-slate-500">ventas menos gastos</p>
        </article>
      </section>

      <section class="dashboard-panel rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6" style="animation-delay: 280ms" aria-label="Ventas de los últimos siete días">
        <div class="mb-5 flex flex-wrap items-end justify-between gap-4">
          <div>
            <h2 class="font-extrabold text-slate-900">Ventas de los últimos 7 días</h2>
            <p class="mt-1 text-xs text-slate-500">Esta semana frente a la semana anterior</p>
          </div>
          <div class="flex flex-wrap gap-5">
            <div>
              <p class="text-[10px] font-extrabold uppercase tracking-[0.14em] text-[var(--chart-current)]">Esta semana</p>
              <p class="mt-0.5 text-lg font-black text-slate-900"><AnimatedValue :value="weekTotal" format="currency" /></p>
            </div>
            <div>
              <p class="text-[10px] font-extrabold uppercase tracking-[0.14em] text-[var(--chart-previous)]">Semana anterior</p>
              <p class="mt-0.5 text-lg font-black text-slate-900"><AnimatedValue :value="previousWeekTotal" format="currency" /></p>
            </div>
          </div>
        </div>

        <SalesTrendChart :days="dailySales" />

        <div v-if="weekTotal > 0" class="mt-4 grid gap-3 sm:grid-cols-2">
          <div class="rounded-xl border border-success-200 bg-success-50 p-3">
            <p class="text-[10px] font-extrabold uppercase tracking-[0.14em] text-success-700">Mejor día</p>
            <p class="mt-1 text-sm font-bold text-slate-900">{{ bestDay?.label }}</p>
            <p class="text-xs text-slate-500">
              {{ bestDay ? formatCurrency(bestDay.total) : '—' }}
              <span v-if="bestDay"> · {{ bestDay.tickets }} ventas</span>
            </p>
          </div>
          <div class="rounded-xl border border-rose-200 bg-rose-50 p-3">
            <p class="text-[10px] font-extrabold uppercase tracking-[0.14em] text-rose-700">Día más bajo</p>
            <p class="mt-1 text-sm font-bold text-slate-900">{{ lowestDay?.label }}</p>
            <p class="text-xs text-slate-500">
              {{ lowestDay ? formatCurrency(lowestDay.total) : '—' }}
              <span v-if="lowestDay"> · {{ lowestDay.tickets }} ventas</span>
            </p>
          </div>
        </div>

        <div v-else class="mt-4 rounded-xl bg-slate-50 p-4 text-center text-sm text-slate-500">
          No hay ventas registradas en los últimos 7 días.
        </div>
      </section>

      <section class="dashboard-panel rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6" style="animation-delay: 360ms" aria-label="Gastos y ganancia de los últimos siete días">
        <div class="mb-5 flex flex-wrap items-end justify-between gap-4">
          <div>
            <h2 class="font-extrabold text-slate-900">Gastos y ganancia de los últimos 7 días</h2>
            <p class="mt-1 text-xs text-slate-500">Salidas frente a la ganancia diaria</p>
          </div>
          <div class="flex flex-wrap gap-5">
            <div>
              <p class="text-[10px] font-extrabold uppercase tracking-[0.14em] text-rose-600">Gastos</p>
              <p class="mt-0.5 text-lg font-black text-rose-700"><AnimatedValue :value="weekExpenses" format="currency" /></p>
            </div>
            <div>
              <p class="text-[10px] font-extrabold uppercase tracking-[0.14em] text-success-600">Ganancia</p>
              <p :class="['mt-0.5 text-lg font-black', weekProfit >= 0 ? 'text-success-700' : 'text-rose-700']"><AnimatedValue :value="weekProfit" format="currency" /></p>
            </div>
          </div>
        </div>

        <SalesTrendChart :days="dailySales" mode="finanzas" :height="240" />
      </section>

      <section class="dashboard-panel overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm" style="animation-delay: 400ms">
        <header class="border-b border-slate-200 px-5 py-4">
          <h2 class="font-extrabold text-slate-900">Últimas ventas</h2>
          <p class="mt-1 text-xs text-slate-500">Las seis transacciones más recientes</p>
        </header>
        <div v-if="recentSales.length" class="overflow-x-auto">
          <table class="w-full min-w-[38rem] text-left text-sm">
            <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
              <tr>
                <th class="px-5 py-3 font-bold">Ticket</th>
                <th class="px-5 py-3 font-bold">Fecha</th>
                <th class="px-5 py-3 font-bold">Método</th>
                <th class="px-5 py-3 text-right font-bold">Total</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
              <tr v-for="(sale, index) in recentSales" :key="sale.id" class="dashboard-row" :style="{ animationDelay: `${500 + index * 55}ms` }">
                <td class="px-5 py-3 font-mono text-xs font-bold text-slate-800">{{ sale.ticket_number }}</td>
                <td class="px-5 py-3 text-slate-600">{{ saleDate(sale.created_at) }}</td>
                <td class="px-5 py-3 text-slate-600">{{ paymentMethodLabel(sale.payment_method) }}</td>
                <td class="px-5 py-3 text-right font-extrabold text-slate-900">{{ formatCurrency(sale.total) }}</td>
              </tr>
            </tbody>
          </table>
        </div>
        <p v-else class="px-5 py-10 text-center text-sm text-slate-500">No hay ventas registradas todavía.</p>
      </section>

      <p class="text-center text-xs text-slate-500">Los importes muestran ventas cobradas; no representan utilidad porque no se registran costos ni gastos.</p>
    </div>
  </AdminLayout>
</template>
