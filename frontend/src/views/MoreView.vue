<template>
  <div class="more-view">
    <c-card>
      <p class="more-view__eyebrow">Plus</p>
      <h1>Outils complementaires</h1>
      <p>Ce panneau regroupera les actions secondaires, exports et preferences operateur.</p>
    </c-card>
  </div>
</template>

<script setup lang="ts">
import CCard from '../components/ui/CCard.vue'
</script>

<style scoped lang="scss">
@use '../css/breakpoints.sass' as bp;

.more-view {
  min-width: 0;
}

.more-view__eyebrow {
  margin: 0 0 6px;
  color: #718096;
  font-size: 0.75rem;
  text-transform: uppercase;
  letter-spacing: 0.12em;
}

h1 {
  margin: 0 0 10px;
  font-size: clamp(1.5rem, 5vw, 2rem);
  font-weight: 600;
}

p {
  margin: 0;
  color: #4a5568;
}
</style>
