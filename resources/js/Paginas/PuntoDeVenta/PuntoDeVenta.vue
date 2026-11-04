<script setup>
import { ref, computed, onMounted, onUnmounted, watch } from 'vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import CheckoutModal from './ModalCobro.vue';
import TicketReceiptModal from './ModalRecibo.vue';
import AnimatedValue from '../../Componentes/ValorAnimado.vue';
import FullscreenToggle from '../../Componentes/BotonPantallaCompleta.vue';
import { formatCurrency } from '../../utilidades/moneda.js';
import UiIcon from '../../Componentes/Icono.vue';

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
});

const page = usePage();
const currentUser = computed(() => page.props.auth?.user);
const searchInput = ref(null);
const orderPanel = ref(null);
const mobileOrderTab = ref(null);
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
const showReceiptModal = ref(false);
const showCheckoutModal = ref(false);
const completedSale = ref(null);
const editingNoteIndex = ref(null);
const noteInput = ref('');
const isSubmitting = ref(false);
const checkoutError = ref('');
const stockNotice = ref('');
let stockNoticeTimer = null;

const showStockNotice = (message) => {
  stockNotice.value = message;

  if (stockNoticeTimer) {
    clearTimeout(stockNoticeTimer);
  }

  stockNoticeTimer = setTimeout(() => {
    stockNotice.value = '';
  }, 3000);
};

// Live Clock
const currentTime = ref('');
let timer = null;
let lastFullscreenChange = 0;
const updateClock = () => {
  const now = new Date();
  currentTime.value = now.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit', second: '2-digit' });
};

const handleFullscreenChange = () => {
  lastFullscreenChange = Date.now();
};

onMounted(() => {
  try {
    isDarkMode.value = localStorage.getItem('pollos-misme-theme') === 'dark';
  } catch {
    isDarkMode.value = false;
  }
  document.documentElement.classList.toggle('dark', isDarkMode.value);
  updateClock();
  timer = setInterval(updateClock, 1000);
  document.addEventListener('fullscreenchange', handleFullscreenChange);
});

onUnmounted(() => {
  if (timer) clearInterval(timer);

  if (stockNoticeTimer) {
    clearTimeout(stockNoticeTimer);
  }

  document.removeEventListener('fullscreenchange', handleFullscreenChange);
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

const orderQuantity = computed(() => orderItems.value.reduce((count, item) => count + item.quantity, 0));

const checkoutButtonLabel = computed(() => {
  if (isSubmitting.value) return 'Procesando…';
  if (orderItems.value.length === 0) return 'Agrega platos';

  return 'Cobrar';
});

const isDishSellable = (dish) => (dish.track_stock ? dish.stock_quantity > 0 : dish.is_available);

const dishAvailabilityLabel = (dish) => {
  if (dish.track_stock) {
    return dish.stock_quantity <= 0 ? 'Sin stock' : '';
  }

  return dish.is_available ? '' : 'Agotado';
};

// Cart Actions
const prefersReducedMotion = () => window.matchMedia?.('(prefers-reduced-motion: reduce)').matches;

const flyToCart = (event, dish) => {
  if (! event || event.detail === 0 || prefersReducedMotion()) {
    return;
  }

  const target = window.innerWidth >= 768 ? orderPanel.value : mobileOrderTab.value;
  const targetRect = target?.getBoundingClientRect();

  if (! targetRect || targetRect.width === 0 || targetRect.height === 0) {
    return;
  }

  const flyer = document.createElement(dish.image_url ? 'img' : 'span');
  const size = 42;

  if (flyer instanceof HTMLImageElement) {
    flyer.src = dish.image_url;
    flyer.alt = '';
  } else {
    flyer.textContent = '+';
  }

  flyer.style.cssText = [
    'position:fixed',
    `left:${event.clientX}px`,
    `top:${event.clientY}px`,
    `width:${size}px`,
    `height:${size}px`,
    'border-radius:12px',
    'object-fit:cover',
    'pointer-events:none',
    'z-index:120',
    'display:flex',
    'align-items:center',
    'justify-content:center',
    'font-size:18px',
    'font-weight:900',
    'color:#fff',
    'background:var(--color-primary-500)',
    'box-shadow:0 12px 30px rgb(15 23 42 / 0.28)',
    'transform:translate(-50%, -50%) scale(1)',
  ].join(';');

  document.body.appendChild(flyer);

  const targetX = targetRect.left + targetRect.width / 2;
  const targetY = targetRect.top + targetRect.height / 2;
  const deltaX = targetX - event.clientX;
  const deltaY = targetY - event.clientY;

  if (typeof flyer.animate !== 'function') {
    flyer.remove();

    return;
  }

  flyer.animate([
    { transform: 'translate(-50%, -50%) scale(1)', opacity: 1 },
    { transform: `translate(-50%, -50%) translate(${deltaX}px, ${deltaY}px) scale(0.2)`, opacity: 0.2 },
  ], {
    duration: 520,
    easing: 'cubic-bezier(0.2, 0.7, 0.3, 1)',
  }).finished.finally(() => flyer.remove());

  if (typeof target.animate === 'function') {
    target.animate([
      { transform: 'scale(1)' },
      { transform: 'scale(1.025)' },
      { transform: 'scale(1)' },
    ], { duration: 320, easing: 'ease-out' });
  }
};

const addToOrder = (dish, event = null) => {
  if (! isDishSellable(dish)) {
    return;
  }

  const existingIndex = orderItems.value.findIndex((item) => item.dish_id === dish.id);

  if (existingIndex > -1) {
    const item = orderItems.value[existingIndex];

    if (dish.track_stock && item.quantity >= dish.stock_quantity) {
      showStockNotice(`No hay más stock disponible de ${dish.name}.`);

      return;
    }

    item.quantity++;
  } else {
    orderItems.value.push({
      dish_id: dish.id,
      name: dish.name,
      price: Number(dish.price),
      quantity: 1,
      notes: '',
      image_url: dish.image_url,
      track_stock: dish.track_stock,
      stock_quantity: dish.stock_quantity,
    });
  }

  flyToCart(event, dish);
};

const increaseQuantity = (index) => {
  const item = orderItems.value[index];

  if (item.track_stock && item.quantity >= item.stock_quantity) {
    showStockNotice(`No hay más stock disponible de ${item.name}.`);

    return;
  }

  item.quantity++;
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

const openCheckout = () => {
  if (orderItems.value.length === 0 || isSubmitting.value) {
    return;
  }

  checkoutError.value = '';
  showCheckoutModal.value = true;
};

const closeCheckout = () => {
  if (! isSubmitting.value) {
    showCheckoutModal.value = false;
    checkoutError.value = '';
  }
};

// Submit Order
const processSale = ({ paymentMethod, qrConfirmed }) => {
  if (orderItems.value.length === 0 || isSubmitting.value) {
    return;
  }

  isSubmitting.value = true;
  checkoutError.value = '';

  router.post(
    '/pos/sales',
    {
      items: orderItems.value.map((item) => ({
        dish_id: item.dish_id,
        quantity: item.quantity,
        notes: item.notes,
      })),
      payment_method: paymentMethod,
      qr_confirmed: paymentMethod === 'qr' ? qrConfirmed : null,
    },
    {
      preserveScroll: true,
      onError: (errors) => {
        checkoutError.value = Object.values(errors)[0] || 'No se pudo registrar la venta.';
      },
      onSuccess: () => {
        showCheckoutModal.value = false;
      },
      onFinish: () => {
        isSubmitting.value = false;
      },
    }
  );
};

const exitToAdmin = () => {
  if (orderItems.value.length > 0 && ! window.confirm('¿Salir al panel? Se perderá la orden actual.')) {
    return;
  }

  router.visit('/admin');
};

const handlePosKeydown = (event) => {
  if (event.defaultPrevented || showCheckoutModal.value) {
    return;
  }

  if (event.key === 'Escape'
    && (document.fullscreenElement || Date.now() - lastFullscreenChange < 300)) {
    return;
  }

  if (showReceiptModal.value) {
    if (event.key === 'Escape') {
      event.preventDefault();
      completedSale.value = null;
      showReceiptModal.value = false;
    }

    return;
  }

  if (editingNoteIndex.value !== null && event.key === 'Escape') {
    event.preventDefault();
    cancelNote();

    return;
  }

  if (event.key === 'Escape') {
    event.preventDefault();
    exitToAdmin();

    return;
  }

  if (event.key === 'F2') {
    event.preventDefault();
    searchInput.value?.focus();
  }

  if (event.key === 'F8') {
    event.preventDefault();
    openCheckout();
  }
};

onMounted(() => window.addEventListener('keydown', handlePosKeydown));
onUnmounted(() => window.removeEventListener('keydown', handlePosKeydown));

const toggleDarkMode = () => {
  isDarkMode.value = !isDarkMode.value;
  document.documentElement.classList.toggle('dark', isDarkMode.value);

  try {
    localStorage.setItem('pollos-misme-theme', isDarkMode.value ? 'dark' : 'light');
  } catch {
    // Theme still applies for the current session if storage is unavailable.
  }
};

const logout = () => router.post('/logout');

</script>

<template>
  <Head title="Punto de venta" />

  <div class="flex h-dvh flex-col overflow-hidden bg-slate-100 font-sans">
    <!-- Top Bar -->
    <header class="z-20 flex h-16 shrink-0 items-center justify-between border-b border-slate-200 bg-white px-3 shadow-xs sm:px-6">
      <div class="flex min-w-0 items-center gap-3 sm:gap-4">
        <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-primary-600 text-white shadow-md sm:h-10 sm:w-10">
          <UiIcon name="utensils" :size="22" />
        </div>
        <div>
          <h1 class="brand-wordmark text-lg text-slate-900 sm:text-2xl">Pollos <span class="brand-wordmark-accent">Misme</span></h1>
          <div class="flex items-center gap-1.5 text-[10px] font-medium text-slate-500 sm:gap-2 sm:text-xs">
            <span class="inline-flex h-2 w-2 rounded-full bg-success-500 animate-pulse"></span>
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

        <FullscreenToggle />

        <button
          type="button"
          @click="toggleDarkMode"
          :aria-label="isDarkMode ? 'Cambiar a modo claro' : 'Cambiar a modo noche'"
          :title="isDarkMode ? 'Cambiar a modo claro' : 'Cambiar a modo noche'"
          class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl border border-slate-200 bg-white text-slate-700 transition hover:bg-slate-100 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500"
        >
          <UiIcon :name="isDarkMode ? 'sun' : 'moon'" :size="19" />
        </button>

        <button
          type="button"
          aria-label="Volver al panel (Esc)"
          aria-keyshortcuts="Escape"
          title="Volver al panel (Esc)"
          class="flex h-10 shrink-0 items-center justify-center gap-2 rounded-xl border border-slate-200 bg-white px-3 text-slate-700 transition hover:bg-slate-100 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500"
          @click="exitToAdmin"
        >
          <UiIcon name="arrow-left" :size="18" />
          <span class="hidden text-sm font-bold sm:inline">Volver</span>
          <kbd class="hidden rounded border border-slate-300 bg-slate-50 px-1 text-[10px] font-bold text-slate-500 xl:inline">Esc</kbd>
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

    <div v-if="stockNotice" class="pos-notice-in z-10 flex shrink-0 items-center justify-between gap-3 bg-amber-50 px-5 py-2 text-sm font-semibold text-amber-900" role="alert">
      <span>{{ stockNotice }}</span>
      <button type="button" @click="stockNotice = ''" aria-label="Cerrar aviso de stock" class="font-black">✕</button>
    </div>

    <div v-if="successMessage && !isSuccessDismissed" class="pos-toast fixed right-4 top-20 z-[60] flex max-w-[calc(100vw-2rem)] items-start gap-3 rounded-xl border border-success-200 bg-success-50 px-4 py-3 text-sm font-semibold text-success-900 shadow-lg" role="status" aria-live="polite">
      <span>{{ successMessage }}</span>
      <button type="button" class="shrink-0 font-black text-success-800" aria-label="Cerrar aviso" @click="isSuccessDismissed = true">✕</button>
    </div>

    <!-- Main Workspace: Split View -->
    <div class="flex min-h-0 flex-1 flex-col overflow-hidden md:flex-row">
      <nav class="grid shrink-0 grid-cols-2 gap-2 border-b border-slate-200 bg-white p-2 md:hidden" aria-label="Cambiar panel del punto de venta">
        <button
          type="button"
          :aria-pressed="activeMobilePanel === 'catalog'"
          @click="activeMobilePanel = 'catalog'"
          :class="['rounded-xl px-3 py-2.5 text-sm font-bold', activeMobilePanel === 'catalog' ? 'bg-primary-600 text-white' : 'bg-slate-100 text-slate-700']"
        >
          <span class="inline-flex items-center justify-center gap-2"><UiIcon name="plate" :size="16" /> Catálogo</span>
        </button>
        <button
          type="button"
          ref="mobileOrderTab"
          :aria-pressed="activeMobilePanel === 'order'"
          @click="activeMobilePanel = 'order'"
          :class="['rounded-xl px-3 py-2.5 text-sm font-bold', activeMobilePanel === 'order' ? 'bg-primary-600 text-white' : 'bg-slate-100 text-slate-700']"
        >
          <span class="inline-flex items-center justify-center gap-2"><UiIcon name="receipt" :size="16" /> Orden<span v-if="orderItems.length" :key="orderQuantity" class="pos-count-pop">({{ orderQuantity }})</span></span>
        </button>
      </nav>

      <!-- Left Panel: Catalog (60-65% width) -->
      <main :class="['min-h-0 min-w-0 flex-1 flex-col overflow-hidden bg-slate-50', activeMobilePanel === 'catalog' ? 'flex' : 'hidden', 'md:flex']" aria-label="Catálogo de platillos">
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
              ref="searchInput"
              v-model="searchQuery"
              type="text"
              aria-keyshortcuts="F2"
              placeholder="Buscar platillo por nombre o categoría..."
              class="w-full rounded-2xl border-2 border-slate-200 bg-slate-50/50 py-3.5 pl-12 pr-16 text-base font-semibold text-slate-800 placeholder-slate-400 shadow-inner focus:border-primary-500 focus:bg-white focus:outline-hidden transition"
            />
            <button
              v-if="searchQuery"
              @click="searchQuery = ''"
              aria-label="Limpiar búsqueda"
              class="absolute inset-y-0 right-0 flex items-center pr-3 text-slate-400 hover:text-slate-600"
            >
              <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
              </svg>
            </button>
            <kbd v-else class="pointer-events-none absolute inset-y-0 right-3 my-auto flex h-6 items-center rounded-md border border-slate-300 bg-white px-1.5 text-[10px] font-bold text-slate-500">F2</kbd>
          </div>

          <!-- Horizontal Category Buttons -->
          <div class="flex items-center gap-2 overflow-x-auto pb-1 scrollbar-none">
            <button
              type="button"
              @click="selectedCategoryId = 'all'"
              :class="[
                'shrink-0 px-4 py-2.5 rounded-xl text-sm font-extrabold transition shadow-xs flex items-center gap-2 cursor-pointer',
                selectedCategoryId === 'all'
                  ? 'bg-primary-600 text-white shadow-primary-600/20'
                  : 'bg-white text-slate-700 hover:bg-slate-100 border border-slate-200'
              ]"
            >
              <span class="flex items-center gap-2"><UiIcon name="plate" :size="17" /> Todos</span>
              <span
                :class="[
                  'px-2 py-0.5 rounded-full text-xs font-bold',
                  selectedCategoryId === 'all' ? 'bg-primary-700 text-white' : 'bg-slate-100 text-slate-600'
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
                  ? 'bg-primary-600 text-white shadow-primary-600/20'
                  : 'bg-white text-slate-700 hover:bg-slate-100 border border-slate-200'
              ]"
            >
              <span>{{ cat.name }}</span>
              <span
                :class="[
                  'px-2 py-0.5 rounded-full text-xs font-bold',
                  selectedCategoryId === cat.id ? 'bg-primary-700 text-white' : 'bg-slate-100 text-slate-600'
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
              Prueba con otro término de búsqueda o agrega nuevos platillos desde el Catálogo del panel administrativo.
            </p>
          </div>

          <div v-else :key="selectedCategoryId" :class="filteredDishes.length === 1 ? 'mx-auto grid w-full max-w-sm grid-cols-1 gap-4' : 'grid grid-cols-2 gap-3 sm:gap-4 md:grid-cols-3 xl:grid-cols-4'">
            <article
              v-for="(dish, index) in filteredDishes"
              :key="dish.id"
              :class="[
                'pos-dish-card group relative flex flex-col justify-between overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm transition-all select-none',
                isDishSellable(dish)
                  ? 'hover:-translate-y-1 hover:border-primary-500 hover:shadow-lg'
                  : 'opacity-50 grayscale cursor-not-allowed'
              ]"
              :style="{ animationDelay: `${Math.min(index, 12) * 28}ms` }"
            >
              <button
                type="button"
                class="absolute inset-0 z-10 rounded-2xl focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-inset focus-visible:ring-primary-500 disabled:cursor-not-allowed"
                :disabled="!isDishSellable(dish)"
                :aria-label="isDishSellable(dish) ? `Agregar ${dish.name} al pedido por ${formatCurrency(dish.price)}` : `${dish.name}, ${dishAvailabilityLabel(dish).toLowerCase()}`"
                @click="addToOrder(dish, $event)"
              >
                <span class="sr-only">{{ isDishSellable(dish) ? `Agregar ${dish.name} al pedido` : `${dish.name} ${dishAvailabilityLabel(dish).toLowerCase()}` }}</span>
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
                  class="flex h-full w-full items-center justify-center bg-gradient-to-br from-primary-50 to-slate-100 text-primary-600 transition duration-300 group-hover:scale-105"
                >
                  <UiIcon name="plate" :size="38" />
                </div>

                <!-- Unavailable Overlay -->
                <div
                  v-if="!isDishSellable(dish)"
                  class="absolute inset-0 flex items-center justify-center bg-slate-900/70 text-white text-xs font-black uppercase tracking-wider backdrop-blur-xs"
                >
                  {{ dishAvailabilityLabel(dish) }}
                </div>
              </div>

              <!-- Dish Info -->
              <div class="flex flex-1 flex-col justify-between p-3 sm:p-3.5">
                <div>
                  <div class="mb-0.5 flex items-center justify-between gap-2">
                    <span class="block text-[11px] font-bold uppercase tracking-wider text-primary-600">
                      {{ dish.category?.name || 'General' }}
                    </span>
                    <span v-if="dish.track_stock" :class="['shrink-0 rounded-full px-1.5 py-0.5 text-[9px] font-black', dish.stock_quantity <= dish.low_stock_threshold ? 'bg-amber-100 text-amber-800' : 'bg-slate-100 text-slate-600']">
                      Stock {{ dish.stock_quantity }}
                    </span>
                  </div>
                  <!-- Bold Title text-base or higher -->
                  <h3 class="line-clamp-2 text-sm font-bold leading-snug text-slate-800 transition group-hover:text-primary-700 sm:text-base">
                    {{ dish.name }}
                  </h3>
                </div>

                <!-- Footer with Price and Add Icon -->
                <div class="mt-3 flex items-center justify-between border-t border-slate-100 pt-2">
                  <span class="text-lg font-extrabold text-slate-900">
                    {{ formatCurrency(dish.price) }}
                  </span>
                  <div class="flex h-8 w-8 items-center justify-center rounded-xl bg-primary-50 text-primary-700 transition group-hover:bg-primary-600 group-hover:text-white">
                    +
                  </div>
                </div>
              </div>
            </article>
          </div>
        </div>
      </main>

      <!-- Right Panel: Direct Checkout (35-40% width) -->
      <aside
        ref="orderPanel"
        :class="[
          'min-h-0 min-w-0 flex-1 flex-col bg-white shadow-xl transition-all duration-300 ease-out md:flex-none md:border-l md:border-slate-200',
          activeMobilePanel === 'order' ? 'flex' : 'hidden',
          'md:flex',
          isOrderExpanded
            ? 'md:w-[52vw] md:max-w-3xl md:shadow-2xl'
            : 'md:w-[26rem] xl:w-[30rem]'
        ]"
        aria-label="Orden actual y cobro"
      >
        <!-- Order Header -->
        <div class="flex items-center justify-between border-b border-slate-200 bg-slate-50/70 px-4 py-3 sm:px-5 sm:py-4">
          <div class="flex items-center gap-2">
            <UiIcon name="receipt" class="text-slate-500" :size="21" />
            <h2 class="text-lg font-extrabold text-slate-900">Orden Actual</h2>
            <span v-if="orderItems.length > 0" :key="orderQuantity" class="pos-count-pop rounded-full bg-primary-600 px-2.5 py-0.5 text-xs font-bold text-white">
              {{ orderQuantity }}
            </span>
          </div>

          <div class="flex items-center gap-2">
            <button
              type="button"
              @click="isOrderExpanded = !isOrderExpanded"
              :aria-pressed="isOrderExpanded"
              :aria-label="isOrderExpanded ? 'Reducir panel de orden' : 'Expandir panel de orden'"
              :title="isOrderExpanded ? 'Reducir panel' : 'Expandir orden'"
              class="hidden h-9 w-9 items-center justify-center rounded-lg border border-slate-200 bg-white text-slate-600 transition hover:bg-slate-100 active:scale-95 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500 md:flex"
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

          <TransitionGroup v-if="orderItems.length" name="cart-item" tag="div" class="space-y-3">
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
                    class="shrink-0 rounded-md px-2 py-1 text-xs font-bold text-slate-500 transition hover:bg-slate-100 hover:text-primary-700"
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
                  class="flex h-9 w-9 items-center justify-center rounded-lg bg-slate-100 text-base font-bold text-slate-800 transition hover:bg-slate-200 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500"
                  :aria-label="`Disminuir ${item.name}`"
                >−</button>
                <span class="w-5 text-center text-sm font-black text-slate-900">{{ item.quantity }}</span>
                <button
                  type="button"
                  @click="increaseQuantity(index)"
                  class="flex h-9 w-9 items-center justify-center rounded-lg bg-primary-100 text-base font-bold text-primary-800 transition hover:bg-primary-200 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500"
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
                class="flex-1 text-xs rounded-xl border border-slate-300 px-3 py-1.5 focus:border-primary-500 focus:ring-primary-500"
                @keyup.enter="saveNote(index)"
              />
              <button
                type="button"
                @click="saveNote(index)"
                class="px-2.5 py-1 text-xs font-bold bg-primary-600 text-white rounded-lg hover:bg-primary-700"
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
          </TransitionGroup>
        </div>

        <!-- High-Contrast Bottom Checkout Panel (Dark Background) -->
        <div class="space-y-3 border-t border-slate-800 bg-slate-900 p-3 text-white shadow-2xl sm:p-4">
          <!-- Total Line -->
          <div class="flex items-baseline justify-between border-b border-slate-800 pb-3">
            <span class="text-xs font-extrabold uppercase tracking-wider text-slate-400 sm:text-sm">Total a pagar</span>
            <!-- text-3xl Large Total -->
            <span class="text-3xl font-black text-primary-400 tracking-tight">
              <AnimatedValue :value="orderTotal" format="currency" :duration="350" />
            </span>
          </div>

          <div class="flex items-center justify-between gap-3 rounded-xl border border-slate-700/70 bg-slate-800/60 px-3 py-2 text-xs leading-relaxed text-slate-300">
            <span class="flex items-start gap-2">
              <UiIcon name="receipt" class="mt-0.5 shrink-0 text-primary-400" :size="16" />
              <span>El método de pago se elige al confirmar la venta.</span>
            </span>
            <kbd class="shrink-0 rounded-md border border-slate-600 bg-slate-900 px-1.5 py-0.5 text-[10px] font-bold text-slate-300">F8</kbd>
          </div>

          <!-- Main Action: REALIZAR COBRO (Full Width) -->
          <button
            type="button"
            @click="openCheckout"
            aria-keyshortcuts="F8"
            :disabled="orderItems.length === 0 || isSubmitting"
            :class="[
              'flex min-h-14 w-full items-center justify-center gap-2 rounded-2xl px-4 py-4 text-base font-extrabold uppercase tracking-wide transition sm:text-lg',
              orderItems.length === 0 || isSubmitting
                ? 'cursor-not-allowed bg-slate-200 text-slate-500 shadow-none'
                : 'pos-cta-ready cursor-pointer bg-primary-600 text-white shadow-lg hover:bg-primary-700 active:scale-[0.98]'
            ]"
          >
            <svg v-if="isSubmitting" class="h-6 w-6 animate-spin text-slate-600" fill="none" viewBox="0 0 24 24">
              <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
              <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg>
            <span v-else class="flex items-center gap-2">
              <UiIcon name="receipt" :size="22" />
              {{ checkoutButtonLabel }}
            </span>
          </button>
        </div>
      </aside>
    </div>

    <!-- Modals -->
    <CheckoutModal
      :open="showCheckoutModal"
      :total="orderTotal"
      :processing="isSubmitting"
      :error="checkoutError"
      @close="closeCheckout"
      @confirm="processSale"
    />

    <TicketReceiptModal
      :sale="completedSale"
      @close="completedSale = null; showReceiptModal = false"
    />
  </div>
</template>
