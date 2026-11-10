<script setup>
import { computed, ref } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import AdminLayout from '../../../Plantillas/PlantillaAdministracion.vue';
import AnimatedValue from '../../../Componentes/ValorAnimado.vue';
import UiIcon from '../../../Componentes/Icono.vue';
import { formatCurrency } from '../../../utilidades/moneda.js';

const props = defineProps({
  filters: { type: Object, required: true },
  today: { type: String, required: true },
  summary: { type: Object, required: true },
  paymentBreakdown: { type: Array, default: () => [] },
  topDishes: { type: Array, default: () => [] },
  categorySales: { type: Array, default: () => [] },
  paymentMethods: { type: Array, default: () => [] },
  sales: { type: Object, required: true },
  gastos: { type: Array, default: () => [] },
  gastosResumen: { type: Object, required: true },
});

const filtersForm = useForm({
  from: props.filters.from,
  to: props.filters.to,
  payment_method: props.filters.payment_method || '',
});

const showFilters = ref(false);
const expandedSaleIds = ref([]);
const queryString = computed(() => new URLSearchParams({
  from: props.filters.from,
  to: props.filters.to,
  ...(props.filters.payment_method ? { payment_method: props.filters.payment_method } : {}),
}).toString());
const printUrl = computed(() => `/admin/accounting/print?${queryString.value}`);
const exportUrl = computed(() => `/admin/accounting/export?${queryString.value}`);

const formatFilterDate = (value) => new Date(`${value}T00:00:00`).toLocaleDateString('es-BO', {
  day: '2-digit',
  month: 'short',
  year: 'numeric',
});

const activePeriodLabel = computed(() => props.filters.from === props.filters.to
  ? formatFilterDate(props.filters.from)
  : `${formatFilterDate(props.filters.from)} — ${formatFilterDate(props.filters.to)}`);

const activePaymentMethodLabel = computed(() => props.paymentMethods
  .find((method) => method.value === (props.filters.payment_method || ''))?.label || 'Todos los métodos');

const resetFilterForm = () => {
  filtersForm.from = props.filters.from;
  filtersForm.to = props.filters.to;
  filtersForm.payment_method = props.filters.payment_method || '';
  filtersForm.clearErrors();
};

const toggleFilters = () => {
  if (showFilters.value) {
    showFilters.value = false;
    resetFilterForm();

    return;
  }

  resetFilterForm();
  showFilters.value = true;
};

const applyFilters = () => {
  filtersForm.get('/admin/accounting', {
    preserveScroll: true,
    preserveState: true,
    replace: true,
    onSuccess: () => {
      showFilters.value = false;
    },
  });
};

const resetFilters = () => {
  filtersForm.from = props.today;
  filtersForm.to = props.today;
  filtersForm.payment_method = '';
  applyFilters();
};

const closeActions = (event) => {
  event.currentTarget.closest('details')?.removeAttribute('open');
};

const paymentMethodLabel = (method) => ({
  cash: 'Efectivo',
  qr: 'QR',
  card: 'Tarjeta (anterior)',
  transfer: 'Transferencia (anterior)',
}[method] || 'Otro');

const paymentMethodColor = (method) => ({
  cash: '#d97706',
  qr: '#7c3aed',
}[method] || '#64748b');

const categoriaGastoLabel = (categoria) => ({
  insumos: 'Insumos',
  servicios: 'Servicios',
  personal: 'Personal',
  mantenimiento: 'Mantenimiento',
  otros: 'Otros',
}[categoria] || categoria);

const metodoGastoLabel = (metodo) => ({
  efectivo: 'Efectivo',
  transferencia: 'Transferencia',
  qr: 'QR',
}[metodo] || metodo);

const fechaGasto = (fecha) => new Date(`${fecha}T00:00:00`).toLocaleDateString('es-BO', {
  dateStyle: 'medium',
});

const balancePeriodo = computed(() => Number(props.summary.balance) || 0);

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
  } else if (preset === 'this-month') {
    from.setDate(1);
  } else if (preset === 'last-month') {
    from.setMonth(from.getMonth() - 1, 1);
    to.setDate(0);
  }

  filtersForm.from = dateInputValue(from);
  filtersForm.to = dateInputValue(to);
  applyFilters();
};

const paymentPercentage = (method) => props.summary.total > 0
  ? Math.min(100, Math.max(0, (method.total / props.summary.total) * 100))
  : 0;

const categoryPercentage = (category) => Number(props.summary.items) > 0
  ? Math.min(100, Math.max(0, (Number(category.quantity) / Number(props.summary.items)) * 100))
  : 0;

const paymentMethodsWithSales = computed(() => props.paymentBreakdown.filter((method) => Number(method.total) > 0));

const paymentPieStyle = computed(() => {
  if (props.summary.total <= 0) {
    return { background: 'var(--chart-empty, #e2e8f0)' };
  }

  let offset = 0;
  const segments = paymentMethodsWithSales.value.map((method) => {
    const start = offset;
    offset += (Number(method.total) / props.summary.total) * 100;

    return `${paymentMethodColor(method.method)} ${start}% ${offset}%`;
  });

  return { background: `conic-gradient(${segments.join(', ')})` };
});

const paymentPieLabel = computed(() => paymentMethodsWithSales.value
  .map((method) => `${paymentMethodLabel(method.method)}: ${formatCurrency(method.total)}`)
  .join('; '));

const categoryPalette = ['#0369a1', '#4f7a5a', '#d97706', '#7c3aed', '#e76f51', '#64748b'];

const categoryColor = (index) => categoryPalette[index]
  ?? `hsl(${Math.round((index * 137.508) % 360)} 55% 45%)`;

const categorySlices = computed(() => props.categorySales.map((category, index) => ({
  ...category,
  color: categoryColor(index),
})));

const categoryPieStyle = computed(() => {
  if (Number(props.summary.items) <= 0) {
    return { background: 'var(--chart-empty, #e2e8f0)' };
  }

  let offset = 0;
  const segments = categorySlices.value.map((category) => {
    const start = offset;
    offset += (Number(category.quantity) / Number(props.summary.items)) * 100;

    return `${category.color} ${start}% ${offset}%`;
  });

  return { background: `conic-gradient(${segments.join(', ')})` };
});
</script>

<template>
  <AdminLayout title="Ventas y caja" description="Reporte de ventas registradas">
    <div class="mx-auto max-w-7xl space-y-6">
      <section class="flex flex-wrap items-end justify-between gap-4">
        <div>
          <p class="text-xs font-extrabold uppercase tracking-[0.16em] text-primary-700">Contabilidad</p>
          <h1 class="mt-1 text-2xl font-black tracking-tight text-slate-900 sm:text-3xl">Ventas y caja</h1>
          <p class="mt-2 text-sm text-slate-600">Ventas y gastos del período; el balance se calcula restando los gastos a las ventas.</p>
          <div class="mt-3 flex flex-wrap gap-2">
            <span class="inline-flex items-center gap-1.5 rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-600">
              <UiIcon name="chart" :size="13" />
              {{ activePeriodLabel }}
            </span>
            <span class="inline-flex items-center gap-1.5 rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-600">
              <UiIcon name="card" :size="13" />
              {{ activePaymentMethodLabel }}
            </span>
          </div>
        </div>

        <div class="flex flex-wrap items-center gap-2">
          <button
            type="button"
            :aria-expanded="showFilters"
            :class="[
              'inline-flex min-h-10 items-center gap-2 rounded-lg border px-4 text-sm font-bold transition',
              showFilters ? 'border-primary-600 bg-primary-600 text-white' : 'border-slate-300 bg-white text-slate-700 hover:bg-slate-50',
            ]"
            @click="toggleFilters"
          >
            <UiIcon name="settings" :size="16" />
            Filtros
          </button>
          <details class="relative">
            <summary class="inline-flex min-h-10 cursor-pointer list-none items-center gap-2 rounded-lg border border-slate-300 bg-white px-4 text-sm font-bold text-slate-700 transition hover:bg-slate-50">
              <UiIcon name="menu" :size="16" />
              Acciones
            </summary>
            <div class="absolute right-0 z-30 mt-2 w-56 rounded-xl border border-slate-200 bg-white p-2 shadow-xl">
              <a :href="exportUrl" class="flex items-center gap-2 rounded-lg px-3 py-2 text-sm font-semibold text-slate-700 no-underline hover:bg-slate-100" @click="closeActions">
                <UiIcon name="download" :size="15" />
                Exportar CSV
              </a>
              <a :href="printUrl" target="_blank" rel="noopener" class="flex items-center gap-2 rounded-lg px-3 py-2 text-sm font-semibold text-slate-700 no-underline hover:bg-slate-100" @click="closeActions">
                <UiIcon name="printer" :size="15" />
                Imprimir resumen
              </a>
            </div>
          </details>
        </div>
      </section>

      <section v-if="showFilters" class="dashboard-panel rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
        <div class="flex flex-wrap items-start justify-between gap-3">
          <div>
            <h2 class="font-extrabold text-slate-900">Filtros del reporte</h2>
            <p class="mt-1 text-xs text-slate-500">Elige el período y el método de pago para actualizar la contabilidad.</p>
          </div>
          <button type="button" aria-label="Cerrar filtros" class="flex h-8 w-8 items-center justify-center rounded-lg text-slate-400 hover:bg-slate-100 hover:text-slate-700" @click="toggleFilters">✕</button>
        </div>

        <div class="mt-4">
          <p class="text-[10px] font-extrabold uppercase tracking-[0.14em] text-slate-400">Períodos rápidos</p>
          <div class="mt-2 flex flex-wrap gap-2" role="group" aria-label="Períodos rápidos">
            <button type="button" @click="setPeriodPreset('today')" class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs font-bold text-slate-700 transition hover:bg-slate-50">Hoy</button>
            <button type="button" @click="setPeriodPreset('yesterday')" class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs font-bold text-slate-700 transition hover:bg-slate-50">Ayer</button>
            <button type="button" @click="setPeriodPreset('last-seven-days')" class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs font-bold text-slate-700 transition hover:bg-slate-50">7 días</button>
            <button type="button" @click="setPeriodPreset('this-month')" class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs font-bold text-slate-700 transition hover:bg-slate-50">Este mes</button>
            <button type="button" @click="setPeriodPreset('last-month')" class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs font-bold text-slate-700 transition hover:bg-slate-50">Mes anterior</button>
          </div>
        </div>

        <form class="mt-4 grid gap-3 md:grid-cols-[1fr_1fr_1fr_auto]" @submit.prevent="applyFilters">
          <label class="grid gap-1 text-xs font-bold text-slate-600">
            Desde
            <input v-model="filtersForm.from" type="date" required class="min-h-10 rounded-lg border-slate-300 bg-white px-3 text-sm text-slate-800 focus:border-primary-500 focus:ring-primary-500" />
          </label>
          <label class="grid gap-1 text-xs font-bold text-slate-600">
            Hasta
            <input v-model="filtersForm.to" type="date" required :min="filtersForm.from" class="min-h-10 rounded-lg border-slate-300 bg-white px-3 text-sm text-slate-800 focus:border-primary-500 focus:ring-primary-500" />
          </label>
          <label class="grid gap-1 text-xs font-bold text-slate-600">
            Método de pago
            <select v-model="filtersForm.payment_method" class="min-h-10 rounded-lg border-slate-300 bg-white px-3 text-sm text-slate-800 focus:border-primary-500 focus:ring-primary-500">
              <option v-for="method in paymentMethods" :key="method.value" :value="method.value">{{ method.label }}</option>
            </select>
          </label>
          <div class="flex items-end gap-2">
            <button type="button" class="min-h-10 rounded-lg border border-slate-300 bg-white px-3 text-sm font-bold text-slate-600 transition hover:bg-slate-50" @click="resetFilters">Restablecer</button>
            <button type="submit" :disabled="filtersForm.processing" class="inline-flex min-h-10 items-center justify-center gap-2 rounded-lg bg-primary-600 px-4 text-sm font-bold text-white transition hover:bg-primary-700 disabled:opacity-60">
              <UiIcon name="search" :size="16" />
              {{ filtersForm.processing ? 'Filtrando…' : 'Aplicar' }}
            </button>
          </div>
        </form>
      </section>

      <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3" aria-label="Resumen de caja">
        <article class="dashboard-kpi rounded-2xl border border-slate-200 bg-white p-5 shadow-sm" style="animation-delay: 60ms">
          <p class="text-sm font-semibold text-slate-500">Total vendido</p>
          <p class="mt-2 text-2xl font-black text-slate-900"><AnimatedValue :value="summary.total" format="currency" /></p>
          <p class="mt-1 text-xs text-slate-500">{{ summary.items }} platos vendidos</p>
        </article>
        <article class="dashboard-kpi rounded-2xl border border-slate-200 bg-white p-5 shadow-sm" style="animation-delay: 110ms">
          <p class="text-sm font-semibold text-slate-500">Ventas</p>
          <p class="mt-2 text-2xl font-black text-slate-900"><AnimatedValue :value="summary.tickets" /></p>
          <p class="mt-1 text-xs text-slate-500">tickets registrados</p>
        </article>
        <article class="dashboard-kpi rounded-2xl border border-slate-200 bg-white p-5 shadow-sm" style="animation-delay: 160ms">
          <p class="text-sm font-semibold text-slate-500">Efectivo</p>
          <p class="mt-2 text-2xl font-black text-amber-800"><AnimatedValue :value="summary.cash" format="currency" /></p>
        </article>
        <article class="dashboard-kpi rounded-2xl border border-slate-200 bg-white p-5 shadow-sm" style="animation-delay: 210ms">
          <p class="text-sm font-semibold text-slate-500">QR</p>
          <p class="mt-2 text-2xl font-black text-violet-700"><AnimatedValue :value="summary.qr" format="currency" /></p>
        </article>
        <article class="dashboard-kpi rounded-2xl border border-slate-200 bg-white p-5 shadow-sm" style="animation-delay: 260ms">
          <p class="text-sm font-semibold text-slate-500">Gastos del período</p>
          <p class="mt-2 text-2xl font-black text-rose-700"><AnimatedValue :value="summary.gastos" format="currency" /></p>
          <p class="mt-1 text-xs text-slate-500">{{ gastosResumen.cantidad }} gastos registrados</p>
        </article>
        <article class="dashboard-kpi rounded-2xl border border-slate-200 bg-white p-5 shadow-sm" style="animation-delay: 310ms">
          <p class="text-sm font-semibold text-slate-500">Balance del período</p>
          <p :class="['mt-2 text-2xl font-black', balancePeriodo >= 0 ? 'text-success-700' : 'text-rose-700']"><AnimatedValue :value="balancePeriodo" format="currency" /></p>
          <p class="mt-1 text-xs text-slate-500">ventas menos gastos</p>
        </article>
      </section>

      <section class="grid gap-6 xl:grid-cols-2" aria-label="Gráficos del período">
        <article class="dashboard-panel rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6" style="animation-delay: 320ms">
          <div class="mb-5">
            <h2 class="font-extrabold text-slate-900">Cobros por método</h2>
            <p class="mt-1 text-xs text-slate-500">Distribución del período seleccionado</p>
          </div>
          <div class="flex flex-col items-center gap-6 sm:flex-row sm:justify-around">
            <div class="relative flex h-44 w-44 shrink-0 items-center justify-center">
              <div class="pie-chart h-44 w-44 rounded-full shadow-inner ring-1 ring-slate-200" :style="paymentPieStyle" role="img" :aria-label="paymentMethodsWithSales.length ? `Distribución de cobros: ${paymentPieLabel}` : 'Sin cobros en el período'">
                <div v-if="paymentMethodsWithSales.length" class="pie-center absolute inset-[23%] flex flex-col items-center justify-center rounded-full bg-white px-2 text-center shadow-inner">
                  <span class="text-[9px] font-extrabold uppercase tracking-[0.16em] text-slate-400">Total</span>
                  <strong class="mt-0.5 text-sm font-black text-slate-900"><AnimatedValue :value="summary.total" format="currency" /></strong>
                  <span class="mt-0.5 text-[10px] font-semibold text-slate-500">{{ summary.tickets }} ventas</span>
                </div>
              </div>
              <div v-if="summary.total <= 0" class="absolute inset-0 flex items-center justify-center rounded-full bg-slate-100/90 text-center text-xs font-bold text-slate-500">Sin cobros</div>
            </div>
            <div v-if="paymentMethodsWithSales.length" class="chart-legend w-full max-w-xs space-y-3">
              <div v-for="(method, index) in paymentMethodsWithSales" :key="method.method" class="flex items-start gap-3">
                <span class="mt-1 h-3.5 w-3.5 shrink-0 rounded-full shadow-sm" :style="{ backgroundColor: paymentMethodColor(method.method) }"></span>
                <div class="min-w-0 flex-1">
                  <div class="flex items-baseline justify-between gap-2">
                    <p class="truncate text-sm font-bold text-slate-800">{{ paymentMethodLabel(method.method) }}</p>
                    <p class="shrink-0 text-sm font-extrabold text-slate-900">{{ formatCurrency(method.total) }}</p>
                  </div>
                  <p class="text-xs text-slate-500">{{ method.tickets }} ventas · {{ paymentPercentage(method).toFixed(1) }}%</p>
                  <div class="mt-1.5 h-1.5 overflow-hidden rounded-full bg-slate-100" aria-hidden="true">
                    <div class="chart-bar h-full rounded-full" :style="{ width: `${paymentPercentage(method)}%`, backgroundColor: paymentMethodColor(method.method), animationDelay: `${580 + index * 80}ms` }"></div>
                  </div>
                </div>
              </div>
            </div>
            <p v-else class="text-center text-sm text-slate-500">No hay cobros en el período.</p>
          </div>
        </article>

        <article class="dashboard-panel rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6" style="animation-delay: 420ms">
          <div class="mb-5">
            <h2 class="font-extrabold text-slate-900">Ventas por categoría</h2>
            <p class="mt-1 text-xs text-slate-500">Participación por unidades · incluye bebidas y nuevas categorías</p>
          </div>
          <div class="flex flex-col items-center gap-6 sm:flex-row sm:justify-around">
            <div class="relative flex h-44 w-44 shrink-0 items-center justify-center">
              <div class="pie-chart pie-chart--delayed h-44 w-44 rounded-full shadow-inner ring-1 ring-slate-200" :style="categoryPieStyle" role="img" :aria-label="categorySlices.length ? `Ventas por categoría: ${categorySlices.map((category) => `${category.name}: ${category.quantity} unidades`).join('; ')}` : 'Sin productos vendidos'">
                <div v-if="categorySlices.length" class="pie-center absolute inset-[23%] flex flex-col items-center justify-center rounded-full bg-white px-2 text-center shadow-inner">
                  <span class="text-[9px] font-extrabold uppercase tracking-[0.16em] text-slate-400">Unidades</span>
                  <strong class="mt-0.5 text-2xl font-black leading-none text-slate-900"><AnimatedValue :value="summary.items" /></strong>
                  <span class="mt-0.5 text-[10px] font-semibold text-slate-500">vendidas</span>
                </div>
              </div>
              <div v-if="Number(summary.items) <= 0" class="absolute inset-0 flex items-center justify-center rounded-full bg-slate-100/90 text-center text-xs font-bold text-slate-500">Sin ventas</div>
            </div>
            <div v-if="categorySlices.length" class="chart-legend chart-legend--delayed w-full max-w-xs space-y-3">
              <div v-for="(category, index) in categorySlices" :key="category.name" class="flex items-start gap-3">
                <span class="mt-1 h-3.5 w-3.5 shrink-0 rounded-full shadow-sm" :style="{ backgroundColor: category.color }"></span>
                <div class="min-w-0 flex-1">
                  <div class="flex items-baseline justify-between gap-2">
                    <p class="truncate text-sm font-bold text-slate-800">{{ category.name }}</p>
                    <p class="shrink-0 text-xs font-extrabold text-slate-900">{{ category.quantity }} und.</p>
                  </div>
                  <p class="text-xs text-slate-500">{{ categoryPercentage(category).toFixed(1) }}% del total</p>
                  <div class="mt-1.5 h-1.5 overflow-hidden rounded-full bg-slate-100" aria-hidden="true">
                    <div class="chart-bar h-full rounded-full" :style="{ width: `${categoryPercentage(category)}%`, backgroundColor: category.color, animationDelay: `${700 + index * 80}ms` }"></div>
                  </div>
                </div>
              </div>
            </div>
            <p v-else class="text-center text-sm text-slate-500">No hay ventas por categoría en el período.</p>
          </div>
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
                      class="mt-1 text-xs font-bold text-primary-700 underline decoration-primary-300 underline-offset-2 hover:text-primary-900"
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

        <aside class="space-y-6">
          <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <div class="mb-4">
              <h2 class="font-extrabold text-slate-900">Top productos vendidos</h2>
              <p class="mt-1 text-xs text-slate-500">Los 5 productos más vendidos del período.</p>
            </div>
            <div v-if="topDishes.length" class="space-y-3">
              <div v-for="(dish, index) in topDishes" :key="dish.name" class="flex items-center justify-between gap-3 border-b border-slate-100 pb-2 last:border-0 last:pb-0">
                <div class="flex min-w-0 items-center gap-2">
                  <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-primary-50 text-xs font-black text-primary-700">{{ index + 1 }}</span>
                  <p class="truncate text-sm font-semibold text-slate-700">{{ dish.name }}</p>
                </div>
                <div class="text-right">
                  <p class="text-sm font-extrabold text-slate-900">{{ dish.quantity }} uds.</p>
                  <p class="text-xs text-slate-500">{{ formatCurrency(dish.total) }}</p>
                </div>
              </div>
            </div>
            <p v-else class="text-sm text-slate-500">No hay ventas en el período.</p>
          </div>

          <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
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
                  <div class="h-full rounded-full transition-[width]" :style="{ width: `${paymentPercentage(method)}%`, backgroundColor: paymentMethodColor(method.method) }"></div>
                </div>
              </div>
            </div>
          </div>
          <p v-else class="text-sm text-slate-500">No hay pagos registrados en el período.</p>
          </div>
        </aside>
      </section>

      <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
        <header class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-200 px-5 py-4">
          <div>
            <h2 class="font-extrabold text-slate-900">Gastos del período</h2>
            <p class="mt-1 text-xs text-slate-500">{{ gastosResumen.cantidad }} gastos · {{ formatCurrency(gastosResumen.total) }}</p>
          </div>
          <a href="/admin/gastos" class="text-sm font-bold text-primary-700 no-underline hover:text-primary-900">Administrar gastos</a>
        </header>

        <div v-if="gastos.length" class="overflow-x-auto">
          <table class="w-full min-w-[46rem] text-left text-sm">
            <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
              <tr>
                <th class="px-5 py-3 font-bold">Fecha</th>
                <th class="px-5 py-3 font-bold">Descripción</th>
                <th class="px-5 py-3 font-bold">Categoría</th>
                <th class="px-5 py-3 font-bold">Método</th>
                <th class="px-5 py-3 font-bold">Usuario</th>
                <th class="px-5 py-3 text-right font-bold">Monto</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
              <tr v-for="gasto in gastos" :key="gasto.id">
                <td class="px-5 py-3 text-slate-600">{{ fechaGasto(gasto.fecha) }}</td>
                <td class="px-5 py-3">
                  <p class="font-semibold text-slate-900">{{ gasto.descripcion }}</p>
                  <p v-if="gasto.notas" class="text-xs text-slate-500">{{ gasto.notas }}</p>
                </td>
                <td class="px-5 py-3 text-slate-600">{{ categoriaGastoLabel(gasto.categoria) }}</td>
                <td class="px-5 py-3 text-slate-600">{{ metodoGastoLabel(gasto.metodo_pago) }}</td>
                <td class="px-5 py-3 text-slate-600">{{ gasto.user?.name || 'Sistema' }}</td>
                <td class="px-5 py-3 text-right font-extrabold text-rose-700">{{ formatCurrency(gasto.monto) }}</td>
              </tr>
            </tbody>
          </table>
        </div>
        <p v-else class="px-5 py-10 text-center text-sm text-slate-500">No hay gastos en el período.</p>
      </section>
    </div>
  </AdminLayout>
</template>
