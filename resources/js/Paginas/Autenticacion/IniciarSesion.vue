<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import FullscreenToggle from '../../Componentes/BotonPantallaCompleta.vue';

const form = useForm({
  email: '',
  password: '',
});

const submit = () => {
  form.post('/login', {
    onFinish: () => form.reset('password'),
  });
};
</script>

<template>
  <Head title="Iniciar sesión" />

  <main class="flex min-h-dvh items-center justify-center bg-slate-100 px-4 py-10">
    <div class="fixed right-4 top-4 z-10">
      <FullscreenToggle />
    </div>
    <section class="w-full max-w-md rounded-3xl border border-slate-200 bg-white p-7 shadow-xl sm:p-9">
      <div class="mb-8 text-center">
        <img :src="'/images/pollos-misme-logo.png'" alt="Pollos Misme" class="mx-auto mb-4 h-44 w-44 object-contain drop-shadow-xl">
        <h1 class="sr-only">Pollos Misme</h1>
        <p class="text-sm text-slate-500">Ingresa con tu correo y PIN de personal</p>
      </div>

      <form class="space-y-5" @submit.prevent="submit">
        <div>
          <label for="email" class="mb-1 block text-sm font-bold text-slate-700">Correo electrónico</label>
          <input
            id="email"
            v-model="form.email"
            type="email"
            autocomplete="username"
            required
            autofocus
            class="w-full rounded-xl border-slate-300 px-4 py-3 text-sm focus:border-primary-500 focus:ring-primary-500"
          />
          <p v-if="form.errors.email" class="mt-1 text-sm text-rose-600">{{ form.errors.email }}</p>
        </div>

        <div>
          <label for="password" class="mb-1 block text-sm font-bold text-slate-700">PIN de 4 dígitos</label>
          <input
            id="password"
            v-model="form.password"
            type="password"
            inputmode="numeric"
            pattern="[0-9]{4}"
            minlength="4"
            maxlength="4"
            autocomplete="current-password"
            required
            class="w-full rounded-xl border-slate-300 px-4 py-3 text-lg tracking-[0.4em] focus:border-primary-500 focus:ring-primary-500"
          />
          <p v-if="form.errors.password" class="mt-1 text-sm text-rose-600">{{ form.errors.password }}</p>
        </div>

        <button type="submit" :disabled="form.processing" class="flex min-h-12 w-full items-center justify-center gap-2 rounded-xl bg-primary-600 px-4 py-3 font-bold text-white transition hover:bg-primary-700 disabled:cursor-wait disabled:opacity-60">
          {{ form.processing ? 'Ingresando…' : 'Ingresar' }}
        </button>
      </form>

      <p class="mt-6 text-center text-xs text-slate-500">Acceso reservado al personal del restaurante.</p>
    </section>
  </main>
</template>
