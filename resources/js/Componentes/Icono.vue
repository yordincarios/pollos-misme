<script setup>
defineProps({
  name: { type: String, required: true },
  size: { type: [String, Number], default: 20 },
});
</script>

<template>
  <svg
    :width="size"
    :height="size"
    viewBox="0 0 24 24"
    fill="none"
    stroke="currentColor"
    stroke-width="1.8"
    stroke-linecap="round"
    stroke-linejoin="round"
    aria-hidden="true"
    focusable="false"
  >
    <template v-if="name === 'utensils'">
      <path d="M5 3v6a2 2 0 0 0 4 0V3M7 3v18M16 3v18M16 3c3 2 4 5 4 8h-4" />
    </template>
    <template v-else-if="name === 'plate'">
      <circle cx="12" cy="12" r="9" />
      <circle cx="12" cy="12" r="6" />
    </template>
    <template v-else-if="name === 'search'">
      <circle cx="11" cy="11" r="7" />
      <path d="m20 20-4-4" />
    </template>
    <template v-else-if="name === 'receipt'">
      <path d="M5 3h14v18l-3.5-2-3.5 2-3.5-2L5 21V3Z" />
      <path d="M8 8h8M8 12h8M8 16h4" />
    </template>
    <template v-else-if="name === 'cart'">
      <path d="M3 4h2l2.2 10.5a2 2 0 0 0 2 1.5h8.2a2 2 0 0 0 2-1.6L21 8H6" />
      <circle cx="10" cy="20" r="1" />
      <circle cx="18" cy="20" r="1" />
    </template>
    <template v-else-if="name === 'note'">
      <path d="M14 3H6a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V9Z" />
      <path d="M14 3v6h6M8 13h8M8 17h6" />
    </template>
    <template v-else-if="name === 'cash'">
      <rect x="3" y="6" width="18" height="12" rx="2" />
      <circle cx="12" cy="12" r="3" />
      <path d="M7 9h.01M17 15h.01" />
    </template>
    <template v-else-if="name === 'card'">
      <rect x="3" y="5" width="18" height="14" rx="2" />
      <path d="M3 10h18M7 15h3" />
    </template>
    <template v-else-if="name === 'transfer'">
      <path d="M4 7h15l-3-3M20 17H5l3 3" />
      <path d="M19 7v4M5 13v4" />
    </template>
    <template v-else-if="name === 'settings'">
      <path d="M4 6h16M4 12h16M4 18h16" />
      <circle cx="9" cy="6" r="2" fill="currentColor" />
      <circle cx="15" cy="12" r="2" fill="currentColor" />
      <circle cx="11" cy="18" r="2" fill="currentColor" />
    </template>
    <template v-else-if="name === 'download'">
      <path d="M12 3v12m0 0 4-4m-4 4-4-4M4 17v2a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-2" />
    </template>
    <template v-else-if="name === 'printer'">
      <path d="M6 9V3h12v6M6 17H4a2 2 0 0 1-2-2v-4a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v4a2 2 0 0 1-2 2h-2" />
      <path d="M6 14h12v7H6zM18 12h.01" />
    </template>
    <template v-else-if="name === 'sun'">
      <circle cx="12" cy="12" r="4" />
      <path d="M12 2v2M12 20v2M4.93 4.93l1.42 1.42m11.3 11.3 1.42 1.42M2 12h2m16 0h2M4.93 19.07l1.42-1.42m11.3-11.3 1.42-1.42" />
    </template>
    <template v-else-if="name === 'moon'">
      <path d="M20.9 13A9 9 0 0 1 11 3.1 9 9 0 1 0 20.9 13Z" />
    </template>
    <template v-else-if="name === 'expand'">
      <path d="M8 3H5a2 2 0 0 0-2 2v3M16 3h3a2 2 0 0 1 2 2v3M21 16v3a2 2 0 0 1-2 2h-3M3 16v3a2 2 0 0 0 2 2h3" />
      <path d="M8 8 3 3m13 5 5-5m-5 13 5 5M8 16l-5 5" />
    </template>
    <template v-else-if="name === 'compress'">
      <path d="M8 3v5H3m18 0h-5V3m0 18v-5h5M3 16h5v5" />
      <path d="m8 8-5-5m13 5 5-5m-5 13 5 5M8 16l-5 5" />
    </template>
    <template v-else-if="name === 'qr'">
      <path d="M3 3h7v7H3zM14 3h7v7h-7zM3 14h7v7H3z" />
      <path d="M6 6h1v1H6zM17 6h1v1h-1zM6 17h1v1H6zM14 14h3v3h-3zM19 14v2M19 19h2M14 20h2" />
    </template>
    <template v-else-if="name === 'dashboard'">
      <rect x="3" y="3" width="8" height="8" rx="1.5" />
      <rect x="13" y="3" width="8" height="5" rx="1.5" />
      <rect x="13" y="10" width="8" height="11" rx="1.5" />
      <rect x="3" y="13" width="8" height="8" rx="1.5" />
    </template>
    <template v-else-if="name === 'chart'">
      <path d="M3 3v18h18M8 16v-5m5 5V6m5 10V9" />
    </template>
    <template v-else-if="name === 'map-pin'">
      <path d="M20 10c0 5-8 11-8 11S4 15 4 10a8 8 0 1 1 16 0Z" />
      <circle cx="12" cy="10" r="2.5" />
    </template>
    <template v-else-if="name === 'store'">
      <path d="M3 10v11h18V10M3 10l2-7h14l2 7" />
      <path d="M3 10a3 3 0 0 0 6 0 3 3 0 0 0 6 0 3 3 0 0 0 6 0M9 21v-6h6v6" />
    </template>
    <template v-else-if="name === 'plus'">
      <path d="M12 5v14M5 12h14" />
    </template>
    <template v-else-if="name === 'trash'">
      <path d="M4 7h16M10 11v6M14 11v6M5 7l1 14h12l1-14M9 7V4h6v3" />
    </template>
    <template v-else-if="name === 'box'">
      <path d="m12 3 8 4.5v9L12 21l-8-4.5v-9L12 3Z" />
      <path d="m4 7.5 8 4.5 8-4.5M12 12v9" />
    </template>
    <template v-else-if="name === 'menu'">
      <path d="M4 6h16M4 12h16M4 18h16" />
    </template>
    <template v-else-if="name === 'wallet'">
      <path d="M3 7a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2v10a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V7Z" />
      <path d="M16 12h5M3 9h18" />
    </template>
    <template v-else-if="name === 'users'">
      <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2" />
      <circle cx="9" cy="7" r="4" />
      <path d="M22 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75" />
    </template>
    <template v-else-if="name === 'arrow-left'">
      <path d="M19 12H5M12 19l-7-7 7-7" />
    </template>
    <template v-else-if="name === 'logout'">
      <path d="M10 17l5-5-5-5M15 12H3" />
      <path d="M12 3h6a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-6" />
    </template>
  </svg>
</template>
