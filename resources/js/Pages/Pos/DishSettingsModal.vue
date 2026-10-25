<script setup>
import { computed, nextTick, onBeforeUnmount, ref, watch } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import { formatCurrency } from '../../utils/currency.js';
import UiIcon from '../../Components/UiIcon.vue';

const props = defineProps({
  show: { type: Boolean, default: false },
  categories: { type: Array, default: () => [] },
  dishes: { type: Array, default: () => [] },
  archivedDishes: { type: Array, default: () => [] },
  paymentQrUrl: { type: String, default: null },
  initialTab: { type: String, default: 'new' },
});

const emit = defineEmits(['close']);
const activeTab = ref('new');
const editingDishId = ref(null);
const imagePreview = ref(null);
const originalImageUrl = ref(null);
const fileInput = ref(null);
const categoryName = ref('');
const categoryNameError = ref('');
const categoryEditId = ref(null);
const categoryEditName = ref('');
const categoryEditError = ref('');
const categoryProcessing = ref(false);
const dishSearchQuery = ref('');
const modalPanel = ref(null);
const qrFileInput = ref(null);
const qrImagePreview = ref(null);
const paymentQrForm = useForm({ image: null });
let previouslyFocusedElement = null;

const focusableSelector = 'button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])';
const closeModal = () => emit('close');
const qrImageSource = computed(() => qrImagePreview.value || props.paymentQrUrl);

const form = useForm({
  name: '',
  price: '',
  category_id: props.categories[0]?.id || '',
  image: null,
  is_available: true,
  remove_image: false,
});

watch(() => props.categories, (categories) => {
  if (!form.category_id && categories.length > 0) {
    form.category_id = categories[0].id;
  }
}, { immediate: true });

const matchesDishSearch = (dish) => {
  const search = dishSearchQuery.value.trim().toLocaleLowerCase('es');

  return !search || `${dish.name} ${dish.category?.name || ''}`.toLocaleLowerCase('es').includes(search);
};

const filteredDishes = computed(() => props.dishes.filter(matchesDishSearch));
const filteredArchivedDishes = computed(() => props.archivedDishes.filter(matchesDishSearch));
const linkedDishCount = (category) => Number(category.dishes_count || 0) + Number(category.archived_dishes_count || 0);

const handleModalKeydown = (event) => {
  if (event.key === 'Escape') {
    event.preventDefault();
    closeModal();

    return;
  }

  if (event.key !== 'Tab' || !modalPanel.value) {
    return;
  }

  const focusableElements = [...modalPanel.value.querySelectorAll(focusableSelector)];
  const firstElement = focusableElements[0];
  const lastElement = focusableElements[focusableElements.length - 1];

  if (!firstElement) {
    event.preventDefault();
    modalPanel.value.focus();

    return;
  }

  if (!modalPanel.value.contains(document.activeElement)) {
    event.preventDefault();
    (event.shiftKey ? lastElement : firstElement).focus();

    return;
  }

  if (event.shiftKey && document.activeElement === firstElement) {
    event.preventDefault();
    lastElement.focus();
  } else if (!event.shiftKey && document.activeElement === lastElement) {
    event.preventDefault();
    firstElement.focus();
  }
};

watch(() => props.show, async (isShowing) => {
  if (isShowing) {
    activeTab.value = props.initialTab;
    previouslyFocusedElement = document.activeElement;
    document.addEventListener('keydown', handleModalKeydown);
    await nextTick();
    modalPanel.value?.querySelector(focusableSelector)?.focus();

    return;
  }

  document.removeEventListener('keydown', handleModalKeydown);
  previouslyFocusedElement?.focus?.();
  previouslyFocusedElement = null;
  clearQrUpload();
}, { flush: 'post' });

onBeforeUnmount(() => {
  document.removeEventListener('keydown', handleModalKeydown);
  if (qrImagePreview.value?.startsWith('blob:')) {
    URL.revokeObjectURL(qrImagePreview.value);
  }
});

const resetForm = () => {
  form.reset();
  form.clearErrors();
  editingDishId.value = null;
  if (imagePreview.value?.startsWith('blob:')) {
    URL.revokeObjectURL(imagePreview.value);
  }
  imagePreview.value = null;
  originalImageUrl.value = null;
  if (fileInput.value) {
    fileInput.value.value = '';
  }
};

const setImagePreview = (file) => {
  if (imagePreview.value?.startsWith('blob:')) {
    URL.revokeObjectURL(imagePreview.value);
  }
  imagePreview.value = file ? URL.createObjectURL(file) : null;
};

const handleQrFileChange = (event) => {
  const file = event.target.files[0] || null;
  paymentQrForm.image = file;

  if (qrImagePreview.value?.startsWith('blob:')) {
    URL.revokeObjectURL(qrImagePreview.value);
  }

  qrImagePreview.value = file ? URL.createObjectURL(file) : null;
  paymentQrForm.clearErrors();
};

const clearQrUpload = () => {
  if (qrImagePreview.value?.startsWith('blob:')) {
    URL.revokeObjectURL(qrImagePreview.value);
  }

  qrImagePreview.value = null;
  paymentQrForm.reset();
  paymentQrForm.clearErrors();
  if (qrFileInput.value) {
    qrFileInput.value.value = '';
  }
};

const savePaymentQr = () => {
  paymentQrForm.post('/payment-settings/qr', {
    forceFormData: true,
    preserveScroll: true,
    onSuccess: clearQrUpload,
  });
};

const removePaymentQr = () => {
  router.delete('/payment-settings/qr', {
    preserveScroll: true,
    onSuccess: clearQrUpload,
  });
};

const handleFileChange = (event) => {
  const file = event.target.files[0] || null;
  form.image = file;
  form.remove_image = false;

  if (file) {
    setImagePreview(file);
  } else {
    setImagePreview(null);
    imagePreview.value = originalImageUrl.value;
  }
};

const removeSelectedImage = () => {
  const isNewUpload = imagePreview.value?.startsWith('blob:');

  if (isNewUpload && imagePreview.value) {
    URL.revokeObjectURL(imagePreview.value);
  }

  form.image = null;
  if (isNewUpload && originalImageUrl.value) {
    imagePreview.value = originalImageUrl.value;
    form.remove_image = false;
  } else {
    imagePreview.value = null;
    form.remove_image = Boolean(originalImageUrl.value);
  }

  if (fileInput.value) {
    fileInput.value.value = '';
  }
};

const submitDish = () => {
  const url = editingDishId.value ? `/dishes/${editingDishId.value}` : '/dishes';

  form.post(url, {
    forceFormData: true,
    preserveScroll: true,
    onSuccess: () => {
      resetForm();
      activeTab.value = 'list';
    },
  });
};

const editDish = (dish) => {
  if (imagePreview.value?.startsWith('blob:')) {
    URL.revokeObjectURL(imagePreview.value);
  }

  editingDishId.value = dish.id;
  form.name = dish.name;
  form.price = Number(dish.price).toFixed(2);
  form.category_id = dish.category_id;
  form.image = null;
  form.is_available = dish.is_available;
  form.remove_image = false;
  form.clearErrors();
  originalImageUrl.value = dish.image_url;
  imagePreview.value = dish.image_url;
  activeTab.value = 'new';
};

const toggleDishAvailability = (dishId) => {
  router.patch(`/dishes/${dishId}/toggle`, {}, { preserveScroll: true });
};

const archiveDish = (dish) => {
  if (window.confirm(`¿Retirar "${dish.name}" del catálogo? Sus ventas anteriores se conservarán.`)) {
    router.delete(`/dishes/${dish.id}`, { preserveScroll: true });
  }
};

const restoreDish = (dishId) => {
  router.patch(`/dishes/${dishId}/restore`, {}, { preserveScroll: true });
};

const createCategory = () => {
  router.post('/categories', { name: categoryName.value }, {
    preserveScroll: true,
    onStart: () => { categoryProcessing.value = true; },
    onSuccess: () => {
      categoryName.value = '';
      categoryNameError.value = '';
    },
    onError: (errors) => {
      categoryNameError.value = errors.name || '';
    },
    onFinish: () => { categoryProcessing.value = false; },
  });
};

const editCategory = (category) => {
  categoryEditId.value = category.id;
  categoryEditName.value = category.name;
  categoryEditError.value = '';
};

const updateCategory = () => {
  router.patch(`/categories/${categoryEditId.value}`, { name: categoryEditName.value }, {
    preserveScroll: true,
    onStart: () => { categoryProcessing.value = true; },
    onSuccess: () => {
      categoryEditId.value = null;
      categoryEditError.value = '';
    },
    onError: (errors) => {
      categoryEditError.value = errors.name || '';
    },
    onFinish: () => { categoryProcessing.value = false; },
  });
};

const deleteCategory = (category) => {
  if (window.confirm(`¿Eliminar la categoría "${category.name}"?`)) {
    router.delete(`/categories/${category.id}`, {
      preserveScroll: true,
      onStart: () => { categoryProcessing.value = true; },
      onError: (errors) => {
        categoryEditError.value = errors.category || '';
      },
      onFinish: () => { categoryProcessing.value = false; },
    });
  }
};
</script>

<template>
  <div v-if="show" class="fixed inset-0 z-50 overflow-y-auto" role="dialog" aria-modal="true" aria-labelledby="catalog-title">
    <div aria-hidden="true" class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm" @click="closeModal"></div>

    <div class="flex min-h-full items-center justify-center p-4">
      <section ref="modalPanel" tabindex="-1" class="relative w-full max-w-3xl overflow-hidden rounded-2xl border border-slate-100 bg-white shadow-2xl focus:outline-none">
        <header class="flex items-center justify-between border-b border-slate-100 bg-slate-50 px-6 py-4">
          <div class="flex items-center gap-3">
            <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-emerald-100 text-emerald-700">
              <UiIcon name="settings" :size="20" />
            </span>
            <div>
               <h2 id="catalog-title" class="text-xl font-extrabold text-slate-900">Ajustes del POS</h2>
               <p class="text-xs text-slate-500">Platillos, categorías y cobros QR</p>
            </div>
          </div>
          <button type="button" @click="closeModal" class="rounded-xl p-2 text-slate-400 transition hover:bg-slate-200 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500" aria-label="Cerrar ajustes">✕</button>
        </header>

        <nav class="flex gap-2 overflow-x-auto border-b border-slate-200 bg-slate-100/60 px-4 pt-3 sm:px-6">
          <button v-for="tab in [
            { id: 'new', label: editingDishId ? 'Editar platillo' : 'Nuevo platillo' },
            { id: 'list', label: `Platos (${dishes.length})` },
            { id: 'categories', label: `Categorías (${categories.length})` },
            { id: 'payment', label: 'QR de pago' },
          ]" :key="tab.id" type="button" @click="activeTab = tab.id"
            :class="['shrink-0 rounded-t-xl border-b-2 px-4 py-2.5 text-sm font-bold transition', activeTab === tab.id ? 'border-emerald-600 bg-white text-emerald-700' : 'border-transparent text-slate-500 hover:text-slate-800']">
            {{ tab.label }}
          </button>
        </nav>

        <div v-if="activeTab === 'new'" class="max-h-[75vh] overflow-y-auto p-6">
          <div v-if="categories.length === 0" class="mb-5 rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">
            Primero crea una categoría para poder registrar platillos.
            <button type="button" class="ml-1 font-bold underline" @click="activeTab = 'categories'">Administrar categorías</button>
          </div>

          <form class="space-y-4" @submit.prevent="submitDish">
            <div>
              <label for="dish-name" class="mb-1 block text-sm font-bold text-slate-700">Nombre del platillo *</label>
              <input id="dish-name" v-model="form.name" required maxlength="255" class="w-full rounded-xl border-slate-300 px-4 py-2.5 text-sm focus:border-emerald-500 focus:ring-emerald-500" />
              <p v-if="form.errors.name" class="mt-1 text-xs text-rose-600">{{ form.errors.name }}</p>
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
              <div>
                <label for="dish-category" class="mb-1 block text-sm font-bold text-slate-700">Categoría *</label>
                <select id="dish-category" v-model="form.category_id" required class="w-full rounded-xl border-slate-300 bg-white px-4 py-2.5 text-sm focus:border-emerald-500 focus:ring-emerald-500">
                  <option value="" disabled>Selecciona una categoría</option>
                  <option v-for="category in categories" :key="category.id" :value="category.id">{{ category.name }}</option>
                </select>
                <p v-if="form.errors.category_id" class="mt-1 text-xs text-rose-600">{{ form.errors.category_id }}</p>
              </div>
              <div>
                <label for="dish-price" class="mb-1 block text-sm font-bold text-slate-700">Precio (Bs) *</label>
                <input id="dish-price" v-model="form.price" type="number" min="0" max="99999999.99" step="0.01" required class="w-full rounded-xl border-slate-300 px-4 py-2.5 text-sm focus:border-emerald-500 focus:ring-emerald-500" />
                <p v-if="form.errors.price" class="mt-1 text-xs text-rose-600">{{ form.errors.price }}</p>
              </div>
            </div>

            <div>
              <label for="dish-image" class="mb-1 block text-sm font-bold text-slate-700">Imagen (opcional)</label>
              <input id="dish-image" ref="fileInput" type="file" accept="image/jpeg,image/png,image/webp,image/gif,image/avif" @change="handleFileChange" class="block w-full text-sm text-slate-600 file:mr-3 file:rounded-lg file:border-0 file:bg-emerald-50 file:px-4 file:py-2 file:font-bold file:text-emerald-700" />
              <p class="mt-1 text-xs text-slate-500">JPEG/JPG, PNG, WebP, GIF o AVIF; máximo 4 MB. SVG y HEIC/HEIF no están permitidos.</p>
              <p v-if="form.errors.image" class="mt-1 text-xs text-rose-600">{{ form.errors.image }}</p>
              <div v-if="imagePreview" class="relative mt-3 inline-flex">
                <img :src="imagePreview" alt="Vista previa del platillo" class="h-28 w-28 rounded-xl border border-slate-200 object-cover" />
                <button type="button" @click="removeSelectedImage" class="absolute -right-2 -top-2 flex h-7 w-7 items-center justify-center rounded-full bg-rose-600 text-sm font-bold text-white shadow hover:bg-rose-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-rose-500" aria-label="Quitar imagen del platillo">✕</button>
              </div>
            </div>

            <label class="flex items-center gap-3 text-sm font-semibold text-slate-700">
              <input v-model="form.is_available" type="checkbox" class="h-5 w-5 rounded border-slate-300 text-emerald-600 focus:ring-emerald-500" />
              Disponible para la venta
            </label>

            <div class="flex justify-end gap-3 border-t border-slate-100 pt-4">
              <button v-if="editingDishId" type="button" @click="resetForm" class="rounded-xl border border-slate-300 px-4 py-2.5 text-sm font-bold text-slate-600 hover:bg-slate-100">Cancelar edición</button>
              <button type="submit" :disabled="form.processing || categories.length === 0" class="rounded-xl bg-emerald-600 px-5 py-2.5 text-sm font-bold text-white hover:bg-emerald-700 disabled:opacity-50">
                {{ form.processing ? 'Guardando…' : editingDishId ? 'Guardar cambios' : 'Agregar platillo' }}
              </button>
            </div>
          </form>
        </div>

        <div v-else-if="activeTab === 'list'" class="max-h-[75vh] space-y-6 overflow-y-auto p-6">
          <label class="relative block">
            <span class="sr-only">Buscar platos</span>
            <UiIcon name="search" :size="18" class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-slate-400" />
            <input v-model="dishSearchQuery" type="search" placeholder="Buscar por plato o categoría" class="w-full rounded-xl border-slate-300 py-2.5 pl-10 pr-3 text-sm focus:border-emerald-500 focus:ring-emerald-500" />
          </label>

          <div v-if="filteredDishes.length === 0" class="rounded-xl bg-slate-50 p-6 text-center text-sm text-slate-500">
            {{ dishes.length ? 'No hay platos que coincidan con la búsqueda.' : 'No hay platillos activos.' }}
          </div>
          <div v-for="dish in filteredDishes" :key="dish.id" class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-slate-200 p-3">
            <div class="flex min-w-0 items-center gap-3">
              <img v-if="dish.image_url" :src="dish.image_url" :alt="dish.name" class="h-12 w-12 rounded-lg border object-cover" />
              <div v-else class="flex h-12 w-12 items-center justify-center rounded-lg bg-emerald-50 text-emerald-700">
                <UiIcon name="plate" :size="22" />
              </div>
              <div class="min-w-0">
                <h3 class="truncate text-sm font-bold text-slate-800">{{ dish.name }}</h3>
                <p class="text-xs text-slate-500">{{ formatCurrency(dish.price) }} · {{ dish.category?.name || 'General' }}</p>
              </div>
            </div>
            <div class="flex flex-wrap items-center gap-2">
              <button type="button" @click="toggleDishAvailability(dish.id)" class="rounded-lg bg-slate-100 px-3 py-2 text-xs font-bold text-slate-700 hover:bg-slate-200">{{ dish.is_available ? 'Disponible' : 'Agotado' }}</button>
              <button type="button" @click="editDish(dish)" class="rounded-lg bg-blue-50 px-3 py-2 text-xs font-bold text-blue-700 hover:bg-blue-100">Editar</button>
              <button type="button" @click="archiveDish(dish)" class="rounded-lg bg-rose-50 px-3 py-2 text-xs font-bold text-rose-700 hover:bg-rose-100">Retirar</button>
            </div>
          </div>

          <section class="border-t border-slate-200 pt-5">
            <h3 class="mb-3 font-extrabold text-slate-800">Platillos retirados ({{ filteredArchivedDishes.length }})</h3>
            <div v-if="filteredArchivedDishes.length === 0" class="text-sm text-slate-500">
              {{ archivedDishes.length ? 'No hay platos retirados que coincidan con la búsqueda.' : 'No hay platos retirados.' }}
            </div>
            <div v-for="dish in filteredArchivedDishes" :key="dish.id" class="mb-2 flex items-center justify-between gap-3 rounded-xl border border-slate-200 bg-slate-50 p-3">
              <div>
                <p class="text-sm font-bold text-slate-700">{{ dish.name }}</p>
                <p class="text-xs text-slate-500">{{ formatCurrency(dish.price) }} · {{ dish.category?.name || 'General' }}</p>
              </div>
              <button type="button" @click="restoreDish(dish.id)" class="rounded-lg bg-emerald-50 px-3 py-2 text-xs font-bold text-emerald-700 hover:bg-emerald-100">Restaurar</button>
            </div>
          </section>
        </div>

        <div v-else-if="activeTab === 'categories'" class="max-h-[75vh] space-y-5 overflow-y-auto p-6">
          <form class="flex gap-2" @submit.prevent="createCategory">
            <input v-model="categoryName" maxlength="255" required placeholder="Nombre de la categoría" class="min-w-0 flex-1 rounded-xl border-slate-300 px-4 py-2.5 text-sm focus:border-emerald-500 focus:ring-emerald-500" />
            <button type="submit" :disabled="categoryProcessing" class="rounded-xl bg-emerald-600 px-4 py-2.5 text-sm font-bold text-white transition hover:bg-emerald-700 disabled:cursor-wait disabled:opacity-60">
              {{ categoryProcessing ? 'Guardando…' : 'Agregar' }}
            </button>
          </form>
          <p v-if="categoryNameError" class="-mt-3 text-xs text-rose-600">{{ categoryNameError }}</p>
          <p v-if="categoryEditError" class="text-xs text-rose-600">{{ categoryEditError }}</p>

          <div v-for="category in categories" :key="category.id" class="flex flex-wrap items-center justify-between gap-2 rounded-xl border border-slate-200 p-3">
            <template v-if="categoryEditId === category.id">
              <input v-model="categoryEditName" maxlength="255" :disabled="categoryProcessing" class="min-w-0 flex-1 rounded-lg border-slate-300 px-3 py-2 text-sm" />
              <button type="button" @click="updateCategory" :disabled="categoryProcessing" class="rounded-lg bg-emerald-600 px-3 py-2 text-xs font-bold text-white disabled:opacity-60">{{ categoryProcessing ? 'Guardando…' : 'Guardar' }}</button>
              <button type="button" @click="categoryEditId = null; categoryEditError = ''" :disabled="categoryProcessing" class="rounded-lg bg-slate-100 px-3 py-2 text-xs font-bold text-slate-700 disabled:opacity-60">Cancelar</button>
            </template>
            <template v-else>
              <div>
                <p class="font-bold text-slate-800">{{ category.name }}</p>
                <p class="text-xs text-slate-500">
                  {{ category.dishes_count }} activos
                  <span v-if="category.archived_dishes_count">· {{ category.archived_dishes_count }} retirados</span>
                </p>
                <p v-if="linkedDishCount(category) > 0" class="mt-1 text-[11px] text-slate-400">
                  Reasigna los platos y restaura primero los retirados para poder eliminarla.
                </p>
              </div>
              <div class="flex gap-2">
                <button type="button" @click="editCategory(category)" :disabled="categoryProcessing" class="rounded-lg bg-blue-50 px-3 py-2 text-xs font-bold text-blue-700 disabled:opacity-60">Editar</button>
                <button
                  type="button"
                  @click="deleteCategory(category)"
                  :disabled="categoryProcessing || linkedDishCount(category) > 0"
                  :title="linkedDishCount(category) > 0 ? 'Reasigna los platos y restaura primero los retirados para borrar la categoría' : 'Eliminar categoría'"
                  class="rounded-lg bg-rose-50 px-3 py-2 text-xs font-bold text-rose-700 disabled:cursor-not-allowed disabled:opacity-40"
                >
                  Eliminar
                </button>
              </div>
            </template>
          </div>
        </div>

        <div v-else class="max-h-[75vh] space-y-5 overflow-y-auto p-6">
          <div class="max-w-2xl">
            <p class="text-sm leading-relaxed text-slate-600">Sube el QR fijo del comercio. El cliente lo escanea desde su app bancaria; el cajero todavía debe verificar el abono manualmente antes de registrar la venta.</p>
          </div>

          <div class="grid gap-6 md:grid-cols-[minmax(0,1fr)_minmax(14rem,0.8fr)]">
            <form class="space-y-4" @submit.prevent="savePaymentQr">
              <div>
                <label for="payment-qr-image" class="mb-1 block text-sm font-bold text-slate-700">Imagen del QR del comercio</label>
                <input
                  id="payment-qr-image"
                  ref="qrFileInput"
                  type="file"
                  accept="image/png,image/jpeg,image/webp"
                  @change="handleQrFileChange"
                  class="block w-full text-sm text-slate-600 file:mr-3 file:rounded-lg file:border-0 file:bg-emerald-50 file:px-4 file:py-2 file:font-bold file:text-emerald-700"
                />
                <p class="mt-1 text-xs text-slate-500">PNG, JPEG o WebP; máximo 4 MB. Reemplazarlo no cambia los pagos ya registrados.</p>
                <p v-if="paymentQrForm.errors.image" class="mt-1 text-xs text-rose-600">{{ paymentQrForm.errors.image }}</p>
              </div>

              <div class="flex flex-wrap gap-2 border-t border-slate-100 pt-4">
                <button type="submit" :disabled="!paymentQrForm.image || paymentQrForm.processing" class="rounded-xl bg-emerald-600 px-4 py-2.5 text-sm font-bold text-white hover:bg-emerald-700 disabled:cursor-not-allowed disabled:opacity-50">
                  {{ paymentQrForm.processing ? 'Guardando…' : paymentQrUrl ? 'Reemplazar QR' : 'Guardar QR' }}
                </button>
                <button v-if="qrImagePreview" type="button" @click="clearQrUpload" class="rounded-xl border border-slate-300 px-4 py-2.5 text-sm font-bold text-slate-700 hover:bg-slate-100">Descartar selección</button>
                <button v-else-if="paymentQrUrl" type="button" @click="removePaymentQr" class="rounded-xl border border-rose-200 px-4 py-2.5 text-sm font-bold text-rose-700 hover:bg-rose-50">Quitar QR</button>
              </div>
            </form>

            <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4 text-center">
              <p class="mb-3 text-xs font-extrabold uppercase tracking-wide text-slate-500">Vista previa</p>
              <img v-if="qrImageSource" :src="qrImageSource" alt="Vista previa del QR de pago del comercio" class="mx-auto aspect-square w-full max-w-64 rounded-xl bg-white object-contain p-2 shadow-sm" />
              <div v-else class="flex aspect-square w-full max-w-64 items-center justify-center rounded-xl border border-dashed border-slate-300 bg-white text-sm text-slate-500">
                Todavía no hay un QR configurado.
              </div>
            </div>
          </div>
        </div>
      </section>
    </div>
  </div>
</template>
