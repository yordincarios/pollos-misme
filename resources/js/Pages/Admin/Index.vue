<script setup>
import { Link } from '@inertiajs/vue3';
import AdminLayout from '../../Layouts/AdminLayout.vue';
import UiIcon from '../../Components/UiIcon.vue';
</script>

<template>
  <AdminLayout title="Panel principal" description="Restaurante 2 · Administración">
    <div class="mx-auto max-w-6xl space-y-6">
      <section class="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm">
        <div class="p-6 sm:p-8 lg:p-10">
          <div>
            <p class="text-xs font-extrabold uppercase tracking-[0.16em] text-emerald-700">Restaurante 2</p>
            <h1 class="mt-3 text-3xl font-black tracking-tight text-slate-900 sm:text-4xl">Bienvenido</h1>
            <p class="mt-3 max-w-2xl text-sm leading-relaxed text-slate-600 sm:text-base">
              Administra ventas, puestos fijos y pedidos de WhatsApp desde este panel.
            </p>
          </div>
        </div>
      </section>

      <section aria-labelledby="modules-title">
        <div class="mb-4">
          <h2 id="modules-title" class="text-lg font-extrabold text-slate-900">Módulos del sistema</h2>
          <p class="mt-1 text-sm text-slate-600">Accesos a caja, contabilidad y operación diaria.</p>
        </div>

        <div class="grid gap-4 md:grid-cols-3">
          <Link href="/admin/accounting" class="block rounded-2xl border border-slate-200 bg-white p-5 text-inherit no-underline shadow-sm transition hover:-translate-y-0.5 hover:shadow-md">
            <span class="flex h-11 w-11 items-center justify-center rounded-xl bg-amber-50 text-amber-800">
              <UiIcon name="chart" :size="21" />
            </span>
            <h3 class="mt-4 font-extrabold text-slate-900">Contabilidad</h3>
            <p class="mt-2 text-sm leading-relaxed text-slate-600">Reportes de ventas y control de caja.</p>
            <span class="mt-4 inline-flex items-center gap-2 text-xs font-bold text-amber-800">Ver ventas y caja <span aria-hidden="true">→</span></span>
          </Link>

          <Link href="/admin/orders" class="block rounded-2xl border border-slate-200 bg-white p-5 text-inherit no-underline shadow-sm transition hover:-translate-y-0.5 hover:shadow-md">
            <span class="flex h-11 w-11 items-center justify-center rounded-xl bg-emerald-50 text-emerald-700">
              <UiIcon name="map-pin" :size="21" />
            </span>
            <h3 class="mt-4 font-extrabold text-slate-900">Pedidos y mapa</h3>
            <p class="mt-2 text-sm leading-relaxed text-slate-600">Ubicaciones de puestos y pedidos recibidos por WhatsApp.</p>
            <span class="mt-4 inline-flex items-center gap-2 text-xs font-bold text-emerald-800">Abrir pedidos y mapa <span aria-hidden="true">→</span></span>
          </Link>

          <Link href="/admin/stalls" class="block rounded-2xl border border-slate-200 bg-white p-5 text-inherit no-underline shadow-sm transition hover:-translate-y-0.5 hover:shadow-md">
            <span class="flex h-11 w-11 items-center justify-center rounded-xl bg-blue-50 text-blue-700">
              <UiIcon name="store" :size="21" />
            </span>
            <h3 class="mt-4 font-extrabold text-slate-900">Puestos</h3>
            <p class="mt-2 text-sm leading-relaxed text-slate-600">Registro y administración de ubicaciones fijas.</p>
            <span class="mt-4 inline-flex items-center gap-2 text-xs font-bold text-blue-800">Administrar puestos <span aria-hidden="true">→</span></span>
          </Link>
        </div>
      </section>
    </div>
  </AdminLayout>
</template>
