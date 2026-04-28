<template>
  <AppErrorBoundary>
    <RouterView />
  </AppErrorBoundary>
</template>

<script setup lang="ts">
import { RouterView } from 'vue-router'
import AppErrorBoundary from './components/AppErrorBoundary.vue'
</script>

<style>
@import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap');

:root {
  font-family: 'Inter', sans-serif;
  color: #1a202c;
  background: #f7f8fa;
}

html,
body {
  margin: 0;
  width: 100%;
  min-height: 100%;
  font-family: 'Inter', sans-serif;
  font-size: 16px;
  background: #f7f8fa;
  overflow-x: hidden;
}

#app {
  min-height: 100vh;
}

* {
  box-sizing: border-box;
}

img,
.q-card,
.q-img {
  max-width: 100%;
}

.q-header {
  padding-top: env(safe-area-inset-top);
}

.q-footer {
  padding-bottom: env(safe-area-inset-bottom);
}

.fab-button {
  bottom: calc(72px + env(safe-area-inset-bottom));
}

button,
a,
input,
select,
textarea {
  touch-action: manipulation;
}
</style>
