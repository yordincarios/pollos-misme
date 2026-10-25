<script setup>
import { computed, ref } from 'vue';
import { router, useForm, usePage } from '@inertiajs/vue3';
import AdminLayout from '../../../Layouts/AdminLayout.vue';
import LocationMap from '../../../Components/LocationMap.vue';
import FloatingModal from '../../../Components/FloatingModal.vue';
import UiIcon from '../../../Components/UiIcon.vue';
import { formatCurrency } from '../../../utils/currency.js';

const props = defineProps({
  orders: { type: Object, required: true },
  statuses: { type: Array, default: () => [] },
  filters: { type: Object, required: true },
  availableDishes: { type: Array, default: () => [] },
  stalls: { type: Array, default: () => [] },
  mapOrders: { type: Array, default: () => [] },
  paymentQrUrl: { type: String, default: null },
  summary: { type: Object, required: true },
});

const page = usePage();
const isOrderFormOpen = ref(false);
const paymentOrderId = ref(null);
const filtersForm = useForm({ status: props.filters.status || '' });
const orderForm = useForm({
  customer_name: '',
  customer_phone: '',
  delivery_address: '',
  latitude: '',
  longitude: '',
  notes: '',
  items: [{ dish_id: '', quantity: 1, notes: '' }],
});
const paymentForm = useForm({
  payment_method: 'cash',
  cash_received: '',
  qr_confirmed: false,
});

const statusLabels = {
  received: 'Recibido',
  preparing: 'En preparación',
  ready: 'Listo para entregar',
  delivered: 'Entregado',
  cancelled: 'Cancelado',
};

const statusClasses = {
  received: 'bg-amber-50 text-amber-800',
  preparing: 'bg-blue-50 text-blue-800',
  ready: 'bg-emerald-50 text-emerald-800',
  delivered: 'bg-slate-100 text-slate-700',
  cancelled: 'bg-rose-50 text-rose-700',
};

const overviewMapMarkers = computed(() => {
  const stallMarkers = props.stalls.map((stall) => ({
    id: `stall-${stall.id}`,
    label: stall.name,
    description: stall.address || 'Puesto fijo',
    latitude: stall.latitude,
    longitude: stall.longitude,
    kind: 'stall',
  }));
  const orderMarkers = props.mapOrders.map((order) => ({
    id: `order-${order.id}`,
    label: `Pedido ${order.order_number}`,
    description: `${order.customer_name} · ${order.delivery_address}`,
    latitude: order.latitude,
    longitude: order.longitude,
    kind: 'order',
  }));

  return [...stallMarkers, ...orderMarkers];
});

const mapMarkers = computed(() => {
  const markers = [...overviewMapMarkers.value];

  if (orderForm.latitude !== '' && orderForm.longitude !== '') {
    markers.push({
      id: 'selected-order-location',
      label: orderForm.customer_name || 'Ubicación del pedido',
      description: orderForm.delivery_address || 'Ubicación seleccionada',
      latitude: orderForm.latitude,
      longitude: orderForm.longitude,
      kind: 'selected',
    });
  }

  return markers;
});

const orderTotal = computed(() => orderForm.items.reduce((totalCents, item) => {
  const dish = props.availableDishes.find((availableDish) => String(availableDish.id) === String(item.dish_id));

  return totalCents + (dish ? Math.round(Number(dish.price) * 100) * Number(item.quantity || 0) : 0);
}, 0) / 100);

const addItem = () => {
  orderForm.items.push({ dish_id: '', quantity: 1, notes: '' });
};

const removeItem = (index) => {
  if (orderForm.items.length > 1) {
    orderForm.items.splice(index, 1);
  }
};

const availableDishesForItem = (itemIndex) => props.availableDishes.filter((dish) => !orderForm.items.some((item, index) => (
  index !== itemIndex && String(item.dish_id) === String(dish.id)
)));

const submitOrder = () => {
  orderForm.post('/admin/orders', {
    preserveScroll: true,
    onSuccess: () => {
      orderForm.reset();
      orderForm.items = [{ dish_id: '', quantity: 1, notes: '' }];
      isOrderFormOpen.value = false;
    },
  });
};

const openOrderForm = () => {
  isOrderFormOpen.value = true;
};

const setLocation = ({ latitude, longitude }) => {
  orderForm.latitude = latitude.toFixed(7);
  orderForm.longitude = longitude.toFixed(7);
};

const applyStatusFilter = () => {
  filtersForm.get('/admin/orders', {
    preserveScroll: true,
    preserveState: true,
    replace: true,
  });
};

const updateStatus = (order, status) => {
  router.patch(`/admin/orders/${order.id}/status`, { status }, { preserveScroll: true });
};

const beginPayment = (order) => {
  paymentOrderId.value = order.id;
  paymentForm.reset();
  paymentForm.payment_method = 'cash';
  paymentForm.cash_received = Number(order.total).toFixed(2);
  paymentForm.qr_confirmed = false;
};

const cancelPayment = () => {
  paymentOrderId.value = null;
  paymentForm.reset();
  paymentForm.clearErrors();
};

const submitPayment = (order) => {
  paymentForm.post(`/admin/orders/${order.id}/payment`, {
    preserveScroll: true,
    onSuccess: () => cancelPayment(),
  });
};

const goToPage = (url) => {
  if (url) {
    router.visit(url, { preserveScroll: true, preserveState: true });
  }
};
</script>

<template>
  <AdminLayout title="Pedidos y mapa" description="Pedidos recibidos por WhatsApp">
    <div class="mx-auto max-w-7xl space-y-6">
      <section class="flex flex-wrap items-end justify-between gap-4">
        <div>
          <p class="text-xs font-extrabold uppercase tracking-[0.16em] text-emerald-700">Operaciones</p>
          <h1 class="mt-1 text-2xl font-black tracking-tight text-slate-900 sm:text-3xl">Pedidos de WhatsApp</h1>
          <p class="mt-2 max-w-2xl text-sm text-slate-600">Registra manualmente el pedido y su ubicación. El mapa también muestra los puestos fijos.</p>
        </div>
        <button type="button" @click="openOrderForm" class="inline-flex min-h-11 items-center gap-2 rounded-xl bg-emerald-700 px-4 py-2.5 text-sm font-bold text-white transition hover:bg-emerald-800">
          <UiIcon name="plus" :size="17" />
          Registrar pedido
        </button>
      </section>

      <p v-if="page.props.flash?.success" class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-800" role="status">
        {{ page.props.flash.success }}
      </p>

      <section class="grid gap-4 sm:grid-cols-3" aria-label="Resumen de pedidos">
        <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
          <p class="text-sm font-semibold text-slate-500">Pedidos abiertos</p>
          <p class="mt-2 text-2xl font-black text-slate-900">{{ summary.open }}</p>
        </article>
        <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
          <p class="text-sm font-semibold text-slate-500">Pendientes de cobro</p>
          <p class="mt-2 text-2xl font-black text-amber-800">{{ summary.awaitingPayment }}</p>
        </article>
        <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
          <p class="text-sm font-semibold text-slate-500">Registrados en caja</p>
          <p class="mt-2 text-2xl font-black text-emerald-700">{{ summary.paid }}</p>
        </article>
      </section>

      <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
        <div class="mb-4 flex flex-wrap items-start justify-between gap-3">
          <div>
            <h2 class="font-extrabold text-slate-900">Mapa operativo</h2>
            <p class="mt-1 text-xs text-slate-500">Azul: puesto fijo · Terracota: pedido abierto · Verde: nueva ubicación</p>
          </div>
          <span class="text-[10px] text-slate-500">© OpenStreetMap contributors</span>
        </div>
        <LocationMap :markers="overviewMapMarkers" />
      </section>

      <FloatingModal
        v-model:open="isOrderFormOpen"
        title="Registrar pedido de WhatsApp"
        title-id="whatsapp-order-modal-title"
        description="Completa los datos del cliente; el mapa queda dentro de esta ventana para marcar la entrega sin perderlo."
      >
        <div class="grid gap-6 xl:grid-cols-[minmax(0,1.2fr)_minmax(20rem,0.8fr)]">
        <aside class="order-1 space-y-3 xl:order-2">
          <div>
            <h3 class="text-sm font-extrabold text-slate-900">Ubicación de entrega</h3>
            <p class="mt-1 text-xs text-slate-500">Toca el mapa para marcar el punto del cliente.</p>
          </div>
          <LocationMap :markers="mapMarkers" pick-location compact @location-selected="setLocation" />
        </aside>
        <div class="order-2 xl:order-1">
        <div class="mb-5">
          <p class="text-xs font-extrabold uppercase tracking-wide text-emerald-700">Captura manual</p>
          <p class="mt-1 text-sm text-slate-600">Los precios se toman del catálogo actual; el sistema guarda una copia en el pedido.</p>
        </div>

        <form class="space-y-5" @submit.prevent="submitOrder">
          <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
            <label class="block text-sm font-bold text-slate-700">
              Nombre del cliente
              <input v-model="orderForm.customer_name" required maxlength="255" class="mt-1 w-full rounded-xl border-slate-300 bg-white px-3 py-2.5 text-sm text-slate-800 focus:border-emerald-500 focus:ring-emerald-500" />
              <span v-if="orderForm.errors.customer_name" class="mt-1 block text-xs text-rose-600">{{ orderForm.errors.customer_name }}</span>
            </label>
            <label class="block text-sm font-bold text-slate-700">
              Teléfono / WhatsApp
              <input v-model="orderForm.customer_phone" type="tel" maxlength="30" class="mt-1 w-full rounded-xl border-slate-300 bg-white px-3 py-2.5 text-sm text-slate-800 focus:border-emerald-500 focus:ring-emerald-500" />
              <span v-if="orderForm.errors.customer_phone" class="mt-1 block text-xs text-rose-600">{{ orderForm.errors.customer_phone }}</span>
            </label>
            <label class="block text-sm font-bold text-slate-700 md:col-span-2 xl:col-span-1">
              Dirección de entrega
              <input v-model="orderForm.delivery_address" required maxlength="1000" class="mt-1 w-full rounded-xl border-slate-300 bg-white px-3 py-2.5 text-sm text-slate-800 focus:border-emerald-500 focus:ring-emerald-500" />
              <span v-if="orderForm.errors.delivery_address" class="mt-1 block text-xs text-rose-600">{{ orderForm.errors.delivery_address }}</span>
            </label>
          </div>

          <div class="grid gap-3 sm:grid-cols-2">
            <label class="block text-xs font-bold text-slate-600">
              Latitud (opcional)
              <input v-model="orderForm.latitude" type="number" step="0.0000001" min="-90" max="90" class="mt-1 w-full rounded-lg border-slate-300 px-3 py-2 text-sm text-slate-800 focus:border-emerald-500 focus:ring-emerald-500" />
              <span v-if="orderForm.errors.latitude" class="mt-1 block text-[11px] text-rose-600">{{ orderForm.errors.latitude }}</span>
            </label>
            <label class="block text-xs font-bold text-slate-600">
              Longitud (opcional)
              <input v-model="orderForm.longitude" type="number" step="0.0000001" min="-180" max="180" class="mt-1 w-full rounded-lg border-slate-300 px-3 py-2 text-sm text-slate-800 focus:border-emerald-500 focus:ring-emerald-500" />
              <span v-if="orderForm.errors.longitude" class="mt-1 block text-[11px] text-rose-600">{{ orderForm.errors.longitude }}</span>
            </label>
          </div>

          <label class="block text-sm font-bold text-slate-700">
            Notas del pedido
            <textarea v-model="orderForm.notes" rows="2" maxlength="2000" placeholder="Referencias, instrucciones o notas de WhatsApp" class="mt-1 w-full rounded-xl border-slate-300 bg-white px-3 py-2.5 text-sm text-slate-800 focus:border-emerald-500 focus:ring-emerald-500"></textarea>
            <span v-if="orderForm.errors.notes" class="mt-1 block text-xs text-rose-600">{{ orderForm.errors.notes }}</span>
          </label>

          <div class="space-y-3 border-t border-slate-100 pt-5">
            <div class="flex flex-wrap items-center justify-between gap-3">
              <div>
                <h3 class="font-extrabold text-slate-900">Platos solicitados</h3>
                <p v-if="!availableDishes.length" class="mt-1 text-xs text-amber-700">No hay platos disponibles en el catálogo.</p>
              </div>
              <button type="button" @click="addItem" :disabled="availableDishes.length <= orderForm.items.length" class="rounded-lg border border-slate-300 px-3 py-2 text-xs font-bold text-slate-700 hover:bg-slate-100 disabled:cursor-not-allowed disabled:opacity-50">Agregar plato</button>
            </div>

            <div v-for="(item, index) in orderForm.items" :key="index" class="grid gap-3 rounded-xl border border-slate-200 p-3 sm:grid-cols-[minmax(0,1fr)_7rem_3rem]">
              <label class="block text-xs font-bold text-slate-600">
                Plato
                <select v-model="item.dish_id" required class="mt-1 w-full rounded-lg border-slate-300 bg-white px-3 py-2.5 text-sm text-slate-800 focus:border-emerald-500 focus:ring-emerald-500">
                  <option value="" disabled>Selecciona un plato</option>
                  <option v-for="dish in availableDishesForItem(index)" :key="dish.id" :value="dish.id">{{ dish.name }} · {{ formatCurrency(dish.price) }}</option>
                </select>
                <span v-if="orderForm.errors[`items.${index}.dish_id`]" class="mt-1 block text-[11px] text-rose-600">{{ orderForm.errors[`items.${index}.dish_id`] }}</span>
              </label>
              <label class="block text-xs font-bold text-slate-600">
                Cantidad
                <input v-model.number="item.quantity" type="number" min="1" max="100" required class="mt-1 w-full rounded-lg border-slate-300 px-3 py-2.5 text-sm text-slate-800 focus:border-emerald-500 focus:ring-emerald-500" />
                <span v-if="orderForm.errors[`items.${index}.quantity`]" class="mt-1 block text-[11px] text-rose-600">{{ orderForm.errors[`items.${index}.quantity`] }}</span>
              </label>
              <button v-if="orderForm.items.length > 1" type="button" @click="removeItem(index)" :aria-label="`Quitar plato ${index + 1}`" class="mt-5 flex h-10 w-10 items-center justify-center rounded-lg text-rose-700 hover:bg-rose-50">
                <UiIcon name="trash" :size="17" />
              </button>
              <label class="block text-xs font-bold text-slate-600 sm:col-span-3">
                Nota para este plato (opcional)
                <input v-model="item.notes" maxlength="500" class="mt-1 w-full rounded-lg border-slate-300 px-3 py-2 text-sm text-slate-800 focus:border-emerald-500 focus:ring-emerald-500" />
                <span v-if="orderForm.errors[`items.${index}.notes`]" class="mt-1 block text-[11px] text-rose-600">{{ orderForm.errors[`items.${index}.notes`] }}</span>
              </label>
            </div>
            <p v-if="orderForm.errors.items" class="text-sm text-rose-600">{{ orderForm.errors.items }}</p>

            <div class="flex flex-wrap items-center justify-between gap-3 rounded-xl bg-slate-50 px-4 py-3">
              <span class="font-bold text-slate-700">Total estimado del pedido</span>
              <span class="text-xl font-black text-slate-900">{{ formatCurrency(orderTotal) }}</span>
            </div>
          </div>

          <div class="flex justify-end gap-2 border-t border-slate-100 pt-4">
            <button type="button" @click="isOrderFormOpen = false" class="rounded-lg border border-slate-300 px-4 py-2.5 text-sm font-bold text-slate-700 hover:bg-slate-100">Cancelar</button>
            <button type="submit" :disabled="orderForm.processing || !availableDishes.length" class="rounded-lg bg-emerald-700 px-5 py-2.5 text-sm font-bold text-white hover:bg-emerald-800 disabled:cursor-wait disabled:opacity-60">
              {{ orderForm.processing ? 'Guardando…' : 'Guardar pedido' }}
            </button>
          </div>
        </form>
        </div>
        </div>
      </FloatingModal>

      <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
        <header class="flex flex-wrap items-end justify-between gap-3 border-b border-slate-200 px-5 py-4">
          <div>
            <h2 class="font-extrabold text-slate-900">Pedidos registrados</h2>
            <p class="mt-1 text-xs text-slate-500">{{ orders.total }} en total</p>
          </div>
          <form class="flex items-end gap-2" @submit.prevent="applyStatusFilter">
            <label class="grid gap-1 text-xs font-bold text-slate-600">
              Estado
              <select v-model="filtersForm.status" class="min-h-10 rounded-lg border-slate-300 bg-white px-3 text-sm text-slate-800 focus:border-emerald-500 focus:ring-emerald-500">
                <option value="">Todos</option>
                <option v-for="status in statuses" :key="status" :value="status">{{ statusLabels[status] }}</option>
              </select>
            </label>
            <button type="submit" :disabled="filtersForm.processing" class="min-h-10 rounded-lg bg-slate-900 px-3 text-sm font-bold text-white hover:bg-slate-800 disabled:opacity-60">Filtrar</button>
          </form>
        </header>

        <div v-if="orders.data.length" class="divide-y divide-slate-100">
          <article v-for="order in orders.data" :key="order.id" class="space-y-4 p-5">
            <div class="flex flex-wrap items-start justify-between gap-3">
              <div class="min-w-0">
                <div class="flex flex-wrap items-center gap-2">
                  <h3 class="font-mono text-sm font-black text-slate-900">{{ order.order_number }}</h3>
                  <span :class="['rounded-full px-2.5 py-1 text-[11px] font-bold', statusClasses[order.status]]">{{ statusLabels[order.status] }}</span>
                  <span :class="['rounded-full px-2.5 py-1 text-[11px] font-bold', order.sale_id ? 'bg-emerald-50 text-emerald-800' : 'bg-amber-50 text-amber-800']">{{ order.sale_id ? `Cobrado · ${order.sale?.ticket_number || ''}` : 'Pendiente de cobro' }}</span>
                </div>
                <p class="mt-2 text-sm font-bold text-slate-800">{{ order.customer_name }}<span v-if="order.customer_phone" class="ml-2 font-normal text-slate-500">{{ order.customer_phone }}</span></p>
                <p class="mt-1 text-sm text-slate-600">{{ order.delivery_address }}</p>
                <p v-if="order.notes" class="mt-1 text-xs text-slate-500">Nota: {{ order.notes }}</p>
              </div>
              <div class="text-right">
                <p class="text-xs text-slate-500">{{ new Date(order.created_at).toLocaleString('es-BO', { dateStyle: 'short', timeStyle: 'short' }) }}</p>
                <p class="mt-1 text-lg font-black text-slate-900">{{ formatCurrency(order.total) }}</p>
              </div>
            </div>

            <div class="rounded-xl bg-slate-50 p-3">
              <ul class="space-y-1.5 text-sm">
                <li v-for="item in order.items" :key="item.id" class="flex flex-wrap justify-between gap-2">
                  <span class="font-semibold text-slate-700">{{ item.quantity }} × {{ item.dish_name }}<span v-if="item.notes" class="ml-1 text-xs font-normal text-slate-500">({{ item.notes }})</span></span>
                  <span class="font-bold text-slate-800">{{ formatCurrency(item.subtotal) }}</span>
                </li>
              </ul>
            </div>

            <div class="flex flex-wrap items-end justify-between gap-3">
              <label class="grid gap-1 text-xs font-bold text-slate-600">
                Estado del pedido
                <select :value="order.status" :disabled="order.sale_id && order.status === 'cancelled'" @change="updateStatus(order, $event.target.value)" class="min-h-10 rounded-lg border-slate-300 bg-white px-3 text-sm text-slate-800 focus:border-emerald-500 focus:ring-emerald-500">
                  <option v-for="status in statuses.filter((value) => !order.sale_id || value !== 'cancelled')" :key="status" :value="status">{{ statusLabels[status] }}</option>
                </select>
              </label>

              <button v-if="!order.sale_id && order.status !== 'cancelled' && paymentOrderId !== order.id" type="button" @click="beginPayment(order)" class="rounded-lg bg-emerald-700 px-4 py-2.5 text-sm font-bold text-white hover:bg-emerald-800">Cobrar pedido</button>
            </div>

            <form v-if="paymentOrderId === order.id" class="grid gap-3 rounded-xl border border-emerald-200 bg-emerald-50/60 p-4 sm:grid-cols-[1fr_1fr_auto] sm:items-end" @submit.prevent="submitPayment(order)">
              <label class="grid gap-1 text-xs font-bold text-slate-700">
                Método de pago
                <select v-model="paymentForm.payment_method" class="min-h-10 rounded-lg border-slate-300 bg-white px-3 text-sm text-slate-800 focus:border-emerald-500 focus:ring-emerald-500">
                  <option value="cash">Efectivo</option>
                  <option value="qr" :disabled="!paymentQrUrl">{{ paymentQrUrl ? 'QR' : 'QR (sin configurar)' }}</option>
                </select>
                <span v-if="!paymentQrUrl" class="text-[11px] text-amber-700">Configura el QR desde Ajustes del POS para habilitar este pago.</span>
              </label>
              <label v-if="paymentForm.payment_method === 'cash'" class="grid gap-1 text-xs font-bold text-slate-700">
                Efectivo recibido
                <input v-model="paymentForm.cash_received" type="number" min="0" step="0.01" required class="min-h-10 rounded-lg border-slate-300 bg-white px-3 text-sm text-slate-800 focus:border-emerald-500 focus:ring-emerald-500" />
                <span v-if="paymentForm.errors.cash_received" class="text-[11px] text-rose-600">{{ paymentForm.errors.cash_received }}</span>
              </label>
              <label v-else class="flex min-h-10 items-center gap-2 text-xs font-semibold text-slate-700">
                <input v-model="paymentForm.qr_confirmed" type="checkbox" class="rounded border-slate-300 text-emerald-600 focus:ring-emerald-500" />
                Confirmé manualmente el pago QR
              </label>
              <div v-if="paymentForm.payment_method === 'qr' && paymentQrUrl" class="flex items-center gap-3 rounded-lg border border-slate-200 bg-white p-3 sm:col-span-2">
                <img :src="paymentQrUrl" alt="QR de pago del comercio" class="h-24 w-24 rounded bg-white object-contain p-1" />
                <p class="text-sm font-bold text-slate-700">El cliente paga {{ formatCurrency(order.total) }}. Si su banco lo pide, debe ingresar ese importe. Confirma el abono antes de continuar.</p>
              </div>
              <span v-if="paymentForm.errors.qr_confirmed" class="text-xs text-rose-600 sm:col-span-2">{{ paymentForm.errors.qr_confirmed }}</span>
              <span v-if="paymentForm.errors.payment_method" class="text-xs text-rose-600 sm:col-span-2">{{ paymentForm.errors.payment_method }}</span>
              <div class="flex gap-2 sm:justify-end">
                <button type="button" @click="cancelPayment" class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs font-bold text-slate-700">Cancelar</button>
                <button type="submit" :disabled="paymentForm.processing" class="rounded-lg bg-emerald-700 px-3 py-2 text-xs font-bold text-white disabled:opacity-60">{{ paymentForm.processing ? 'Procesando…' : 'Confirmar cobro' }}</button>
              </div>
            </form>
          </article>
        </div>
        <p v-else class="px-5 py-12 text-center text-sm text-slate-500">No hay pedidos que coincidan con este filtro.</p>

        <div v-if="orders.last_page > 1" class="flex items-center justify-between border-t border-slate-200 px-5 py-3">
          <button type="button" :disabled="!orders.prev_page_url" @click="goToPage(orders.prev_page_url)" class="rounded-lg px-3 py-2 text-sm font-bold text-slate-700 hover:bg-slate-100 disabled:cursor-not-allowed disabled:opacity-40">Anterior</button>
          <span class="text-xs font-semibold text-slate-500">Página {{ orders.current_page }} de {{ orders.last_page }}</span>
          <button type="button" :disabled="!orders.next_page_url" @click="goToPage(orders.next_page_url)" class="rounded-lg px-3 py-2 text-sm font-bold text-slate-700 hover:bg-slate-100 disabled:cursor-not-allowed disabled:opacity-40">Siguiente</button>
        </div>
      </section>
    </div>
  </AdminLayout>
</template>
