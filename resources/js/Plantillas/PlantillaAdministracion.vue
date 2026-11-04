<script setup>
import { computed, onMounted, ref } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import FullscreenToggle from '../Componentes/BotonPantallaCompleta.vue';
import UiIcon from '../Componentes/Icono.vue';

defineProps({
  title: { type: String, default: 'Panel principal' },
  description: { type: String, default: 'Pollos Misme' },
});

const page = usePage();
const currentUser = computed(() => page.props.auth?.user);
const isMobileMenuOpen = ref(false);
const isDarkMode = ref(false);

const isCurrentPage = (href) => page.url.split('?')[0] === href;
const logout = () => router.post('/logout');

const toggleDarkMode = () => {
  isDarkMode.value = !isDarkMode.value;
  document.documentElement.classList.toggle('dark', isDarkMode.value);

  try {
    localStorage.setItem('pollos-misme-theme', isDarkMode.value ? 'dark' : 'light');
  } catch {
    // Keep the selected theme for this session if storage is unavailable.
  }
};

onMounted(() => {
  isDarkMode.value = document.documentElement.classList.contains('dark');
});
</script>

<template>
  <Head :title="title" />

  <div class="flex h-dvh overflow-hidden bg-slate-100 font-sans text-slate-900">
    <div
      v-if="isMobileMenuOpen"
      class="fixed inset-0 z-40 bg-slate-950/50 md:hidden"
      aria-hidden="true"
      @click="isMobileMenuOpen = false"
    ></div>

    <aside
      :class="[
        'fixed inset-y-0 left-0 z-50 flex w-64 shrink-0 flex-col bg-slate-900 text-white shadow-xl transition-transform md:static md:z-auto md:translate-x-0 md:shadow-none',
        isMobileMenuOpen ? 'translate-x-0' : '-translate-x-full',
      ]"
      aria-label="Navegación principal"
    >
      <Link href="/admin" class="flex h-16 shrink-0 items-center gap-3 border-b border-slate-700 px-5 text-white no-underline">
        <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-primary-600">
          <UiIcon name="utensils" :size="21" />
        </span>
        <span>
          <span class="brand-wordmark brand-wordmark-inverse block text-lg">Pollos <span class="brand-wordmark-accent">Misme</span></span>
          <span class="mt-0.5 block text-xs text-slate-400">Administración</span>
        </span>
      </Link>

      <nav class="flex-1 space-y-1 overflow-y-auto px-3 py-5">
        <p class="px-3 pb-2 text-[10px] font-extrabold uppercase tracking-[0.16em] text-slate-400">Principal</p>
        <Link
          href="/admin"
          @click="isMobileMenuOpen = false"
          :class="[
            'flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-semibold no-underline transition',
            isCurrentPage('/admin') ? 'bg-primary-600 text-white shadow-sm' : 'text-slate-300 hover:bg-slate-800 hover:text-white',
          ]"
        >
          <UiIcon name="dashboard" :size="18" />
          <span>Panel principal</span>
        </Link>
        <Link
          href="/pos"
          @click="isMobileMenuOpen = false"
          :class="[
            'flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-semibold no-underline transition',
            isCurrentPage('/pos') ? 'bg-primary-600 text-white shadow-sm' : 'text-slate-300 hover:bg-slate-800 hover:text-white',
          ]"
        >
          <UiIcon name="receipt" :size="18" />
          <span>Punto de venta</span>
        </Link>

        <p class="mb-2 mt-7 px-3 text-[10px] font-extrabold uppercase tracking-[0.16em] text-slate-400">Administración</p>
        <Link
          href="/admin/catalog"
          @click="isMobileMenuOpen = false"
          :class="[
            'flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-semibold no-underline transition',
            isCurrentPage('/admin/catalog') ? 'bg-primary-600 text-white shadow-sm' : 'text-slate-300 hover:bg-slate-800 hover:text-white',
          ]"
        >
          <UiIcon name="plate" :size="18" />
          <span>Catálogo</span>
        </Link>
        <Link
          href="/admin/accounting"
          @click="isMobileMenuOpen = false"
          :class="[
            'flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-semibold no-underline transition',
            isCurrentPage('/admin/accounting') ? 'bg-primary-600 text-white shadow-sm' : 'text-slate-300 hover:bg-slate-800 hover:text-white',
          ]"
        >
          <UiIcon name="chart" :size="18" />
          <span>Contabilidad</span>
        </Link>
        <Link
          href="/admin/inventory"
          @click="isMobileMenuOpen = false"
          :class="[
            'flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-semibold no-underline transition',
            isCurrentPage('/admin/inventory') ? 'bg-primary-600 text-white shadow-sm' : 'text-slate-300 hover:bg-slate-800 hover:text-white',
          ]"
        >
          <UiIcon name="box" :size="18" />
          <span>Inventario</span>
        </Link>

      </nav>

      <div class="border-t border-slate-700 p-4">
        <p class="text-xs font-semibold text-slate-300">Administración local</p>
        <p class="mt-1 text-[11px] text-slate-500">Acceso a las herramientas del restaurante.</p>
      </div>
    </aside>

    <div class="flex min-w-0 flex-1 flex-col overflow-hidden">
      <header class="z-20 flex h-16 shrink-0 items-center justify-between border-b border-slate-200 bg-white px-3 shadow-sm sm:px-6">
        <div class="flex min-w-0 items-center gap-3">
          <button
            type="button"
            class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl border border-slate-200 bg-white text-slate-700 transition hover:bg-slate-100 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500 md:hidden"
            aria-label="Abrir menú"
            :aria-expanded="isMobileMenuOpen"
            @click="isMobileMenuOpen = true"
          >
            <UiIcon name="menu" :size="20" />
          </button>
          <div class="min-w-0">
            <p class="truncate text-base font-extrabold text-slate-900 sm:text-lg">{{ title }}</p>
            <p class="hidden truncate text-xs text-slate-500 sm:block">{{ description }}</p>
          </div>
        </div>

        <div class="flex shrink-0 items-center gap-2 sm:gap-3">
          <FullscreenToggle />
          <button
            type="button"
            @click="toggleDarkMode"
            :aria-label="isDarkMode ? 'Cambiar a modo claro' : 'Cambiar a modo noche'"
            :title="isDarkMode ? 'Cambiar a modo claro' : 'Cambiar a modo noche'"
            class="flex h-10 w-10 items-center justify-center rounded-xl border border-slate-200 bg-white text-slate-700 transition hover:bg-slate-100 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500"
          >
            <UiIcon :name="isDarkMode ? 'sun' : 'moon'" :size="18" />
          </button>
          <span class="hidden max-w-32 truncate text-xs font-semibold text-slate-600 sm:block">{{ currentUser?.name }}</span>
          <button type="button" @click="logout" aria-label="Cerrar sesión" title="Cerrar sesión" class="flex h-10 w-10 items-center justify-center rounded-xl border border-slate-200 bg-white text-slate-600 transition hover:bg-rose-50 hover:text-rose-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-rose-500">
            <UiIcon name="logout" :size="18" />
          </button>
        </div>
      </header>

      <main class="min-h-0 flex-1 overflow-y-auto bg-slate-100 p-4 sm:p-6 lg:p-8">
        <slot />
      </main>
    </div>
  </div>
</template>
