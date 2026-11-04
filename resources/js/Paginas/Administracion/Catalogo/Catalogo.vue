<script setup>
import { computed, ref, watch } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import AdminLayout from '../../../Plantillas/PlantillaAdministracion.vue';
import UiIcon from '../../../Componentes/Icono.vue';
import { formatCurrency } from '../../../utilidades/moneda.js';

const props = defineProps({
  categories: { type: Array, default: () => [] },
  dishes: { type: Array, default: () => [] },
  archivedDishes: { type: Array, default: () => [] },
});

const activeTab = ref('dishes');
const dishSearchQuery = ref('');
const showDishModal = ref(false);
const editingDishId = ref(null);
const editingDishTrackStock = ref(false);
const imagePreview = ref(null);
const originalImageUrl = ref(null);
const fileInput = ref(null);
const categoryName = ref('');
const categoryNameError = ref('');
const categoryEditId = ref(null);
const categoryEditName = ref('');
const categoryEditError = ref('');
const categoryProcessing = ref(false);

const form = useForm({
  name: '',
  price: '',
  category_id: props.categories[0]?.id || '',
  image: null,
  is_available: true,
  remove_image: false,
});

watch(() => props.categories, (categories) => {
  if (! form.category_id && categories.length > 0) {
    form.category_id = categories[0].id;
  }
}, { immediate: true });

const tabs = computed(() => [
  { id: 'dishes', label: `Platos (${props.dishes.length})` },
  { id: 'categories', label: `Categorías (${props.categories.length})` },
  { id: 'archived', label: `Retirados (${props.archivedDishes.length})` },
]);

const closeDishModal = () => {
  showDishModal.value = false;
  resetForm();
};

const matchesDishSearch = (dish) => {
  const search = dishSearchQuery.value.trim().toLocaleLowerCase('es');

  return ! search || `${dish.name} ${dish.category?.name || ''}`.toLocaleLowerCase('es').includes(search);
};

const filteredDishes = computed(() => props.dishes.filter(matchesDishSearch));
const filteredArchivedDishes = computed(() => props.archivedDishes.filter(matchesDishSearch));
const linkedDishCount = (category) => Number(category.dishes_count || 0) + Number(category.archived_dishes_count || 0);

const scrollToPageTop = () => {
  document.querySelector('main')?.scrollTo({ top: 0, behavior: 'smooth' });
};

const selectTab = (tab) => {
  activeTab.value = tab;
  scrollToPageTop();
};

const startCreate = () => {
  resetForm();
  showDishModal.value = true;
};

const resetForm = () => {
  form.reset();
  form.clearErrors();
  editingDishId.value = null;
  editingDishTrackStock.value = false;
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
      closeDishModal();
      activeTab.value = 'dishes';
      scrollToPageTop();
    },
  });
};

const editDish = (dish) => {
  if (imagePreview.value?.startsWith('blob:')) {
    URL.revokeObjectURL(imagePreview.value);
  }

  editingDishId.value = dish.id;
  editingDishTrackStock.value = Boolean(dish.track_stock);
  form.name = dish.name;
  form.price = Number(dish.price).toFixed(2);
  form.category_id = dish.category_id;
  form.image = null;
  form.is_available = dish.is_available;
  form.remove_image = false;
  form.clearErrors();
  originalImageUrl.value = dish.image_url;
  imagePreview.value = dish.image_url;
  showDishModal.value = true;
};

const cancelEdit = () => {
  closeDishModal();
};

const toggleDishAvailability = (dishId) => {
  router.patch(`/dishes/${dishId}/toggle`, {}, { preserveScroll: true });
};

const archiveDish = (dish) => {
  if (window.confirm(`¿Retirar "${dish.name}" del catálogo? Sus ventas anteriores se conservarán.`)) {
    router.delete(`/dishes/${dish.id}`, {
      preserveScroll: true,
      onSuccess: () => {
        if (showDishModal.value && editingDishId.value === dish.id) {
          closeDishModal();
          activeTab.value = 'dishes';
        }
      },
    });
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
  <AdminLayout title="Catálogo" description="Platos, bebidas y categorías">
    <div class="mx-auto max-w-5xl space-y-6">
      <section class="flex flex-wrap items-end justify-between gap-4">
        <div>
          <p class="text-xs font-extrabold uppercase tracking-[0.16em] text-primary-700">Administración</p>
          <h1 class="mt-1 text-2xl font-black tracking-tight text-slate-900 sm:text-3xl">Catálogo</h1>
          <p class="mt-2 max-w-2xl text-sm text-slate-600">Administra los platos, bebidas, precios y categorías que se muestran en el punto de venta.</p>
        </div>
        <button type="button" @click="startCreate" class="inline-flex min-h-11 items-center gap-2 rounded-xl bg-primary-700 px-4 py-2.5 text-sm font-bold text-white transition hover:bg-primary-800">
          <UiIcon name="plus" :size="17" />
          Nuevo plato
        </button>
      </section>

      <section class="grid gap-4 sm:grid-cols-3" aria-label="Resumen del catálogo">
        <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
          <div class="flex items-center justify-between gap-3">
            <p class="text-sm font-semibold text-slate-500">Platos y bebidas</p>
            <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-primary-50 text-primary-700"><UiIcon name="plate" :size="19" /></span>
          </div>
          <p class="mt-3 text-2xl font-black text-slate-900">{{ dishes.length }}</p>
        </article>
        <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
          <div class="flex items-center justify-between gap-3">
            <p class="text-sm font-semibold text-slate-500">Categorías</p>
            <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-amber-50 text-amber-800"><UiIcon name="menu" :size="19" /></span>
          </div>
          <p class="mt-3 text-2xl font-black text-slate-900">{{ categories.length }}</p>
        </article>
        <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
          <div class="flex items-center justify-between gap-3">
            <p class="text-sm font-semibold text-slate-500">Retirados</p>
            <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-slate-100 text-slate-600"><UiIcon name="trash" :size="19" /></span>
          </div>
          <p class="mt-3 text-2xl font-black text-slate-900">{{ archivedDishes.length }}</p>
        </article>
      </section>

      <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
        <nav class="flex gap-2 overflow-x-auto border-b border-slate-200 bg-slate-50 px-4 pt-3 sm:px-6" role="tablist" aria-label="Secciones del catálogo">
          <button
            v-for="tab in tabs"
            :key="tab.id"
            type="button"
            role="tab"
            :aria-selected="activeTab === tab.id"
            :class="['shrink-0 rounded-t-xl border-b-2 px-4 py-2.5 text-sm font-bold transition', activeTab === tab.id ? 'border-primary-600 bg-white text-primary-700' : 'border-transparent text-slate-500 hover:text-slate-800']"
            @click="selectTab(tab.id)"
          >
            {{ tab.label }}
          </button>
        </nav>

        <div v-if="activeTab === 'dishes'" class="p-4 sm:p-6" role="tabpanel">
          <label class="relative block">
            <span class="sr-only">Buscar platos</span>
            <UiIcon name="search" :size="18" class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-slate-400" />
            <input v-model="dishSearchQuery" type="search" placeholder="Buscar por plato o categoría" class="w-full rounded-xl border-slate-300 py-2.5 pl-10 pr-3 text-sm focus:border-primary-500 focus:ring-primary-500" />
          </label>

          <div v-if="filteredDishes.length === 0" class="mt-4 rounded-xl bg-slate-50 p-8 text-center text-sm text-slate-500">
            {{ dishes.length ? 'No hay platos que coincidan con la búsqueda.' : 'No hay platos activos. Crea el primero con “Nuevo plato”.' }}
          </div>

          <div v-else class="mt-4 space-y-2">
            <article v-for="dish in filteredDishes" :key="dish.id" class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-slate-200 p-3 transition hover:border-slate-300">
              <div class="flex min-w-0 items-center gap-3">
                <img v-if="dish.image_url" :src="dish.image_url" :alt="dish.name" class="h-12 w-12 rounded-lg border object-cover" />
                <div v-else class="flex h-12 w-12 items-center justify-center rounded-lg bg-primary-50 text-primary-700">
                  <UiIcon name="plate" :size="22" />
                </div>
                <div class="min-w-0">
                  <h2 class="truncate text-sm font-bold text-slate-800">{{ dish.name }}</h2>
                  <p class="text-xs text-slate-500">{{ formatCurrency(dish.price) }} · {{ dish.category?.name || 'General' }}</p>
                </div>
              </div>
              <div class="flex flex-wrap items-center gap-2">
                <span v-if="dish.track_stock" :class="['rounded-lg px-3 py-2 text-xs font-bold', dish.stock_quantity > 0 ? 'bg-success-50 text-success-700' : 'bg-rose-50 text-rose-700']">
                  {{ dish.stock_quantity > 0 ? `Stock ${dish.stock_quantity}` : 'Sin stock' }}
                </span>
                <button v-else type="button" @click="toggleDishAvailability(dish.id)" :class="['rounded-lg px-3 py-2 text-xs font-bold', dish.is_available ? 'bg-success-50 text-success-700 hover:bg-success-100' : 'bg-rose-50 text-rose-700 hover:bg-rose-100']">
                  {{ dish.is_available ? 'Disponible' : 'Agotado' }}
                </button>
                <button type="button" @click="editDish(dish)" class="rounded-lg bg-primary-50 px-3 py-2 text-xs font-bold text-primary-700 hover:bg-primary-100">Editar</button>
                <button type="button" @click="archiveDish(dish)" class="rounded-lg bg-rose-50 px-3 py-2 text-xs font-bold text-rose-700 hover:bg-rose-100">Retirar</button>
              </div>
            </article>
          </div>
        </div>

        <div v-else-if="activeTab === 'categories'" class="space-y-5 p-4 sm:p-6" role="tabpanel">
          <form class="flex gap-2" @submit.prevent="createCategory">
            <input v-model="categoryName" maxlength="255" required placeholder="Nombre de la categoría" class="min-w-0 flex-1 rounded-xl border-slate-300 px-4 py-2.5 text-sm focus:border-primary-500 focus:ring-primary-500" />
            <button type="submit" :disabled="categoryProcessing" class="rounded-xl bg-primary-600 px-4 py-2.5 text-sm font-bold text-white transition hover:bg-primary-700 disabled:cursor-wait disabled:opacity-60">
              {{ categoryProcessing ? 'Guardando…' : 'Agregar' }}
            </button>
          </form>
          <p v-if="categoryNameError" class="-mt-3 text-xs text-rose-600">{{ categoryNameError }}</p>
          <p v-if="categoryEditError" class="text-xs text-rose-600">{{ categoryEditError }}</p>

          <div v-if="categories.length === 0" class="rounded-xl bg-slate-50 p-6 text-center text-sm text-slate-500">
            Todavía no hay categorías. Crea una para poder agregar platos.
          </div>

          <div v-for="category in categories" :key="category.id" class="flex flex-wrap items-center justify-between gap-2 rounded-xl border border-slate-200 p-3">
            <template v-if="categoryEditId === category.id">
              <input v-model="categoryEditName" maxlength="255" :disabled="categoryProcessing" class="min-w-0 flex-1 rounded-lg border-slate-300 px-3 py-2 text-sm" />
              <button type="button" @click="updateCategory" :disabled="categoryProcessing" class="rounded-lg bg-primary-600 px-3 py-2 text-xs font-bold text-white disabled:opacity-60">Guardar</button>
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
                <button type="button" @click="editCategory(category)" :disabled="categoryProcessing" class="rounded-lg bg-primary-50 px-3 py-2 text-xs font-bold text-primary-700 disabled:opacity-60">Editar</button>
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

        <div v-else class="p-4 sm:p-6" role="tabpanel">
          <label class="relative block">
            <span class="sr-only">Buscar platos retirados</span>
            <UiIcon name="search" :size="18" class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-slate-400" />
            <input v-model="dishSearchQuery" type="search" placeholder="Buscar platos retirados" class="w-full rounded-xl border-slate-300 py-2.5 pl-10 pr-3 text-sm focus:border-primary-500 focus:ring-primary-500" />
          </label>

          <div v-if="filteredArchivedDishes.length === 0" class="mt-4 rounded-xl bg-slate-50 p-8 text-center text-sm text-slate-500">
            {{ archivedDishes.length ? 'No hay platos retirados que coincidan con la búsqueda.' : 'No hay platos retirados.' }}
          </div>

          <div v-else class="mt-4 space-y-2">
            <article v-for="dish in filteredArchivedDishes" :key="dish.id" class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-slate-200 bg-slate-50 p-3">
              <div>
                <p class="text-sm font-bold text-slate-700">{{ dish.name }}</p>
                <p class="text-xs text-slate-500">{{ formatCurrency(dish.price) }} · {{ dish.category?.name || 'General' }}</p>
              </div>
              <button type="button" @click="restoreDish(dish.id)" class="rounded-lg bg-success-50 px-3 py-2 text-xs font-bold text-success-700 hover:bg-success-100">Restaurar</button>
            </article>
          </div>
        </div>
      </section>
    </div>

    <Teleport to="body">
      <div v-if="showDishModal" class="fixed inset-0 z-50 flex items-center justify-center p-4" role="dialog" aria-modal="true" aria-labelledby="dish-modal-title">
        <div class="absolute inset-0 bg-slate-900/60 backdrop-blur-sm" @click="cancelEdit"></div>
        <div class="relative z-10 max-h-[90vh] w-full max-w-2xl overflow-y-auto rounded-2xl bg-white p-5 shadow-2xl sm:p-6">
          <div class="mb-4 flex items-start justify-between gap-4">
            <div>
              <h2 id="dish-modal-title" class="text-lg font-black text-slate-900">{{ editingDishId ? 'Editar plato' : 'Nuevo plato' }}</h2>
              <p class="mt-1 text-sm text-slate-500">Completa los datos del plato o bebida.</p>
            </div>
            <button type="button" class="rounded-lg p-2 text-slate-400 transition hover:bg-slate-100 hover:text-slate-700" aria-label="Cerrar" @click="cancelEdit">
              ✕
            </button>
          </div>

          <div v-if="categories.length === 0" class="mb-5 rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">
            Primero crea una categoría para poder registrar platos.
            <button type="button" class="ml-1 font-bold underline" @click="cancelEdit(); activeTab = 'categories'">Administrar categorías</button>
          </div>

          <form class="space-y-4" @submit.prevent="submitDish">
            <div>
              <label for="dish-name" class="mb-1 block text-sm font-bold text-slate-700">Nombre del plato o bebida *</label>
              <input id="dish-name" v-model="form.name" required maxlength="255" class="w-full rounded-xl border-slate-300 px-4 py-2.5 text-sm focus:border-primary-500 focus:ring-primary-500" />
              <p v-if="form.errors.name" class="mt-1 text-xs text-rose-600">{{ form.errors.name }}</p>
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
              <div>
                <label for="dish-category" class="mb-1 block text-sm font-bold text-slate-700">Categoría *</label>
                <select id="dish-category" v-model="form.category_id" required class="w-full rounded-xl border-slate-300 bg-white px-4 py-2.5 text-sm focus:border-primary-500 focus:ring-primary-500">
                  <option value="" disabled>Selecciona una categoría</option>
                  <option v-for="category in categories" :key="category.id" :value="category.id">{{ category.name }}</option>
                </select>
                <p v-if="form.errors.category_id" class="mt-1 text-xs text-rose-600">{{ form.errors.category_id }}</p>
              </div>
              <div>
                <label for="dish-price" class="mb-1 block text-sm font-bold text-slate-700">Precio (Bs) *</label>
                <input id="dish-price" v-model="form.price" type="number" min="0" max="99999999.99" step="0.01" required class="w-full rounded-xl border-slate-300 px-4 py-2.5 text-sm focus:border-primary-500 focus:ring-primary-500" />
                <p v-if="form.errors.price" class="mt-1 text-xs text-rose-600">{{ form.errors.price }}</p>
              </div>
            </div>

            <div>
              <label for="dish-image" class="mb-1 block text-sm font-bold text-slate-700">Imagen (opcional)</label>
              <input id="dish-image" ref="fileInput" type="file" accept="image/jpeg,image/png,image/webp,image/gif,image/avif" @change="handleFileChange" class="block w-full text-sm text-slate-600 file:mr-3 file:rounded-lg file:border-0 file:bg-primary-50 file:px-4 file:py-2 file:font-bold file:text-primary-700" />
              <p class="mt-1 text-xs text-slate-500">JPEG/JPG, PNG, WebP, GIF o AVIF; máximo 4 MB. SVG y HEIC/HEIF no están permitidos.</p>
              <p v-if="form.errors.image" class="mt-1 text-xs text-rose-600">{{ form.errors.image }}</p>
              <div v-if="imagePreview" class="relative mt-3 inline-flex">
                <img :src="imagePreview" alt="Vista previa del plato" class="h-28 w-28 rounded-xl border border-slate-200 object-cover" />
                <button type="button" @click="removeSelectedImage" class="absolute -right-2 -top-2 flex h-7 w-7 items-center justify-center rounded-full bg-rose-600 text-sm font-bold text-white shadow hover:bg-rose-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-rose-500" aria-label="Quitar imagen del plato">✕</button>
              </div>
            </div>

            <label v-if="!editingDishTrackStock" class="flex items-center gap-3 text-sm font-semibold text-slate-700">
              <input v-model="form.is_available" type="checkbox" class="h-5 w-5 rounded border-slate-300 text-primary-600 focus:ring-primary-500" />
              Disponible para la venta
            </label>
            <div v-else class="flex items-start gap-3 rounded-xl border border-primary-200 bg-primary-50 p-4 text-sm text-primary-900">
              <UiIcon name="box" class="mt-0.5 shrink-0 text-primary-700" :size="20" />
              <div>
                <p class="font-bold">Disponibilidad controlada por inventario</p>
                <p class="mt-1 text-xs leading-relaxed text-primary-800">La caja venderá este producto mientras tenga stock. Para cambiar las unidades, usa Inventario.</p>
                <a href="/admin/inventory" class="mt-1 inline-block text-xs font-bold underline">Ir a Inventario</a>
              </div>
            </div>

            <div class="flex justify-end gap-3 border-t border-slate-100 pt-4">
              <button type="button" @click="cancelEdit" class="rounded-xl border border-slate-300 px-4 py-2.5 text-sm font-bold text-slate-600 hover:bg-slate-100">Cancelar</button>
              <button type="submit" :disabled="form.processing || categories.length === 0" class="rounded-xl bg-primary-600 px-5 py-2.5 text-sm font-bold text-white hover:bg-primary-700 disabled:opacity-50">
                {{ form.processing ? 'Guardando…' : editingDishId ? 'Guardar cambios' : 'Agregar plato' }}
              </button>
            </div>
          </form>
        </div>
      </div>
    </Teleport>
  </AdminLayout>
</template>
