<script setup>
import { computed, ref } from 'vue';
import { useForm, router } from '@inertiajs/vue3';
import LocationMap from '../../../Components/LocationMap.vue';
import FloatingModal from '../../../Components/FloatingModal.vue';
import UiIcon from '../../../Components/UiIcon.vue';
import AdminLayout from '../../../Layouts/AdminLayout.vue';

const props = defineProps({
  stalls: { type: Array, default: () => [] },
});

const editingStallId = ref(null);
const isStallFormOpen = ref(false);
const form = useForm({
  name: '',
  address: '',
  latitude: '',
  longitude: '',
});

const overviewMapMarkers = computed(() => props.stalls.map((stall) => ({
    id: `stall-${stall.id}`,
    label: stall.name,
    description: stall.address || 'Puesto fijo',
    latitude: stall.latitude,
    longitude: stall.longitude,
    kind: stall.is_active ? 'stall' : 'inactive',
  })));

const mapMarkers = computed(() => {
  const stallMarkers = [...overviewMapMarkers.value];

  if (form.latitude !== '' && form.longitude !== '') {
    stallMarkers.push({
      id: 'selected-stall-location',
      label: form.name || 'Ubicación seleccionada',
      description: form.address || 'Nuevo puesto',
      latitude: form.latitude,
      longitude: form.longitude,
      kind: 'selected',
    });
  }

  return stallMarkers;
});

const saveStall = () => {
  const onSuccess = () => {
    editingStallId.value = null;
    isStallFormOpen.value = false;
    form.reset();
  };

  if (editingStallId.value) {
    form.patch(`/admin/stalls/${editingStallId.value}`, {
      preserveScroll: true,
      onSuccess,
    });

    return;
  }

  form.post('/admin/stalls', {
    preserveScroll: true,
    onSuccess,
  });
};

const editStall = (stall) => {
  editingStallId.value = stall.id;
  form.name = stall.name;
  form.address = stall.address || '';
  form.latitude = stall.latitude;
  form.longitude = stall.longitude;
  form.clearErrors();
  isStallFormOpen.value = true;
};

const openNewStall = () => {
  editingStallId.value = null;
  form.reset();
  form.clearErrors();
  isStallFormOpen.value = true;
};

const cancelEdit = () => {
  editingStallId.value = null;
  isStallFormOpen.value = false;
  form.reset();
  form.clearErrors();
};

const setLocation = ({ latitude, longitude }) => {
  form.latitude = latitude.toFixed(7);
  form.longitude = longitude.toFixed(7);
};

const toggleAvailability = (stall) => {
  router.patch(`/admin/stalls/${stall.id}/availability`, {
    is_active: !stall.is_active,
  }, { preserveScroll: true });
};
</script>

<template>
  <AdminLayout title="Puestos" description="Ubicaciones fijas del restaurante">
    <div class="mx-auto max-w-7xl space-y-6">
      <section class="flex flex-wrap items-end justify-between gap-4">
        <div>
          <p class="text-xs font-extrabold uppercase tracking-[0.16em] text-blue-700">Administración</p>
          <h1 class="mt-1 text-2xl font-black tracking-tight text-slate-900 sm:text-3xl">Puestos fijos</h1>
          <p class="mt-2 max-w-2xl text-sm text-slate-600">Administra los puntos fijos del restaurante. No se rastrea la ubicación de empleados.</p>
        </div>
        <div class="flex flex-wrap items-center gap-3">
          <span class="hidden items-center gap-2 text-xs font-semibold text-slate-600 sm:inline-flex"><span class="h-3 w-3 rounded-full bg-blue-600"></span>Activo</span>
          <span class="hidden items-center gap-2 text-xs font-semibold text-slate-600 sm:inline-flex"><span class="h-3 w-3 rounded-full bg-slate-400"></span>Inactivo</span>
          <button type="button" @click="openNewStall" class="inline-flex min-h-11 items-center gap-2 rounded-xl bg-blue-700 px-4 py-2.5 text-sm font-bold text-white transition hover:bg-blue-800">
            <UiIcon name="plus" :size="17" />
            Registrar puesto
          </button>
        </div>
      </section>

      <section class="grid gap-6">
        <article class="overflow-hidden rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
          <div class="mb-4 flex flex-wrap items-start justify-between gap-2">
            <div>
              <h2 class="font-extrabold text-slate-900">Mapa de puestos</h2>
              <p class="mt-1 text-xs text-slate-500">Puestos activos e inactivos del restaurante.</p>
            </div>
            <span class="text-[10px] text-slate-500">© OpenStreetMap contributors</span>
          </div>
          <LocationMap :markers="overviewMapMarkers" />
        </article>
      </section>

      <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
        <div class="border-b border-slate-200 px-5 py-4">
          <h2 class="font-extrabold text-slate-900">Ubicaciones registradas</h2>
          <p class="mt-1 text-xs text-slate-500">{{ stalls.length }} {{ stalls.length === 1 ? 'puesto' : 'puestos' }}</p>
        </div>
        <div v-if="stalls.length" class="divide-y divide-slate-100">
          <article v-for="stall in stalls" :key="stall.id" class="flex flex-wrap items-center justify-between gap-3 px-5 py-4">
            <div class="flex min-w-0 items-start gap-3">
              <span :class="['mt-1 h-3 w-3 shrink-0 rounded-full', stall.is_active ? 'bg-blue-600' : 'bg-slate-400']"></span>
              <div class="min-w-0">
                <h3 class="font-bold text-slate-900">{{ stall.name }}</h3>
                <p class="mt-0.5 text-sm text-slate-600">{{ stall.address || 'Sin dirección agregada' }}</p>
                <p class="mt-1 font-mono text-[11px] text-slate-500">{{ stall.latitude }}, {{ stall.longitude }}</p>
              </div>
            </div>
            <div class="flex shrink-0 gap-2">
              <button type="button" @click="editStall(stall)" class="rounded-lg border border-slate-300 px-3 py-2 text-xs font-bold text-slate-700 hover:bg-slate-100">Editar</button>
              <button type="button" @click="toggleAvailability(stall)" :class="['rounded-lg px-3 py-2 text-xs font-bold', stall.is_active ? 'bg-rose-50 text-rose-700 hover:bg-rose-100' : 'bg-emerald-50 text-emerald-700 hover:bg-emerald-100']">
                {{ stall.is_active ? 'Desactivar' : 'Activar' }}
              </button>
            </div>
          </article>
        </div>
        <p v-else class="px-5 py-10 text-center text-sm text-slate-500">Todavía no hay puestos. Usa «Registrar puesto» para agregar el primero.</p>
      </section>

      <FloatingModal
        v-model:open="isStallFormOpen"
        :title="editingStallId ? 'Editar puesto fijo' : 'Registrar puesto fijo'"
        title-id="stall-form-modal-title"
        description="Completa los datos y marca la ubicación en el mapa de esta ventana."
      >
        <div class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_minmax(20rem,0.9fr)]">
          <aside class="order-1 space-y-3 xl:order-2">
            <div>
              <h3 class="text-sm font-extrabold text-slate-900">Ubicación del puesto</h3>
              <p class="mt-1 text-xs text-slate-500">Toca el mapa para seleccionar o actualizar las coordenadas.</p>
            </div>
            <LocationMap :markers="mapMarkers" pick-location compact @location-selected="setLocation" />
          </aside>

          <form class="order-2 space-y-4 xl:order-1" @submit.prevent="saveStall">
            <label class="block text-sm font-bold text-slate-700">
              Nombre del puesto
              <input v-model="form.name" data-modal-autofocus required maxlength="255" class="mt-1 w-full rounded-xl border-slate-300 bg-white px-3 py-2.5 text-sm text-slate-800 focus:border-emerald-500 focus:ring-emerald-500" />
              <span v-if="form.errors.name" class="mt-1 block text-xs text-rose-600">{{ form.errors.name }}</span>
            </label>

            <label class="block text-sm font-bold text-slate-700">
              Dirección o referencia
              <input v-model="form.address" maxlength="255" class="mt-1 w-full rounded-xl border-slate-300 bg-white px-3 py-2.5 text-sm text-slate-800 focus:border-emerald-500 focus:ring-emerald-500" />
              <span v-if="form.errors.address" class="mt-1 block text-xs text-rose-600">{{ form.errors.address }}</span>
            </label>

            <div class="grid grid-cols-2 gap-3">
              <label class="block text-xs font-bold text-slate-600">
                Latitud
                <input v-model="form.latitude" type="number" step="0.0000001" min="-90" max="90" required class="mt-1 w-full rounded-lg border-slate-300 px-3 py-2 text-sm text-slate-800 focus:border-emerald-500 focus:ring-emerald-500" />
                <span v-if="form.errors.latitude" class="mt-1 block text-[11px] text-rose-600">{{ form.errors.latitude }}</span>
              </label>
              <label class="block text-xs font-bold text-slate-600">
                Longitud
                <input v-model="form.longitude" type="number" step="0.0000001" min="-180" max="180" required class="mt-1 w-full rounded-lg border-slate-300 px-3 py-2 text-sm text-slate-800 focus:border-emerald-500 focus:ring-emerald-500" />
                <span v-if="form.errors.longitude" class="mt-1 block text-[11px] text-rose-600">{{ form.errors.longitude }}</span>
              </label>
            </div>
            <p v-if="form.errors.stall" class="text-sm text-rose-600">{{ form.errors.stall }}</p>

            <div class="flex justify-end gap-2 border-t border-slate-100 pt-4">
              <button type="button" @click="cancelEdit" class="rounded-lg border border-slate-300 px-4 py-2.5 text-sm font-bold text-slate-700 hover:bg-slate-100">Cancelar</button>
              <button type="submit" :disabled="form.processing" class="inline-flex items-center gap-2 rounded-lg bg-blue-700 px-4 py-2.5 text-sm font-bold text-white transition hover:bg-blue-800 disabled:cursor-wait disabled:opacity-60">
                <UiIcon name="store" :size="16" />
                {{ form.processing ? 'Guardando…' : editingStallId ? 'Guardar cambios' : 'Guardar puesto' }}
              </button>
            </div>
          </form>
        </div>
      </FloatingModal>
    </div>
  </AdminLayout>
</template>
