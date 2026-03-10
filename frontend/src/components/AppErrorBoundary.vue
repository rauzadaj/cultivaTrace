<template>
  <div v-if="hasError" class="error-shell">
    <div class="error-card">
      <p class="eyebrow">Runtime fault</p>
      <h1>Dashboard unavailable</h1>
      <p>{{ message }}</p>
      <button type="button" @click="reset">Reload view</button>
    </div>
  </div>
  <slot v-else />
</template>

<script setup lang="ts">
import { onErrorCaptured, ref } from 'vue'

const hasError = ref(false)
const message = ref('Unknown runtime error.')

onErrorCaptured((error) => {
  hasError.value = true
  message.value = error instanceof Error ? error.message : 'Unknown runtime error.'

  return false
})

function reset() {
  hasError.value = false
  message.value = 'Unknown runtime error.'
  window.location.reload()
}
</script>

<style scoped>
.error-shell {
  min-height: 100vh;
  display: grid;
  place-items: center;
  padding: 24px;
  background:
    radial-gradient(circle at top left, rgba(155, 44, 44, 0.18), transparent 34%),
    linear-gradient(135deg, #fff8ef 0%, #f6eee1 100%);
}

.error-card {
  max-width: 520px;
  padding: 32px;
  border-radius: 24px;
  background: #fffdf8;
  border: 1px solid rgba(87, 35, 18, 0.12);
  box-shadow: 0 24px 64px rgba(59, 26, 13, 0.12);
}

.eyebrow {
  margin: 0 0 8px;
  text-transform: uppercase;
  letter-spacing: 0.14em;
  font-size: 0.75rem;
  color: #9b2c2c;
}

h1 {
  margin: 0 0 12px;
  font-size: 2rem;
}

button {
  margin-top: 12px;
  border: 0;
  border-radius: 999px;
  padding: 12px 18px;
  background: #195b39;
  color: #fff;
  cursor: pointer;
}
</style>
