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

body {
  margin: 0;
  font-family: 'Inter', sans-serif;
  font-size: 16px;
  background: #f7f8fa;
}

* {
  box-sizing: border-box;
}

button,
a,
input,
select,
textarea {
  touch-action: manipulation;
}
</style>
