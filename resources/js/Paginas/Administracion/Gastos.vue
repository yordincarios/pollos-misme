<script setup>
import { computed } from 'vue';
import { router, useForm, usePage } from '@inertiajs/vue3';
import AdminLayout from '../../Plantillas/PlantillaAdministracion.vue';
import Icono from '../../Componentes/Icono.vue';
import { formatCurrency } from '../../utilidades/moneda.js';

const props = defineProps({
  gastos: { type: Object, required: true },
  filtros: { type: Object, required: true },
  resumen: { type: Object, required: true },
  categorias: { type: Array, default: () => [] },
  metodosPago: { type: Array, default: () => [] },
  hoy: { type: String, required: true },
});

const page = usePage();
const exito = computed(() => page.props.flash?.success || '');

const form = useForm({
  descripcion: '',
  categoria: 'insumos',
  monto: '',
  fecha: props.hoy,
  metodo_pago: 'efectivo',
  notas: '',
});

const filtrosForm = useForm({
  desde: props.filtros.desde,
  hasta: props.filtros.hasta,
  categoria: props.filtros.categoria || '',
});

const categoriaLabel = (categoria) => ({
  insumos: 'Insumos',
  servicios: 'Servicios',
  personal: 'Personal',
  mantenimiento: 'Mantenimiento',
  otros: 'Otros',
}[categoria] || categoria);

const metodoLabel = (metodo) => ({
  efectivo: 'Efectivo',
  transferencia: 'Transferencia',
  qr: 'QR',
}[metodo] || metodo);

const crear = () => {
  form.post('/admin/gastos', {
    preserveScroll: true,
    onSuccess: () => form.reset('descripcion', 'monto', 'notas'),
  });
};

const aplicarFiltros = () => {
  filtrosForm.get('/admin/gastos', {
    preserveScroll: true,
    preserveState: true,
    replace: true,
  });
};

const eliminar = (gasto) => {
  if (window.confirm(`¿Eliminar el gasto "${gasto.descripcion}"?`)) {
    router.delete(`/admin/gastos/${gasto.id}`, { preserveScroll: true });
  }
};

const irAPagina = (url) => {
  if (url) {
    window.location.href = url;
  }
};

const fecha = (value) => new Date(`${value}T00:00:00`).toLocaleDateString('es-BO', { dateStyle: 'medium' });
</script>

<template>
  <AdminLayout title="Gastos" description="Salidas de dinero del restaurante">
    <div class="mx-auto max-w-6xl space-y-6">
      <section>
        <p class="text-xs font-extrabold uppercase tracking-[0.16em] text-primary-700">Administración</p>
        <h1 class="mt-1 text-2xl font-black tracking-tight text-slate-900 sm:text-3xl">Gastos</h1>
        <p class="mt-2 text-sm text-slate-600">Registra insumos, servicios y otras salidas de dinero.</p>
      </section>

      <div v-if="exito" class="rounded-xl border border-success-200 bg-success-50 p-4 text-sm font-semibold text-success-900" role="status">{{ exito }}</div>

      <section class="grid gap-4 sm:grid-cols-2">
        <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
          <p class="text-sm font-semibold text-slate-500">Gastos del período</p>
          <p class="mt-2 text-2xl font-black text-rose-700">{{ formatCurrency(resumen.total) }}</p>
        </article>
        <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
          <p class="text-sm font-semibold text-slate-500">Cantidad de gastos</p>
          <p class="mt-2 text-2xl font-black text-slate-900">{{ resumen.cantidad }}</p>
        </article>
      </section>

      <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
        <div class="mb-4">
          <h2 class="font-extrabold text-slate-900">Nuevo gasto</h2>
          <p class="mt-1 text-xs text-slate-500">Registra una salida de caja.</p>
        </div>
        <form class="grid gap-3 sm:grid-cols-2" @submit.prevent="crear">
          <label class="grid gap-1 text-xs font-bold text-slate-600">
            Descripción
            <input v-model="form.descripcion" required maxlength="255" placeholder="Ej. Compra de pollo" class="rounded-lg border-slate-300 px-3 py-2.5 text-sm focus:border-primary-500 focus:ring-primary-500" />
            <span v-if="form.errors.descripcion" class="text-xs font-normal text-rose-600">{{ form.errors.descripcion }}</span>
          </label>
          <label class="grid gap-1 text-xs font-bold text-slate-600">
            Categoría
            <select v-model="form.categoria" class="rounded-lg border-slate-300 bg-white px-3 py-2.5 text-sm focus:border-primary-500 focus:ring-primary-500">
              <option v-for="categoria in categorias" :key="categoria" :value="categoria">{{ categoriaLabel(categoria) }}</option>
            </select>
          </label>
          <label class="grid gap-1 text-xs font-bold text-slate-600">
            Monto (Bs)
            <input v-model="form.monto" type="number" min="0.01" step="0.01" required class="rounded-lg border-slate-300 px-3 py-2.5 text-sm focus:border-primary-500 focus:ring-primary-500" />
            <span v-if="form.errors.monto" class="text-xs font-normal text-rose-600">{{ form.errors.monto }}</span>
          </label>
          <label class="grid gap-1 text-xs font-bold text-slate-600">
            Fecha
            <input v-model="form.fecha" type="date" required class="rounded-lg border-slate-300 px-3 py-2.5 text-sm focus:border-primary-500 focus:ring-primary-500" />
          </label>
          <label class="grid gap-1 text-xs font-bold text-slate-600">
            Método de pago
            <select v-model="form.metodo_pago" class="rounded-lg border-slate-300 bg-white px-3 py-2.5 text-sm focus:border-primary-500 focus:ring-primary-500">
              <option v-for="metodo in metodosPago" :key="metodo" :value="metodo">{{ metodoLabel(metodo) }}</option>
            </select>
          </label>
          <label class="grid gap-1 text-xs font-bold text-slate-600">
            Nota (opcional)
            <input v-model="form.notas" maxlength="500" class="rounded-lg border-slate-300 px-3 py-2.5 text-sm focus:border-primary-500 focus:ring-primary-500" />
          </label>
          <div class="sm:col-span-2 flex justify-end">
            <button type="submit" :disabled="form.processing" class="inline-flex items-center gap-2 rounded-xl bg-primary-700 px-4 py-2.5 text-sm font-bold text-white hover:bg-primary-800 disabled:opacity-60">
              <Icono name="plus" :size="16" />
              Registrar gasto
            </button>
          </div>
        </form>
      </section>

      <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
        <form class="grid gap-3 sm:grid-cols-[1fr_1fr_12rem_auto]" @submit.prevent="aplicarFiltros">
          <label class="grid gap-1 text-xs font-bold text-slate-600">
            Desde
            <input v-model="filtrosForm.desde" type="date" required class="rounded-lg border-slate-300 px-3 py-2.5 text-sm focus:border-primary-500 focus:ring-primary-500" />
          </label>
          <label class="grid gap-1 text-xs font-bold text-slate-600">
            Hasta
            <input v-model="filtrosForm.hasta" type="date" required :min="filtrosForm.desde" class="rounded-lg border-slate-300 px-3 py-2.5 text-sm focus:border-primary-500 focus:ring-primary-500" />
          </label>
          <label class="grid gap-1 text-xs font-bold text-slate-600">
            Categoría
            <select v-model="filtrosForm.categoria" class="rounded-lg border-slate-300 bg-white px-3 py-2.5 text-sm focus:border-primary-500 focus:ring-primary-500">
              <option value="">Todas</option>
              <option v-for="categoria in categorias" :key="categoria" :value="categoria">{{ categoriaLabel(categoria) }}</option>
            </select>
          </label>
          <div class="flex items-end">
            <button type="submit" :disabled="filtrosForm.processing" class="inline-flex min-h-10 items-center gap-2 rounded-xl bg-primary-700 px-4 py-2.5 text-sm font-bold text-white hover:bg-primary-800 disabled:opacity-60">
              <Icono name="search" :size="16" />
              Filtrar
            </button>
          </div>
        </form>
      </section>

      <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
        <div v-if="gastos.data.length" class="overflow-x-auto">
          <table class="w-full min-w-[48rem] text-left text-sm">
            <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
              <tr>
                <th class="px-5 py-3 font-bold">Fecha</th>
                <th class="px-5 py-3 font-bold">Descripción</th>
                <th class="px-5 py-3 font-bold">Categoría</th>
                <th class="px-5 py-3 font-bold">Método</th>
                <th class="px-5 py-3 font-bold">Usuario</th>
                <th class="px-5 py-3 text-right font-bold">Monto</th>
                <th class="px-5 py-3"></th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
              <tr v-for="gasto in gastos.data" :key="gasto.id">
                <td class="px-5 py-3 text-slate-600">{{ fecha(gasto.fecha) }}</td>
                <td class="px-5 py-3">
                  <p class="font-semibold text-slate-900">{{ gasto.descripcion }}</p>
                  <p v-if="gasto.notas" class="text-xs text-slate-500">{{ gasto.notas }}</p>
                </td>
                <td class="px-5 py-3 text-slate-600">{{ categoriaLabel(gasto.categoria) }}</td>
                <td class="px-5 py-3 text-slate-600">{{ metodoLabel(gasto.metodo_pago) }}</td>
                <td class="px-5 py-3 text-slate-600">{{ gasto.user?.name || 'Sistema' }}</td>
                <td class="px-5 py-3 text-right font-extrabold text-rose-700">{{ formatCurrency(gasto.monto) }}</td>
                <td class="px-5 py-3 text-right">
                  <button type="button" class="rounded-lg bg-rose-50 px-3 py-2 text-xs font-bold text-rose-700 hover:bg-rose-100" @click="eliminar(gasto)">Eliminar</button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
        <p v-else class="px-5 py-10 text-center text-sm text-slate-500">No hay gastos en el período.</p>

        <div v-if="gastos.last_page > 1" class="flex items-center justify-between border-t border-slate-200 px-5 py-3">
          <button type="button" :disabled="!gastos.prev_page_url" class="rounded-lg px-3 py-2 text-sm font-bold text-slate-700 hover:bg-slate-100 disabled:opacity-40" @click="irAPagina(gastos.prev_page_url)">Anterior</button>
          <span class="text-xs font-semibold text-slate-500">Página {{ gastos.current_page }} de {{ gastos.last_page }}</span>
          <button type="button" :disabled="!gastos.next_page_url" class="rounded-lg px-3 py-2 text-sm font-bold text-slate-700 hover:bg-slate-100 disabled:opacity-40" @click="irAPagina(gastos.next_page_url)">Siguiente</button>
        </div>
      </section>
    </div>
  </AdminLayout>
</template>
