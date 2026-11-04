<script setup>
import { computed, ref } from 'vue';
import { useForm } from '@inertiajs/vue3';
import AdminLayout from '../../../Plantillas/PlantillaAdministracion.vue';
import FloatingModal from '../../../Componentes/ModalFlotante.vue';
import UiIcon from '../../../Componentes/Icono.vue';

const props = defineProps({
  dishes: { type: Array, default: () => [] },
  categories: { type: Array, default: () => [] },
  filters: { type: Object, required: true },
  summary: { type: Object, required: true },
  movements: { type: Array, default: () => [] },
});

const filtersForm = useForm({
  search: props.filters.search || '',
  category: props.filters.category || '',
  status: props.filters.status || '',
});

const trackingModalOpen = ref(false);
const movementModalOpen = ref(false);
const selectedDish = ref(null);
const showHistory = ref(false);

const trackingForm = useForm({
  track_stock: true,
  low_stock_threshold: 0,
  initial_stock: 0,
});

const movementForm = useForm({
  type: 'entry',
  quantity: 1,
  notes: '',
});

const statusOf = (dish) => {
  if (! dish.track_stock) {
    return 'untracked';
  }

  if (dish.stock_quantity === 0) {
    return 'out';
  }

  if (dish.stock_quantity <= dish.low_stock_threshold) {
    return 'low';
  }

  return 'ok';
};

const statusLabels = {
  untracked: 'Sin inventario',
  ok: 'Disponible',
  low: 'Stock bajo',
  out: 'Agotado',
};

const statusClasses = {
  untracked: 'bg-slate-100 text-slate-600',
  ok: 'bg-success-50 text-success-800',
  low: 'bg-amber-50 text-amber-800',
  out: 'bg-rose-50 text-rose-700',
};

const statusFilters = computed(() => [
  { value: '', label: 'Todos', count: Number(props.summary.total) || 0 },
  { value: 'tracked', label: 'Con inventario', count: Number(props.summary.tracked) || 0 },
  { value: 'low', label: 'Stock bajo', count: Number(props.summary.low) || 0 },
  { value: 'out', label: 'Agotados', count: Number(props.summary.out) || 0 },
  {
    value: 'untracked',
    label: 'Sin inventario',
    count: Math.max(0, (Number(props.summary.total) || 0) - (Number(props.summary.tracked) || 0)),
  },
]);

const movementLabels = {
  entry: 'Entrada',
  waste: 'Merma',
  adjustment: 'Corrección',
  sale: 'Venta',
};

const movementActionLabels = {
  entry: 'Agregar stock',
  waste: 'Registrar merma',
  adjustment: 'Corregir stock',
};

const movementActionDescriptions = {
  entry: 'Llegó mercadería',
  waste: 'Producto perdido o dañado',
  adjustment: 'Ajustar al conteo real',
};

const movementActionIcons = {
  entry: 'plus',
  waste: 'trash',
  adjustment: 'box',
};

const movementClasses = {
  entry: 'text-success-700',
  waste: 'text-rose-700',
  adjustment: 'text-primary-700',
  sale: 'text-slate-700',
};

const movementQuantityLabel = computed(() => {
  if (movementForm.type === 'adjustment') {
    return 'Stock real contado';
  }

  return movementForm.type === 'waste' ? 'Unidades perdidas' : 'Unidades a agregar';
});

const movementSubmitLabel = computed(() => movementForm.processing
  ? 'Guardando…'
  : movementActionLabels[movementForm.type]);

const trackingModalTitle = computed(() => selectedDish.value?.track_stock ? 'Configurar inventario' : 'Activar inventario');

const trackingSubmitLabel = computed(() => {
  if (trackingForm.processing) {
    return 'Guardando…';
  }

  return selectedDish.value && ! selectedDish.value.track_stock && trackingForm.track_stock
    ? 'Activar inventario'
    : 'Guardar cambios';
});

const previewStock = computed(() => {
  if (! selectedDish.value) {
    return 0;
  }

  const current = Number(selectedDish.value.stock_quantity) || 0;
  const quantity = Number(movementForm.quantity) || 0;

  if (movementForm.type === 'entry') {
    return current + quantity;
  }

  if (movementForm.type === 'waste') {
    return Math.max(0, current - quantity);
  }

  return quantity;
});

const applyFilters = () => {
  filtersForm.get('/admin/inventory', {
    preserveScroll: true,
    preserveState: true,
    replace: true,
  });
};

const setStatus = (status) => {
  filtersForm.status = status;
  applyFilters();
};

const openTrackingModal = (dish, activate = false) => {
  selectedDish.value = dish;
  trackingForm.track_stock = activate ? true : dish.track_stock;
  trackingForm.low_stock_threshold = dish.low_stock_threshold;
  trackingForm.initial_stock = 0;
  trackingForm.clearErrors();
  trackingModalOpen.value = true;
};

const closeTrackingModal = () => {
  trackingModalOpen.value = false;
  trackingForm.clearErrors();
  selectedDish.value = null;
};

const saveTracking = () => {
  if (! selectedDish.value) {
    return;
  }

  trackingForm.patch(`/admin/inventory/${selectedDish.value.id}/tracking`, {
    preserveScroll: true,
    onSuccess: closeTrackingModal,
  });
};

const openMovementModal = (dish) => {
  selectedDish.value = dish;
  movementForm.reset();
  movementForm.type = 'entry';
  movementForm.quantity = 1;
  movementForm.notes = '';
  movementForm.clearErrors();
  movementModalOpen.value = true;
};

const selectMovementType = (type) => {
  movementForm.type = type;
  movementForm.quantity = type === 'adjustment'
    ? Number(selectedDish.value?.stock_quantity) || 0
    : 1;
  movementForm.clearErrors();
};

const closeMovementModal = () => {
  movementModalOpen.value = false;
  movementForm.clearErrors();
  selectedDish.value = null;
};

const saveMovement = () => {
  if (! selectedDish.value) {
    return;
  }

  movementForm.post(`/admin/inventory/${selectedDish.value.id}/movements`, {
    preserveScroll: true,
    onSuccess: closeMovementModal,
  });
};

const formatMovementDate = (date) => new Date(date).toLocaleString('es-BO', {
  dateStyle: 'short',
  timeStyle: 'short',
});
</script>

<template>
  <AdminLayout title="Inventario" description="Stock de platos y bebidas">
    <div class="mx-auto max-w-7xl space-y-6">
      <section class="flex flex-wrap items-end justify-between gap-4">
        <div>
          <p class="text-xs font-extrabold uppercase tracking-[0.16em] text-primary-700">Administración</p>
          <h1 class="mt-1 text-2xl font-black tracking-tight text-slate-900 sm:text-3xl">Inventario</h1>
          <p class="mt-2 max-w-2xl text-sm text-slate-600">Controla las unidades disponibles de cada plato o bebida. La caja descuenta el stock al confirmar la venta.</p>
        </div>
      </section>

      <div class="flex items-start gap-3 rounded-xl border border-primary-200 bg-primary-50 p-4 text-sm leading-relaxed text-primary-900">
        <UiIcon name="box" class="mt-0.5 shrink-0 text-primary-700" :size="20" />
        <p><strong>Activa el inventario solo en los productos que quieras controlar.</strong> Si un producto no lo tiene activo, la caja lo vende sin descontar unidades.</p>
      </div>

      <section class="grid gap-4 sm:grid-cols-3" aria-label="Resumen de inventario">
        <button type="button" class="rounded-2xl border border-slate-200 bg-white p-5 text-left shadow-sm transition hover:-translate-y-0.5 hover:border-primary-300 hover:shadow-md" :aria-pressed="filtersForm.status === 'tracked'" @click="setStatus('tracked')">
          <div class="flex items-center justify-between gap-3">
            <p class="text-sm font-semibold text-slate-500">Con inventario</p>
            <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-primary-50 text-primary-700"><UiIcon name="box" :size="19" /></span>
          </div>
          <p class="mt-2 text-2xl font-black text-slate-900">{{ summary.tracked }}</p>
          <p class="mt-1 text-xs text-slate-400">La caja descuenta unidades</p>
        </button>
        <button type="button" class="rounded-2xl border border-slate-200 bg-white p-5 text-left shadow-sm transition hover:-translate-y-0.5 hover:border-amber-300 hover:shadow-md" :aria-pressed="filtersForm.status === 'low'" @click="setStatus('low')">
          <div class="flex items-center justify-between gap-3">
            <p class="text-sm font-semibold text-slate-500">Stock bajo</p>
            <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-amber-50 text-amber-800"><UiIcon name="chart" :size="19" /></span>
          </div>
          <p class="mt-2 text-2xl font-black text-amber-800">{{ summary.low }}</p>
          <p class="mt-1 text-xs text-slate-400">Productos por reponer</p>
        </button>
        <button type="button" class="rounded-2xl border border-slate-200 bg-white p-5 text-left shadow-sm transition hover:-translate-y-0.5 hover:border-rose-300 hover:shadow-md" :aria-pressed="filtersForm.status === 'out'" @click="setStatus('out')">
          <div class="flex items-center justify-between gap-3">
            <p class="text-sm font-semibold text-slate-500">Agotados</p>
            <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-rose-50 text-rose-700"><UiIcon name="trash" :size="19" /></span>
          </div>
          <p class="mt-2 text-2xl font-black text-rose-700">{{ summary.out }}</p>
          <p class="mt-1 text-xs text-slate-400">No se pueden vender</p>
        </button>
      </section>

      <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
        <header class="space-y-3 border-b border-slate-200 px-5 py-4">
          <div class="flex flex-wrap items-end justify-between gap-2">
            <div>
              <h2 class="font-extrabold text-slate-900">Productos y stock</h2>
              <p class="mt-1 text-xs text-slate-500">{{ dishes.length }} productos encontrados</p>
            </div>
            <p class="text-xs font-semibold text-slate-400">Los agotados y de stock bajo aparecen primero.</p>
          </div>

          <div class="flex flex-wrap gap-2" role="group" aria-label="Filtros rápidos de inventario">
            <button
              v-for="filter in statusFilters"
              :key="filter.value || 'all'"
              type="button"
              :aria-pressed="filtersForm.status === filter.value"
              :class="[
                'inline-flex items-center gap-1.5 rounded-full border px-3 py-1.5 text-xs font-bold transition',
                filtersForm.status === filter.value
                  ? 'border-primary-600 bg-primary-600 text-white'
                  : 'border-slate-200 bg-white text-slate-600 hover:border-primary-300 hover:text-primary-700',
              ]"
              @click="setStatus(filter.value)"
            >
              {{ filter.label }}
              <span :class="['rounded-full px-1.5 py-0.5 text-[10px] font-black', filtersForm.status === filter.value ? 'bg-white/20 text-white' : 'bg-slate-100 text-slate-500']">{{ filter.count }}</span>
            </button>
          </div>

          <form class="grid gap-3 sm:grid-cols-[minmax(0,1fr)_12rem_auto]" @submit.prevent="applyFilters">
            <label class="grid gap-1 text-xs font-bold text-slate-600">
              Buscar
              <input v-model="filtersForm.search" type="search" placeholder="Nombre del plato o bebida" class="min-h-10 rounded-lg border-slate-300 bg-white px-3 text-sm text-slate-800 focus:border-primary-500 focus:ring-primary-500" />
            </label>
            <label class="grid gap-1 text-xs font-bold text-slate-600">
              Categoría
              <select v-model="filtersForm.category" class="min-h-10 rounded-lg border-slate-300 bg-white px-3 text-sm text-slate-800 focus:border-primary-500 focus:ring-primary-500" @change="applyFilters">
                <option value="">Todas</option>
                <option v-for="category in categories" :key="category.id" :value="category.id">{{ category.name }}</option>
              </select>
            </label>
            <button type="submit" :disabled="filtersForm.processing" class="inline-flex min-h-10 items-center justify-center gap-2 rounded-lg bg-slate-900 px-4 text-sm font-bold text-white transition hover:bg-slate-800 disabled:opacity-60">
              <UiIcon name="search" :size="16" />
              Filtrar
            </button>
          </form>
        </header>

        <div v-if="dishes.length" class="divide-y divide-slate-100">
          <article v-for="dish in dishes" :key="dish.id" class="flex flex-wrap items-center justify-between gap-4 px-5 py-4">
            <div class="flex min-w-0 items-center gap-3">
              <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-slate-50 text-slate-500">
                <UiIcon name="box" :size="20" />
              </span>
              <div class="min-w-0">
                <div class="flex flex-wrap items-center gap-2">
                  <h3 class="truncate font-bold text-slate-900">{{ dish.name }}</h3>
                  <span :class="['rounded-full px-2.5 py-1 text-[10px] font-bold', statusClasses[statusOf(dish)]]">{{ statusLabels[statusOf(dish)] }}</span>
                </div>
                <p class="mt-0.5 text-xs text-slate-500">{{ dish.category?.name || 'General' }}</p>
                <p v-if="dish.track_stock" class="mt-1 flex flex-wrap items-baseline gap-x-1.5 text-xs text-slate-500">
                  <strong class="text-xl font-black leading-none text-slate-900">{{ dish.stock_quantity }}</strong>
                  <span>unidades</span>
                  <span class="text-slate-400">· aviso en {{ dish.low_stock_threshold }}</span>
                </p>
                <p v-else class="mt-1 text-xs text-slate-500">No descuenta stock en la caja.</p>
              </div>
            </div>

            <div class="flex shrink-0 flex-wrap gap-2">
              <button v-if="dish.track_stock" type="button" @click="openMovementModal(dish)" class="rounded-lg bg-primary-700 px-3 py-2 text-xs font-bold text-white hover:bg-primary-800">Actualizar stock</button>
              <button v-else type="button" @click="openTrackingModal(dish, true)" class="rounded-lg bg-primary-700 px-3 py-2 text-xs font-bold text-white hover:bg-primary-800">Activar inventario</button>
              <button v-if="dish.track_stock" type="button" @click="openTrackingModal(dish)" class="rounded-lg border border-slate-300 px-3 py-2 text-xs font-bold text-slate-700 hover:bg-slate-100">Configurar</button>
            </div>
          </article>
        </div>
        <p v-else class="px-5 py-12 text-center text-sm text-slate-500">No hay productos que coincidan con los filtros.</p>
      </section>

      <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
        <header class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-200 px-5 py-4">
          <div>
            <h2 class="font-extrabold text-slate-900">Últimos movimientos</h2>
            <p class="mt-1 text-xs text-slate-500">Entradas, mermas, correcciones y ventas</p>
          </div>
          <button type="button" :aria-expanded="showHistory" aria-controls="inventory-history" class="inline-flex items-center gap-2 rounded-lg border border-slate-300 px-3 py-2 text-xs font-bold text-slate-700 hover:bg-slate-100" @click="showHistory = !showHistory">
            {{ showHistory ? 'Ocultar historial' : 'Ver historial' }}
            <UiIcon :name="showHistory ? 'compress' : 'expand'" :size="15" />
          </button>
        </header>

        <div v-show="showHistory" id="inventory-history">
          <div v-if="movements.length" class="overflow-x-auto">
            <table class="w-full min-w-[46rem] text-left text-sm">
              <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                <tr>
                  <th class="px-5 py-3 font-bold">Fecha</th>
                  <th class="px-5 py-3 font-bold">Producto</th>
                  <th class="px-5 py-3 font-bold">Tipo</th>
                  <th class="px-5 py-3 text-right font-bold">Cambio</th>
                  <th class="px-5 py-3 text-right font-bold">Stock después</th>
                  <th class="px-5 py-3 font-bold">Usuario</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-slate-100">
                <tr v-for="movement in movements" :key="movement.id">
                  <td class="px-5 py-3 text-xs text-slate-500">{{ formatMovementDate(movement.created_at) }}</td>
                  <td class="px-5 py-3 font-semibold text-slate-800">{{ movement.dish?.name || 'Producto eliminado' }}</td>
                  <td :class="['px-5 py-3 text-xs font-bold', movementClasses[movement.type] || 'text-slate-600']">{{ movementLabels[movement.type] || movement.type }}</td>
                  <td :class="['px-5 py-3 text-right font-extrabold', movement.quantity > 0 ? 'text-success-700' : 'text-rose-700']">{{ movement.quantity > 0 ? '+' : '' }}{{ movement.quantity }}</td>
                  <td class="px-5 py-3 text-right font-bold text-slate-800">{{ movement.stock_after }}</td>
                  <td class="px-5 py-3 text-xs text-slate-500">{{ movement.user?.name || 'Sistema' }}</td>
                </tr>
              </tbody>
            </table>
          </div>
          <p v-else class="px-5 py-10 text-center text-sm text-slate-500">Todavía no hay movimientos registrados.</p>
        </div>
      </section>

      <FloatingModal
        v-model:open="trackingModalOpen"
        :title="trackingModalTitle"
        title-id="inventory-tracking-title"
        :description="selectedDish ? `Controla si la caja descuenta unidades de ${selectedDish.name}.` : 'Configura el control de unidades.'"
      >
        <form class="mx-auto max-w-lg space-y-5" @submit.prevent="saveTracking">
          <label class="flex items-start gap-3 rounded-xl border border-slate-200 p-4">
            <input v-model="trackingForm.track_stock" type="checkbox" class="mt-0.5 h-5 w-5 rounded border-slate-300 text-primary-600 focus:ring-primary-500" />
            <span>
              <span class="block text-sm font-bold text-slate-800">Controlar stock de este producto</span>
              <span class="mt-1 block text-xs text-slate-500">La caja descontará una unidad por cada venta y bloqueará el producto cuando llegue a cero.</span>
            </span>
          </label>

          <label class="block text-sm font-bold text-slate-700">
            Avisarme cuando queden
            <input v-model.number="trackingForm.low_stock_threshold" type="number" min="0" max="1000000" :disabled="!trackingForm.track_stock" class="mt-1 w-full rounded-xl border-slate-300 px-3 py-2.5 text-sm text-slate-800 focus:border-primary-500 focus:ring-primary-500 disabled:bg-slate-100 disabled:text-slate-400" />
            <span class="mt-1 block text-xs font-normal text-slate-500">Si el stock llega a este número, aparecerá como “Stock bajo”.</span>
            <span v-if="trackingForm.errors.low_stock_threshold" class="mt-1 block text-xs text-rose-600">{{ trackingForm.errors.low_stock_threshold }}</span>
          </label>

          <label v-if="selectedDish && !selectedDish.track_stock && trackingForm.track_stock" class="block text-sm font-bold text-slate-700">
            Stock inicial (unidades)
            <input v-model.number="trackingForm.initial_stock" type="number" min="0" max="1000000" class="mt-1 w-full rounded-xl border-slate-300 px-3 py-2.5 text-sm text-slate-800 focus:border-primary-500 focus:ring-primary-500" />
            <span class="mt-1 block text-xs font-normal text-slate-500">Opcional. Se registra como primera entrada de inventario.</span>
            <span v-if="trackingForm.errors.initial_stock" class="mt-1 block text-xs text-rose-600">{{ trackingForm.errors.initial_stock }}</span>
          </label>

          <p v-if="selectedDish && !selectedDish.track_stock && trackingForm.track_stock" class="rounded-xl border border-amber-200 bg-amber-50 p-4 text-xs leading-relaxed text-amber-900">
            Puedes cargar el stock inicial ahora. Si lo dejas en 0, agrégalo después con “Actualizar stock”.
          </p>
          <p v-if="selectedDish?.track_stock && !trackingForm.track_stock" class="rounded-xl border border-amber-200 bg-amber-50 p-4 text-xs leading-relaxed text-amber-900">
            La caja dejará de descontar unidades. El stock actual se conserva.
          </p>

          <div class="flex justify-end gap-2 border-t border-slate-100 pt-4">
            <button type="button" @click="closeTrackingModal" class="rounded-xl border border-slate-300 px-4 py-2.5 text-sm font-bold text-slate-700 hover:bg-slate-100">Cancelar</button>
            <button type="submit" :disabled="trackingForm.processing" class="rounded-xl bg-primary-700 px-4 py-2.5 text-sm font-bold text-white hover:bg-primary-800 disabled:opacity-60">{{ trackingSubmitLabel }}</button>
          </div>
        </form>
      </FloatingModal>

      <FloatingModal
        v-model:open="movementModalOpen"
        title="Actualizar stock"
        title-id="inventory-movement-title"
        :description="selectedDish ? `Registra qué pasó con las unidades de ${selectedDish.name}.` : 'Registra un movimiento de stock.'"
      >
        <form class="mx-auto max-w-lg space-y-5" @submit.prevent="saveMovement">
          <div class="grid gap-2 sm:grid-cols-3">
            <button
              v-for="type in ['entry', 'waste', 'adjustment']"
              :key="type"
              type="button"
              :class="[
                'flex flex-col items-center gap-1 rounded-xl border-2 px-3 py-3 text-center transition',
                movementForm.type === type ? 'border-primary-600 bg-primary-50 text-primary-800' : 'border-slate-200 text-slate-600 hover:bg-slate-50',
              ]"
              @click="selectMovementType(type)"
            >
              <UiIcon :name="movementActionIcons[type]" :size="18" />
              <span class="text-xs font-bold">{{ movementActionLabels[type] }}</span>
              <span class="text-[10px] font-normal opacity-70">{{ movementActionDescriptions[type] }}</span>
            </button>
          </div>

          <p class="text-xs text-slate-500">Elige qué pasó con el stock.</p>

          <div v-if="selectedDish" class="flex items-center justify-between rounded-xl bg-slate-50 px-4 py-3 text-sm">
            <span class="font-semibold text-slate-600">Stock actual</span>
            <span class="font-black text-slate-900">{{ selectedDish.stock_quantity }}</span>
          </div>

          <label class="block text-sm font-bold text-slate-700">
            {{ movementQuantityLabel }}
            <input v-model.number="movementForm.quantity" type="number" :min="movementForm.type === 'adjustment' ? 0 : 1" max="1000000" required class="mt-1 w-full rounded-xl border-slate-300 px-3 py-2.5 text-sm text-slate-800 focus:border-primary-500 focus:ring-primary-500" />
            <span v-if="movementForm.errors.quantity" class="mt-1 block text-xs text-rose-600">{{ movementForm.errors.quantity }}</span>
          </label>

          <div class="flex items-center justify-between rounded-xl border border-primary-200 bg-primary-50 px-4 py-3 text-sm">
            <span class="font-semibold text-primary-800">Stock resultante</span>
            <span class="font-black text-primary-900">{{ previewStock }}</span>
          </div>

          <label class="block text-sm font-bold text-slate-700">
            Nota (opcional)
            <textarea v-model="movementForm.notes" rows="2" maxlength="500" class="mt-1 w-full rounded-xl border-slate-300 px-3 py-2.5 text-sm text-slate-800 focus:border-primary-500 focus:ring-primary-500"></textarea>
          </label>

          <div class="flex justify-end gap-2 border-t border-slate-100 pt-4">
            <button type="button" @click="closeMovementModal" class="rounded-xl border border-slate-300 px-4 py-2.5 text-sm font-bold text-slate-700 hover:bg-slate-100">Cancelar</button>
            <button type="submit" :disabled="movementForm.processing" class="rounded-xl bg-primary-700 px-4 py-2.5 text-sm font-bold text-white hover:bg-primary-800 disabled:opacity-60">{{ movementSubmitLabel }}</button>
          </div>
        </form>
      </FloatingModal>
    </div>
  </AdminLayout>
</template>
