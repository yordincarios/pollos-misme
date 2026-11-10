<script setup>
import { computed, ref } from 'vue';
import { router, useForm, usePage } from '@inertiajs/vue3';
import AdminLayout from '../../Plantillas/PlantillaAdministracion.vue';
import ModalFlotante from '../../Componentes/ModalFlotante.vue';
import Icono from '../../Componentes/Icono.vue';

const props = defineProps({
  usuarios: { type: Array, default: () => [] },
  usuarioActualId: { type: Number, required: true },
});

const page = usePage();
const exito = computed(() => page.props.flash?.success || '');
const modalAbierto = ref(false);
const editando = ref(null);

const createForm = useForm({
  name: '',
  email: '',
  password: '',
});

const editForm = useForm({
  name: '',
  email: '',
  password: '',
});

const crear = () => {
  createForm.post('/admin/usuarios', {
    preserveScroll: true,
    onSuccess: () => createForm.reset(),
  });
};

const abrirEdicion = (usuario) => {
  editando.value = usuario;
  editForm.name = usuario.name;
  editForm.email = usuario.email;
  editForm.password = '';
  editForm.clearErrors();
  modalAbierto.value = true;
};

const cerrarEdicion = () => {
  modalAbierto.value = false;
  editando.value = null;
  editForm.clearErrors();
};

const guardarEdicion = () => {
  if (! editando.value) {
    return;
  }

  editForm.patch(`/admin/usuarios/${editando.value.id}`, {
    preserveScroll: true,
    onSuccess: cerrarEdicion,
  });
};

const eliminar = (usuario) => {
  if (usuario.id === props.usuarioActualId) {
    return;
  }

  if (window.confirm(`¿Eliminar al usuario ${usuario.name}?`)) {
    router.delete(`/admin/usuarios/${usuario.id}`, { preserveScroll: true });
  }
};
</script>

<template>
  <AdminLayout title="Usuarios" description="Accesos del personal">
    <div class="mx-auto max-w-4xl space-y-6">
      <section>
        <p class="text-xs font-extrabold uppercase tracking-[0.16em] text-primary-700">Administración</p>
        <h1 class="mt-1 text-2xl font-black tracking-tight text-slate-900 sm:text-3xl">Usuarios</h1>
        <p class="mt-2 text-sm text-slate-600">Crea cuentas de acceso y cambia el PIN del personal.</p>
      </section>

      <div v-if="exito" class="rounded-xl border border-success-200 bg-success-50 p-4 text-sm font-semibold text-success-900">{{ exito }}</div>

      <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
        <h2 class="font-extrabold text-slate-900">Nuevo usuario</h2>
        <p class="mt-1 text-xs text-slate-500">El PIN debe tener 4 dígitos.</p>
        <form class="mt-4 grid gap-3 sm:grid-cols-2" @submit.prevent="crear">
          <label class="grid gap-1 text-xs font-bold text-slate-600">
            Nombre
            <input v-model="createForm.name" required maxlength="255" class="rounded-xl border-slate-300 px-3 py-2.5 text-sm focus:border-primary-500 focus:ring-primary-500" />
            <span v-if="createForm.errors.name" class="text-xs font-normal text-rose-600">{{ createForm.errors.name }}</span>
          </label>
          <label class="grid gap-1 text-xs font-bold text-slate-600">
            Correo
            <input v-model="createForm.email" type="email" required maxlength="255" class="rounded-xl border-slate-300 px-3 py-2.5 text-sm focus:border-primary-500 focus:ring-primary-500" />
            <span v-if="createForm.errors.email" class="text-xs font-normal text-rose-600">{{ createForm.errors.email }}</span>
          </label>
          <label class="grid gap-1 text-xs font-bold text-slate-600">
            PIN de 4 dígitos
            <input v-model="createForm.password" type="password" inputmode="numeric" pattern="[0-9]{4}" minlength="4" maxlength="4" required autocomplete="new-password" class="rounded-xl border-slate-300 px-3 py-2.5 text-sm focus:border-primary-500 focus:ring-primary-500" />
            <span v-if="createForm.errors.password" class="text-xs font-normal text-rose-600">{{ createForm.errors.password }}</span>
          </label>
          <div class="flex items-end">
            <button type="submit" :disabled="createForm.processing" class="inline-flex min-h-10 items-center gap-2 rounded-xl bg-primary-700 px-4 py-2.5 text-sm font-bold text-white hover:bg-primary-800 disabled:opacity-60">
              <Icono name="plus" :size="16" />
              Crear usuario
            </button>
          </div>
        </form>
      </section>

      <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
        <header class="border-b border-slate-200 px-5 py-4">
          <h2 class="font-extrabold text-slate-900">Usuarios del sistema</h2>
          <p class="mt-1 text-xs text-slate-500">{{ usuarios.length }} {{ usuarios.length === 1 ? 'usuario' : 'usuarios' }}</p>
        </header>
        <div class="divide-y divide-slate-100">
          <article v-for="usuario in usuarios" :key="usuario.id" class="flex flex-wrap items-center justify-between gap-3 px-5 py-4">
            <div class="flex min-w-0 items-center gap-3">
              <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-primary-50 text-sm font-black text-primary-700">{{ usuario.name.charAt(0).toUpperCase() }}</span>
              <div class="min-w-0">
                <div class="flex items-center gap-2">
                  <h3 class="truncate text-sm font-bold text-slate-900">{{ usuario.name }}</h3>
                  <span v-if="usuario.id === usuarioActualId" class="rounded-full bg-slate-100 px-2 py-0.5 text-[10px] font-bold text-slate-500">Vos</span>
                </div>
                <p class="truncate text-xs text-slate-500">{{ usuario.email }}</p>
              </div>
            </div>
            <div class="flex gap-2">
              <button type="button" class="rounded-lg bg-primary-50 px-3 py-2 text-xs font-bold text-primary-700 hover:bg-primary-100" @click="abrirEdicion(usuario)">Editar</button>
              <button v-if="usuario.id !== usuarioActualId" type="button" class="rounded-lg bg-rose-50 px-3 py-2 text-xs font-bold text-rose-700 hover:bg-rose-100" @click="eliminar(usuario)">Eliminar</button>
            </div>
          </article>
        </div>
      </section>
    </div>

    <ModalFlotante
      v-model:open="modalAbierto"
      title="Editar usuario"
      title-id="editar-usuario-title"
      :description="editando ? `Actualiza los datos de ${editando.name}.` : 'Actualiza los datos del usuario.'"
    >
      <form class="mx-auto max-w-lg space-y-4" @submit.prevent="guardarEdicion">
        <label class="grid gap-1 text-sm font-bold text-slate-700">
          Nombre
          <input v-model="editForm.name" required maxlength="255" class="rounded-xl border-slate-300 px-3 py-2.5 text-sm focus:border-primary-500 focus:ring-primary-500" />
          <span v-if="editForm.errors.name" class="text-xs font-normal text-rose-600">{{ editForm.errors.name }}</span>
        </label>
        <label class="grid gap-1 text-sm font-bold text-slate-700">
          Correo
          <input v-model="editForm.email" type="email" required maxlength="255" class="rounded-xl border-slate-300 px-3 py-2.5 text-sm focus:border-primary-500 focus:ring-primary-500" />
          <span v-if="editForm.errors.email" class="text-xs font-normal text-rose-600">{{ editForm.errors.email }}</span>
        </label>
        <label class="grid gap-1 text-sm font-bold text-slate-700">
          Nuevo PIN
          <input v-model="editForm.password" type="password" inputmode="numeric" pattern="[0-9]{4}" minlength="4" maxlength="4" autocomplete="new-password" placeholder="Dejar vacío para conservar el PIN" class="rounded-xl border-slate-300 px-3 py-2.5 text-sm focus:border-primary-500 focus:ring-primary-500" />
          <span v-if="editForm.errors.password" class="text-xs font-normal text-rose-600">{{ editForm.errors.password }}</span>
        </label>
        <div class="flex justify-end gap-2 border-t border-slate-100 pt-4">
          <button type="button" class="rounded-xl border border-slate-300 px-4 py-2.5 text-sm font-bold text-slate-700 hover:bg-slate-100" @click="cerrarEdicion">Cancelar</button>
          <button type="submit" :disabled="editForm.processing" class="rounded-xl bg-primary-700 px-4 py-2.5 text-sm font-bold text-white hover:bg-primary-800 disabled:opacity-60">{{ editForm.processing ? 'Guardando…' : 'Guardar cambios' }}</button>
        </div>
      </form>
    </ModalFlotante>
  </AdminLayout>
</template>
