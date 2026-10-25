<script setup>
import { ref, computed, onMounted, onUnmounted, watch } from 'vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import DishSettingsModal from './DishSettingsModal.vue';
import TicketReceiptModal from './TicketReceiptModal.vue';
import { formatCurrency } from '../../utils/currency.js';
import UiIcon from '../../Components/UiIcon.vue';

const props = defineProps({
  categories: {
    type: Array,
    default: () => [],
  },
  dishes: {
    type: Array,
    default: () => [],
  },
  archivedDishes: {
    type: Array,
    default: () => [],
  },
  paymentQrUrl: {
    type: String,
    default: null,
  },
});

const page = usePage();
const currentUser = computed(() => page.props.auth?.user);
const activeMobilePanel = ref('catalog');
const isOrderExpanded = ref(false);
const isDarkMode = ref(false);
const isSuccessDismissed = ref(false);
const successMessage = computed(() => page.props.flash?.lastSale ? '' : page.props.flash?.success || '');

watch(() => page.props.flash?.success, () => {
  isSuccessDismissed.value = false;
});

// State
const searchQuery = ref('');
const selectedCategoryId = ref('all');
const showSettingsModal = ref(false);
const settingsInitialTab = ref('new');
const showReceiptModal = ref(false);
const completedSale = ref(null);
const editingNoteIndex = ref(null);
const noteInput = ref('');
const isSubmitting = ref(false);

// Live Clock
const currentTime = ref('');
let timer = null;
const updateClock = () => {
  const now = new Date();
  currentTime.value = now.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit', second: '2-digit' });
};

onMounted(() => {
  try {
    isDarkMode.value = localStorage.getItem('restaurante2-theme') === 'dark';
  } catch {
    isDarkMode.value = false;
  }
  document.documentElement.classList.toggle('dark', isDarkMode.value);
  updateClock();
  timer = setInterval(updateClock, 1000);
});

onUnmounted(() => {
  if (timer) clearInterval(timer);
});

// Watch flash data for completed sales
watch(
  () => page.props.flash?.lastSale,
  (sale) => {
    if (sale) {
      completedSale.value = sale;
      showReceiptModal.value = true;
      clearOrder();
    }
  },
  { immediate: true }
);

// Order / Cart State
const orderItems = ref([]);
const cashReceived = ref('');
const paymentMethod = ref('cash');
const isQrConfirmed = ref(false);

// Filtered Dishes
const filteredDishes = computed(() => {
  return props.dishes.filter((dish) => {
    const matchesCategory =
      selectedCategoryId.value === 'all' || dish.category_id === Number(selectedCategoryId.value);
    const matchesSearch =
      dish.name.toLowerCase().includes(searchQuery.value.toLowerCase()) ||
      (dish.category?.name && dish.category.name.toLowerCase().includes(searchQuery.value.toLowerCase()));
    return matchesCategory && matchesSearch;
  });
});

// Category dish counts
const getCategoryCount = (catId) => {
  if (catId === 'all') return props.dishes.length;
  return props.dishes.filter((d) => d.category_id === catId).length;
};

// Cart Calculations
const orderTotal = computed(() => {
  return orderItems.value.reduce((acc, item) => acc + item.quantity * Number(item.price), 0);
});

const changeGiven = computed(() => {
  if (paymentMethod.value !== 'cash') return 0;
  const cash = parseFloat(cashReceived.value) || 0;
  return cash >= orderTotal.value ? cash - orderTotal.value : 0;
});

const isCashSufficient = computed(() => {
  if (paymentMethod.value !== 'cash') return true;
  const cash = parseFloat(cashReceived.value) || 0;
  return cash >= orderTotal.value && orderTotal.value > 0;
});

const canProcessSale = computed(() => (
  orderItems.value.length > 0
  && ! isSubmitting.value
  && (paymentMethod.value === 'cash'
    ? isCashSufficient.value
    : Boolean(props.paymentQrUrl) && isQrConfirmed.value)
));

const checkoutButtonLabel = computed(() => {
  if (isSubmitting.value) return 'Procesando…';
  if (orderItems.value.length === 0) return 'Agrega platos';

  if (paymentMethod.value === 'cash') {
    if (!cashReceived.value) return 'Ingresa el efectivo';
    return isCashSufficient.value ? 'Realizar cobro' : 'Falta efectivo';
  }

  if (! props.paymentQrUrl) return 'Configura el QR en Ajustes';
  return isQrConfirmed.value ? 'Registrar pago QR' : 'Confirma el pago QR';
});

// Cart Actions
const addToOrder = (dish) => {
  if (!dish.is_available) return;

  const existingIndex = orderItems.value.findIndex((item) => item.dish_id === dish.id);
  if (existingIndex > -1) {
    orderItems.value[existingIndex].quantity++;
  } else {
    orderItems.value.push({
      dish_id: dish.id,
      name: dish.name,
      price: Number(dish.price),
      quantity: 1,
      notes: '',
      image_url: dish.image_url,
    });
  }
};

const increaseQuantity = (index) => {
  orderItems.value[index].quantity++;
};

const decreaseQuantity = (index) => {
  if (orderItems.value[index].quantity > 1) {
    orderItems.value[index].quantity--;
  } else {
    removeItem(index);
  }
};

const removeItem = (index) => {
  orderItems.value.splice(index, 1);
};

const clearOrder = () => {
  orderItems.value = [];
  cashReceived.value = '';
  paymentMethod.value = 'cash';
  isQrConfirmed.value = false;
  editingNoteIndex.value = null;
  noteInput.value = '';
};

// Notes Management
const openNoteModal = (index) => {
  editingNoteIndex.value = index;
  noteInput.value = orderItems.value[index].notes || '';
};

const saveNote = (index) => {
  orderItems.value[index].notes = noteInput.value.trim();
  editingNoteIndex.value = null;
  noteInput.value = '';
};

const cancelNote = () => {
  editingNoteIndex.value = null;
  noteInput.value = '';
};

// Quick Cash Helpers
const setExactCash = () => {
  cashReceived.value = orderTotal.value.toFixed(2);
};

const addCashPreset = (amount) => {
  const current = parseFloat(cashReceived.value) || 0;
  cashReceived.value = (current + amount).toFixed(2);
};

const selectPaymentMethod = (method) => {
  if (method === 'qr' && ! props.paymentQrUrl) {
    return;
  }

  paymentMethod.value = method;
  isQrConfirmed.value = false;
};

const openCatalogSettings = () => {
  settingsInitialTab.value = 'new';
  showSettingsModal.value = true;
};

const openPaymentSettings = () => {
  settingsInitialTab.value = 'payment';
  showSettingsModal.value = true;
};

// Submit Order
const processSale = () => {
  if (! canProcessSale.value) return;

  isSubmitting.value = true;

  router.post(
    '/pos/sales',
    {
      items: orderItems.value.map((item) => ({
        dish_id: item.dish_id,
        quantity: item.quantity,
        notes: item.notes,
      })),
      cash_received: paymentMethod.value === 'cash' ? parseFloat(cashReceived.value) : null,
      payment_method: paymentMethod.value,
      qr_confirmed: paymentMethod.value === 'qr' ? isQrConfirmed.value : null,
    },
    {
      preserveScroll: true,
      onFinish: () => {
        isSubmitting.value = false;
      },
    }
  );
};

const toggleDarkMode = () => {
  isDarkMode.value = !isDarkMode.value;
  document.documentElement.classList.toggle('dark', isDarkMode.value);

  try {
    localStorage.setItem('restaurante2-theme', isDarkMode.value ? 'dark' : 'light');
  } catch {
    // Theme still applies for the current session if storage is unavailable.
  }
};

const logout = () => router.post('/logout');

</script>

<template>
  <Head title="Restaurante 2 - POS" />

  <div class="flex h-dvh flex-col overflow-hidden bg-slate-100 font-sans">
    <!-- Top Bar -->
    <header class="z-20 flex h-16 shrink-0 items-center justify-between border-b border-slate-200 bg-white px-3 shadow-xs sm:px-6">
      <div class="flex min-w-0 items-center gap-3 sm:gap-4">
        <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-emerald-600 text-white shadow-md sm:h-10 sm:w-10">
          <UiIcon name="utensils" :size="22" />
        </div>
        <div>
          <h1 class="text-base font-black tracking-tight text-slate-900 sm:text-xl">Restaurante 2 - POS</h1>
          <div class="flex items-center gap-1.5 text-[10px] font-medium text-slate-500 sm:gap-2 sm:text-xs">
            <span class="inline-flex h-2 w-2 rounded-full bg-emerald-500 animate-pulse"></span>
            <span>Sistema en línea</span>
            <span>•</span>
            <span class="font-mono text-slate-600">{{ currentTime }}</span>
          </div>
        </div>
      </div>

      <!-- Header Actions -->
      <div class="flex items-center gap-3">
        <div class="hidden md:flex items-center gap-2 bg-slate-50 px-3 py-1.5 rounded-xl border border-slate-200 text-xs font-semibold text-slate-600">
          <span>Platos: <strong class="text-slate-900">{{ dishes.length }}</strong></span>
          <span>|</span>
          <span>Categorías: <strong class="text-slate-900">{{ categories.length }}</strong></span>
        </div>

        <button
          type="button"
          @click="toggleDarkMode"
          :aria-label="isDarkMode ? 'Cambiar a modo claro' : 'Cambiar a modo noche'"
          :title="isDarkMode ? 'Cambiar a modo claro' : 'Cambiar a modo noche'"
          class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl border border-slate-200 bg-white text-slate-700 transition hover:bg-slate-100 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500"
        >
          <UiIcon :name="isDarkMode ? 'sun' : 'moon'" :size="19" />
        </button>

        <button
          type="button"
          @click="openCatalogSettings"
          class="flex shrink-0 items-center gap-2 rounded-xl bg-slate-900 px-3 py-2.5 text-sm font-bold text-white shadow-md transition hover:bg-slate-800 active:scale-95 sm:px-4"
        >
          <UiIcon name="settings" :size="18" />
          <span class="hidden sm:inline">Ajustes</span>
        </button>
        <span class="hidden max-w-28 truncate text-xs font-semibold text-slate-600 xl:block">{{ currentUser?.name }}</span>
        <button
          type="button"
          @click="logout"
          aria-label="Cerrar sesión"
          title="Cerrar sesión"
          class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl border border-slate-200 bg-white text-slate-600 transition hover:bg-rose-50 hover:text-rose-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-rose-500"
        >
          <UiIcon name="logout" :size="18" />
        </button>
      </div>
    </header>

    <div v-if="Object.keys(page.props.errors || {}).length" class="z-10 flex shrink-0 items-center justify-between gap-3 bg-rose-50 px-5 py-2 text-sm font-semibold text-rose-800" role="alert">
      <span>{{ Object.values(page.props.errors)[0] }}</span>
    </div>

    <div v-if="successMessage && !isSuccessDismissed" class="fixed right-4 top-20 z-[60] flex max-w-[calc(100vw-2rem)] items-start gap-3 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-900 shadow-lg" role="status" aria-live="polite">
      <span>{{ successMessage }}</span>
      <button type="button" class="shrink-0 font-black text-emerald-800" aria-label="Cerrar aviso" @click="isSuccessDismissed = true">✕</button>
    </div>

    <!-- Main Workspace: Split View -->
    <div class="flex min-h-0 flex-1 flex-col overflow-hidden md:flex-row">
      <nav class="grid shrink-0 grid-cols-2 gap-2 border-b border-slate-200 bg-white p-2 md:hidden" aria-label="Cambiar panel del punto de venta">
        <button
          type="button"
          :aria-pressed="activeMobilePanel === 'catalog'"
          @click="activeMobilePanel = 'catalog'"
          :class="['rounded-xl px-3 py-2.5 text-sm font-bold', activeMobilePanel === 'catalog' ? 'bg-emerald-600 text-white' : 'bg-slate-100 text-slate-700']"
        >
          <span class="inline-flex items-center justify-center gap-2"><UiIcon name="plate" :size="16" /> Catálogo</span>
        </button>
        <button
          type="button"
          :aria-pressed="activeMobilePanel === 'order'"
          @click="activeMobilePanel = 'order'"
          :class="['rounded-xl px-3 py-2.5 text-sm font-bold', activeMobilePanel === 'order' ? 'bg-emerald-600 text-white' : 'bg-slate-100 text-slate-700']"
        >
          <span class="inline-flex items-center justify-center gap-2"><UiIcon name="receipt" :size="16" /> Orden<span v-if="orderItems.length">({{ orderItems.reduce((count, item) => count + item.quantity, 0) }})</span></span>
        </button>
      </nav>

      <!-- Left Panel: Catalog (60-65% width) -->
      <main :class="['min-h-0 min-w-0 flex-1 flex-col overflow-hidden border-r border-slate-200 bg-slate-50', activeMobilePanel === 'catalog' ? 'flex' : 'hidden', 'md:flex']" aria-label="Catálogo de platillos">
        <!-- Search and Filter Bar -->
        <div class="shrink-0 space-y-3 border-b border-slate-200 bg-white p-4 shadow-2xs">
          <!-- Big Search Bar -->
          <div class="relative">
            <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-4 text-slate-400">
              <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
              </svg>
            </div>
            <input
              v-model="searchQuery"
              type="text"
              placeholder="Buscar platillo por nombre o categoría..."
              class="w-full rounded-2xl border-2 border-slate-200 bg-slate-50/50 py-3.5 pl-12 pr-10 text-base font-semibold text-slate-800 placeholder-slate-400 shadow-inner focus:border-emerald-500 focus:bg-white focus:outline-hidden transition"
            />
            <button
              v-if="searchQuery"
              @click="searchQuery = ''"
              class="absolute inset-y-0 right-0 flex items-center pr-3 text-slate-400 hover:text-slate-600"
            >
              <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
              </svg>
            </button>
          </div>

          <!-- Horizontal Category Buttons -->
          <div class="flex items-center gap-2 overflow-x-auto pb-1 scrollbar-none">
            <button
              type="button"
              @click="selectedCategoryId = 'all'"
              :class="[
                'shrink-0 px-4 py-2.5 rounded-xl text-sm font-extrabold transition shadow-xs flex items-center gap-2 cursor-pointer',
                selectedCategoryId === 'all'
                  ? 'bg-emerald-600 text-white shadow-emerald-600/20'
                  : 'bg-white text-slate-700 hover:bg-slate-100 border border-slate-200'
              ]"
            >
              <span class="flex items-center gap-2"><UiIcon name="plate" :size="17" /> Todos</span>
              <span
                :class="[
                  'px-2 py-0.5 rounded-full text-xs font-bold',
                  selectedCategoryId === 'all' ? 'bg-emerald-700 text-white' : 'bg-slate-100 text-slate-600'
                ]"
              >
                {{ getCategoryCount('all') }}
              </span>
            </button>

            <button
              v-for="cat in categories"
              :key="cat.id"
              type="button"
              @click="selectedCategoryId = cat.id"
              :class="[
                'shrink-0 px-4 py-2.5 rounded-xl text-sm font-extrabold transition shadow-xs flex items-center gap-2 cursor-pointer',
                selectedCategoryId === cat.id
                  ? 'bg-emerald-600 text-white shadow-emerald-600/20'
                  : 'bg-white text-slate-700 hover:bg-slate-100 border border-slate-200'
              ]"
            >
              <span>{{ cat.name }}</span>
              <span
                :class="[
                  'px-2 py-0.5 rounded-full text-xs font-bold',
                  selectedCategoryId === cat.id ? 'bg-emerald-700 text-white' : 'bg-slate-100 text-slate-600'
                ]"
              >
                {{ getCategoryCount(cat.id) }}
              </span>
            </button>
          </div>
        </div>

        <!-- Dishes Grid Area -->
        <div class="flex-1 overflow-y-auto p-4 md:p-6">
          <div v-if="filteredDishes.length === 0" class="flex h-64 flex-col items-center justify-center text-center">
            <div class="mb-4 flex h-16 w-16 items-center justify-center rounded-2xl bg-white text-slate-400 shadow-sm ring-1 ring-slate-200">
              <UiIcon name="search" :size="30" />
            </div>
            <h3 class="text-lg font-bold text-slate-700">No se encontraron platillos</h3>
            <p class="text-sm text-slate-500 max-w-sm mt-1">
              Prueba con otro término de búsqueda o agrega nuevos platillos desde Ajustes.
            </p>
          </div>

          <div v-else :class="filteredDishes.length === 1 ? 'mx-auto grid w-full max-w-sm grid-cols-1 gap-4' : 'grid grid-cols-2 gap-3 sm:gap-4 md:grid-cols-3 xl:grid-cols-4'">
            <article
              v-for="dish in filteredDishes"
              :key="dish.id"
              :class="[
                'group relative flex flex-col justify-between overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm transition-all select-none',
                dish.is_available
                  ? 'hover:-translate-y-1 hover:border-emerald-500 hover:shadow-lg'
                  : 'opacity-50 grayscale cursor-not-allowed'
              ]"
            >
              <button
                type="button"
                class="absolute inset-0 z-10 rounded-2xl focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-inset focus-visible:ring-emerald-500 disabled:cursor-not-allowed"
                :disabled="!dish.is_available"
                :aria-label="dish.is_available ? `Agregar ${dish.name} al pedido por ${formatCurrency(dish.price)}` : `${dish.name}, agotado`"
                @click="addToOrder(dish)"
              >
                <span class="sr-only">{{ dish.is_available ? `Agregar ${dish.name} al pedido` : `${dish.name} agotado` }}</span>
              </button>

              <!-- Featured Image (140px high) -->
              <div :class="['relative w-full overflow-hidden bg-slate-100', filteredDishes.length === 1 ? 'h-48 sm:h-56' : 'h-28 sm:h-36']">
                <img
                  v-if="dish.image_url"
                  :src="dish.image_url"
                  :alt="dish.name"
                  class="h-full w-full object-cover transition duration-300 group-hover:scale-105"
                  loading="lazy"
                />
                <!-- Elegant Fallback Image -->
                <div
                  v-else
                  class="flex h-full w-full items-center justify-center bg-gradient-to-br from-emerald-50 to-slate-100 text-emerald-600 transition duration-300 group-hover:scale-105"
                >
                  <UiIcon name="plate" :size="38" />
                </div>

                <!-- Unavailable Overlay -->
                <div
                  v-if="!dish.is_available"
                  class="absolute inset-0 flex items-center justify-center bg-slate-900/70 text-white text-xs font-black uppercase tracking-wider backdrop-blur-xs"
                >
                  Agotado
                </div>
              </div>

              <!-- Dish Info -->
              <div class="flex flex-1 flex-col justify-between p-3 sm:p-3.5">
                <div>
                  <span class="text-[11px] font-bold uppercase tracking-wider text-emerald-600 block mb-0.5">
                    {{ dish.category?.name || 'General' }}
                  </span>
                  <!-- Bold Title text-base or higher -->
                  <h3 class="line-clamp-2 text-sm font-bold leading-snug text-slate-800 transition group-hover:text-emerald-700 sm:text-base">
                    {{ dish.name }}
                  </h3>
                </div>

                <!-- Footer with Price and Add Icon -->
                <div class="mt-3 flex items-center justify-between border-t border-slate-100 pt-2">
                  <span class="text-lg font-extrabold text-slate-900">
                    {{ formatCurrency(dish.price) }}
                  </span>
                  <div class="flex h-8 w-8 items-center justify-center rounded-xl bg-emerald-50 text-emerald-700 transition group-hover:bg-emerald-600 group-hover:text-white">
                    +
                  </div>
                </div>
              </div>
            </article>
          </div>
        </div>
      </main>

      <!-- Right Panel: Direct Checkout (35-40% width) -->
      <div v-if="isOrderExpanded" class="fixed inset-x-0 bottom-0 top-16 z-30 hidden bg-slate-950/45 md:block" aria-hidden="true" @click="isOrderExpanded = false"></div>

      <aside
        :class="[
          'min-h-0 min-w-0 flex-1 flex-col bg-white shadow-xl md:flex-none md:border-l md:border-slate-200',
          activeMobilePanel === 'order' ? 'flex' : 'hidden',
          'md:flex',
          isOrderExpanded
            ? 'md:fixed md:bottom-0 md:right-0 md:top-16 md:z-40 md:w-[70vw] md:max-w-4xl'
            : 'md:w-[26rem] xl:w-[30rem]'
        ]"
        aria-label="Orden actual y cobro"
      >
        <!-- Order Header -->
        <div class="flex items-center justify-between border-b border-slate-200 bg-slate-50/70 px-4 py-3 sm:px-5 sm:py-4">
          <div class="flex items-center gap-2">
            <UiIcon name="receipt" class="text-slate-500" :size="21" />
            <h2 class="text-lg font-extrabold text-slate-900">Orden Actual</h2>
            <span v-if="orderItems.length > 0" class="rounded-full bg-emerald-600 px-2.5 py-0.5 text-xs font-bold text-white">
              {{ orderItems.reduce((acc, i) => acc + i.quantity, 0) }}
            </span>
          </div>

          <div class="flex items-center gap-2">
            <button
              type="button"
              @click="isOrderExpanded = !isOrderExpanded"
              :aria-pressed="isOrderExpanded"
              :aria-label="isOrderExpanded ? 'Reducir panel de orden' : 'Expandir panel de orden'"
              :title="isOrderExpanded ? 'Reducir panel' : 'Expandir orden'"
              class="hidden h-9 w-9 items-center justify-center rounded-lg border border-slate-200 bg-white text-slate-600 transition hover:bg-slate-100 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500 md:flex"
            >
              <UiIcon :name="isOrderExpanded ? 'compress' : 'expand'" :size="17" />
            </button>
            <button
              v-if="orderItems.length > 0"
              type="button"
              @click="clearOrder"
              class="text-xs font-bold text-rose-600 transition hover:text-rose-800 hover:underline"
            >
              Limpiar Todo
            </button>
          </div>
        </div>

        <!-- Order Items Scroll Area -->
        <div class="min-h-0 flex-1 space-y-3 overflow-y-auto p-3 sm:p-4">
          <div v-if="orderItems.length === 0" class="flex flex-col items-center justify-center h-full text-center py-16 text-slate-400">
            <div class="mb-4 flex h-16 w-16 items-center justify-center rounded-2xl bg-slate-50 text-slate-400">
              <UiIcon name="cart" :size="34" />
            </div>
            <h3 class="text-base font-bold text-slate-700">Orden vacía</h3>
            <p class="text-xs text-slate-500 max-w-xs mt-1">
              Haz clic en los platillos del catálogo izquierdo para agregarlos a la orden.
            </p>
          </div>

          <div
            v-for="(item, index) in orderItems"
            :key="item.dish_id"
            class="rounded-xl border border-slate-200 bg-white px-2.5 py-2 shadow-sm transition hover:border-slate-300 sm:px-3"
          >
            <div class="flex items-center gap-2">
              <div class="min-w-0 flex-1">
                <div class="flex items-center justify-between gap-2">
                  <h4 class="truncate text-sm font-bold leading-snug text-slate-900">{{ item.name }}</h4>
                  <span class="shrink-0 text-sm font-black text-slate-900">{{ formatCurrency(item.quantity * item.price) }}</span>
                </div>
                <div class="mt-1 flex items-center justify-between gap-1">
                  <span class="text-xs font-medium text-slate-500">{{ formatCurrency(item.price) }} c/u</span>
                  <button
                    type="button"
                    @click="openNoteModal(index)"
                    class="shrink-0 rounded-md px-2 py-1 text-xs font-bold text-slate-500 transition hover:bg-slate-100 hover:text-emerald-700"
                    :aria-label="item.notes ? `Editar nota de ${item.name}` : `Agregar nota a ${item.name}`"
                  >
                    {{ item.notes ? 'Editar nota' : '+ Nota' }}
                  </button>
                </div>
              </div>
              <div class="flex shrink-0 items-center gap-1">
                <button
                  type="button"
                  @click="decreaseQuantity(index)"
                  class="flex h-9 w-9 items-center justify-center rounded-lg bg-slate-100 text-base font-bold text-slate-800 transition hover:bg-slate-200 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500"
                  :aria-label="`Disminuir ${item.name}`"
                >−</button>
                <span class="w-5 text-center text-sm font-black text-slate-900">{{ item.quantity }}</span>
                <button
                  type="button"
                  @click="increaseQuantity(index)"
                  class="flex h-9 w-9 items-center justify-center rounded-lg bg-emerald-100 text-base font-bold text-emerald-800 transition hover:bg-emerald-200 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500"
                  :aria-label="`Aumentar ${item.name}`"
                >+</button>
              </div>
              <button
                type="button"
                @click="removeItem(index)"
                class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg text-slate-400 transition hover:bg-rose-50 hover:text-rose-600 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-rose-500"
                :aria-label="`Quitar ${item.name} de la orden`"
              >
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a2 2 0 00-2 1v3M4 7h16" />
                </svg>
              </button>
            </div>

            <div v-if="item.notes" class="mt-1 flex items-center gap-1.5 rounded-md bg-amber-50 px-2 py-1 text-xs text-amber-800">
              <UiIcon name="note" class="shrink-0" :size="13" />
              <span class="truncate">{{ item.notes }}</span>
            </div>

            <div v-if="editingNoteIndex === index" class="mt-2 flex gap-2 border-t border-slate-100 pt-2">
              <input
                v-model="noteInput"
                type="text"
                placeholder="Ej. Sin cebolla, extra salsa..."
                class="flex-1 text-xs rounded-xl border border-slate-300 px-3 py-1.5 focus:border-emerald-500 focus:ring-emerald-500"
                @keyup.enter="saveNote(index)"
              />
              <button
                type="button"
                @click="saveNote(index)"
                class="px-2.5 py-1 text-xs font-bold bg-emerald-600 text-white rounded-lg hover:bg-emerald-700"
              >
                ✓
              </button>
              <button
                type="button"
                @click="cancelNote"
                class="px-2.5 py-1 text-xs font-bold bg-slate-200 text-slate-600 rounded-lg hover:bg-slate-300"
              >
                ✕
              </button>
            </div>

          </div>
        </div>

        <!-- High-Contrast Bottom Checkout Panel (Dark Background) -->
        <div class="space-y-3 border-t border-slate-800 bg-slate-900 p-3 text-white shadow-2xl sm:p-4">
          <!-- Total Line -->
          <div class="flex items-baseline justify-between border-b border-slate-800 pb-3">
            <span class="text-xs font-extrabold uppercase tracking-wider text-slate-400 sm:text-sm">Total a pagar</span>
            <!-- text-3xl Large Total -->
            <span class="text-3xl font-black text-emerald-400 tracking-tight">
              {{ formatCurrency(orderTotal) }}
            </span>
          </div>

          <!-- Payment Method Toggle -->
          <div class="grid grid-cols-2 gap-2" role="group" aria-label="Método de pago">
            <button
              type="button"
              @click="selectPaymentMethod('cash')"
              :aria-pressed="paymentMethod === 'cash'"
              :class="[
                'flex min-h-12 items-center justify-center gap-1.5 rounded-xl px-2 py-2 text-[11px] font-bold transition sm:text-xs',
                paymentMethod === 'cash'
                  ? 'bg-emerald-600 text-white shadow-md'
                  : 'bg-slate-800 text-slate-300 hover:bg-slate-700'
              ]"
            >
              <UiIcon name="cash" :size="16" /> Efectivo
            </button>
            <button
              type="button"
              @click="selectPaymentMethod('qr')"
              :disabled="!paymentQrUrl"
              :title="paymentQrUrl ? 'Cobrar con QR' : 'Primero configura el QR desde Ajustes'"
              :aria-pressed="paymentMethod === 'qr'"
              :class="[
                'flex min-h-12 items-center justify-center gap-1.5 rounded-xl px-2 py-2 text-[11px] font-bold transition sm:text-xs',
                !paymentQrUrl
                  ? 'cursor-not-allowed bg-slate-900 text-slate-500'
                  : paymentMethod === 'qr'
                  ? 'bg-emerald-600 text-white shadow-md'
                  : 'bg-slate-800 text-slate-300 hover:bg-slate-700'
              ]"
            >
              <UiIcon name="qr" :size="16" /> QR
            </button>
          </div>

          <div v-if="!paymentQrUrl" class="flex items-center justify-between gap-3 rounded-xl border border-amber-700/50 bg-amber-950/30 px-3 py-2 text-xs text-amber-200">
            <span>Sube el QR fijo del comercio para habilitar este método.</span>
            <button type="button" @click="openPaymentSettings" class="shrink-0 font-bold underline underline-offset-2">Configurar</button>
          </div>

          <!-- Cash Received & Realtime Change Calculation (When Cash) -->
          <div v-if="paymentMethod === 'cash'" class="space-y-2">
            <div class="grid grid-cols-[minmax(0,1fr)_8rem] items-center gap-3">
              <label for="cash-received" class="text-xs font-extrabold uppercase tracking-wider text-slate-400">
                Efectivo Recibido
              </label>
              <div class="relative">
                <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-slate-400 font-bold">Bs</span>
                <input
                  id="cash-received"
                  v-model="cashReceived"
                  type="number"
                  step="0.01"
                  min="0"
                  placeholder="0,00"
                  class="min-h-12 w-full rounded-xl border border-slate-700 bg-slate-800 py-2 pl-8 pr-3 text-right text-lg font-black text-white focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500"
                />
              </div>
            </div>

            <!-- Quick Cash Preset Pills -->
            <div class="scrollbar-none flex flex-nowrap items-center justify-start gap-2 overflow-x-auto pb-1" aria-label="Importes rápidos de efectivo">
              <button
                type="button"
                @click="setExactCash"
                class="shrink-0 rounded-lg border border-slate-700 bg-slate-800 px-2.5 py-1.5 text-[11px] font-bold text-emerald-400 transition hover:bg-slate-700 sm:text-xs"
              >
                Exacto
              </button>
              <button
                type="button"
                @click="addCashPreset(10)"
                class="shrink-0 rounded-lg border border-slate-700 bg-slate-800 px-2.5 py-1.5 text-[11px] font-bold text-slate-200 transition hover:bg-slate-700 sm:text-xs"
              >
                +Bs 10
              </button>
              <button
                type="button"
                @click="addCashPreset(20)"
                class="shrink-0 rounded-lg border border-slate-700 bg-slate-800 px-2.5 py-1.5 text-[11px] font-bold text-slate-200 transition hover:bg-slate-700 sm:text-xs"
              >
                +Bs 20
              </button>
              <button
                type="button"
                @click="addCashPreset(50)"
                class="shrink-0 rounded-lg border border-slate-700 bg-slate-800 px-2.5 py-1.5 text-[11px] font-bold text-slate-200 transition hover:bg-slate-700 sm:text-xs"
              >
                +Bs 50
              </button>
              <button
                type="button"
                @click="addCashPreset(100)"
                class="shrink-0 rounded-lg border border-slate-700 bg-slate-800 px-2.5 py-1.5 text-[11px] font-bold text-slate-200 transition hover:bg-slate-700 sm:text-xs"
              >
                +Bs 100
              </button>
            </div>

            <!-- Real-time Change Row -->
            <div v-if="paymentMethod === 'cash' && cashReceived && orderItems.length" class="flex min-h-10 items-center justify-between rounded-xl border border-slate-700/60 bg-slate-800/80 px-3 py-2" aria-live="polite">
              <span class="text-xs font-bold uppercase text-slate-400">Cambio:</span>
              <span
                :class="[
                  'text-right text-sm font-bold sm:text-base',
                  orderItems.length === 0 ? 'text-slate-400' : isCashSufficient ? 'text-emerald-400' : 'text-amber-300'
                ]"
              >
                <template v-if="orderItems.length === 0">
                  Agrega platos a la orden
                </template>
                <template v-else-if="!cashReceived">
                  Ingresa el efectivo
                </template>
                <template v-else-if="isCashSufficient">
                  {{ formatCurrency(changeGiven) }}
                </template>
                <template v-else>
                  Falta {{ formatCurrency(orderTotal - parseFloat(cashReceived)) }}
                </template>
              </span>
            </div>
          </div>

          <div v-if="paymentMethod === 'qr'" class="space-y-2 rounded-xl border border-slate-700 bg-slate-800/70 p-3">
            <div class="flex items-start gap-2 text-xs leading-relaxed text-slate-300">
              <UiIcon name="qr" class="mt-0.5 shrink-0 text-emerald-400" :size="17" />
              <p><strong class="text-slate-100">Paga {{ formatCurrency(orderTotal) }}.</strong> Si tu banco lo pide, ingresa ese importe; luego verifica el abono en tu app bancaria.</p>
            </div>
            <img v-if="paymentQrUrl" :src="paymentQrUrl" alt="QR fijo para pagar al restaurante" class="mx-auto h-48 w-48 rounded-xl bg-white object-contain p-2" />
            <label class="flex cursor-pointer items-center gap-2 border-t border-slate-700 pt-2 text-xs font-semibold text-slate-200">
              <input v-model="isQrConfirmed" type="checkbox" class="h-4 w-4 shrink-0 rounded border-slate-500 text-emerald-500 focus:ring-emerald-400" />
              <span>Ya verifiqué que el pago se acreditó</span>
            </label>
          </div>

          <!-- Main Action: REALIZAR COBRO (Full Width) -->
          <button
            type="button"
            @click="processSale"
            :disabled="!canProcessSale"
            :class="[
              'flex min-h-14 w-full items-center justify-center gap-2 rounded-2xl px-4 py-4 text-base font-extrabold uppercase tracking-wide transition sm:text-lg',
              !canProcessSale
                ? 'cursor-not-allowed bg-slate-200 text-slate-500 shadow-none'
                : 'cursor-pointer bg-emerald-600 text-white shadow-lg hover:bg-emerald-700 active:scale-[0.98]'
            ]"
          >
            <svg v-if="isSubmitting" class="h-6 w-6 animate-spin text-slate-600" fill="none" viewBox="0 0 24 24">
              <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
              <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg>
            <span v-else class="flex items-center gap-2">
              <UiIcon :name="paymentMethod === 'cash' ? 'cash' : 'qr'" :size="22" />
              {{ checkoutButtonLabel }}
            </span>
          </button>
        </div>
      </aside>
    </div>

    <!-- Modals -->
    <DishSettingsModal
      :show="showSettingsModal"
      :categories="categories"
      :dishes="dishes"
      :archived-dishes="archivedDishes"
      :payment-qr-url="paymentQrUrl"
      :initial-tab="settingsInitialTab"
      @close="showSettingsModal = false"
    />

    <TicketReceiptModal
      :sale="completedSale"
      @close="completedSale = null; showReceiptModal = false"
    />
  </div>
</template>
