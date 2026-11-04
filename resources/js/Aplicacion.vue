<script setup>
import { ref } from 'vue';

const title = ref('Bienvenido a Pollos Misme');
const count = ref(0);
</script>

<template>
  <div class="min-h-screen flex flex-col items-center justify-center bg-gray-50 text-gray-800 p-6">
    <div class="max-w-md w-full bg-white rounded-xl shadow-md p-8 text-center space-y-4">
      <h1 class="text-3xl font-bold text-primary-600">{{ title }}</h1>
      <p class="text-gray-600">
        Proyecto Laravel con Vue 3 y Vite configurado correctamente.
      </p>
      
      <div class="pt-4">
        <button 
          @click="count++" 
          class="px-5 py-2.5 bg-primary-600 text-white font-medium rounded-lg hover:bg-primary-700 transition"
        >
          Contador interactivo: {{ count }}
        </button>
      </div>
    </div>
  </div>
</template>
