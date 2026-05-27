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
  /* Backgrounds */
  --ct-bg:            #F7F8FA;
  --ct-surface:       #FFFFFF;
  --ct-surface-2:     #F3F4F6;
  --ct-surface-3:     #E5E7EB;

  /* Borders */
  --ct-border:        #E5E7EB;
  --ct-border-subtle: rgba(0, 0, 0, 0.06);

  /* Text */
  --ct-text-1:        #111827;
  --ct-text-2:        #6B7280;
  --ct-text-3:        #9CA3AF;

  /* Accent */
  --ct-accent:        #16A34A;
  --ct-accent-light:  #F0FDF4;
  --ct-accent-dim:    rgba(22, 163, 74, 0.08);
  --ct-accent-border: rgba(22, 163, 74, 0.2);

  /* Semantic */
  --ct-danger:        #DC2626;
  --ct-danger-light:  #FEF2F2;
  --ct-danger-dim:    rgba(220, 38, 38, 0.08);
  --ct-warning:       #D97706;
  --ct-warning-light: #FFFBEB;
  --ct-warning-dim:   rgba(217, 119, 6, 0.08);
}

html,
body {
  margin: 0;
  width: 100%;
  min-height: 100%;
  font-family: 'Inter', sans-serif;
  font-size: 16px;
  background: #F7F8FA;
  color: #111827;
  overflow-x: hidden;
  -webkit-font-smoothing: antialiased;
  -moz-osx-font-smoothing: grayscale;
}

#app {
  min-height: 100vh;
}

* {
  box-sizing: border-box;
}

/* Headings — tight, like Apple */
h1, h2, h3, h4, h5, h6 {
  letter-spacing: -0.025em;
}

/* Quasar buttons global */

.q-btn {
  font-family: 'Inter', sans-serif;
  font-weight: 600;
  letter-spacing: -0.01em;
  transition: opacity 0.15s, box-shadow 0.15s;
}

.q-btn:not(.q-btn--round) .q-btn__content {
  gap: 6px;
  padding: 0 4px;
}

.q-btn--flat:hover,
.q-btn--outline:hover {
  opacity: 0.85;
}

/* Quasar overrides — full consistency */

.q-field--outlined .q-field__control {
  background: #FFFFFF;
}

.q-field--outlined .q-field__control:before {
  border-color: #E5E7EB;
  transition: border-color 0.15s;
}

.q-field--outlined.q-field--focused .q-field__control:before,
.q-field--outlined.q-field--focused .q-field__control:after {
  border-color: #16A34A;
}

.q-field__label {
  color: #6B7280;
  font-size: 0.875rem;
}

.q-field__native,
.q-field__input {
  color: #111827;
  font-family: 'Inter', sans-serif;
}

.q-menu {
  background: #FFFFFF;
  border: 1px solid #E5E7EB;
  box-shadow: 0 4px 16px rgba(0, 0, 0, 0.08), 0 1px 4px rgba(0, 0, 0, 0.04);
  border-radius: 10px;
}

.q-item {
  color: #111827;
  border-radius: 6px;
  font-size: 0.9rem;
}

.q-item:hover {
  background: #F3F4F6;
}

.q-item.q-item--active,
.q-item.q-router-link--active {
  color: #16A34A;
}

.q-separator {
  background: #E5E7EB;
}

.q-card {
  background: #FFFFFF;
  border: 1px solid #E5E7EB;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.04), 0 1px 8px rgba(0, 0, 0, 0.02);
}

.q-skeleton {
  background: #F3F4F6;
}

.q-chip--outline {
  border-color: var(--ct-accent-border);
  color: var(--ct-accent);
}

.q-banner {
  background: #F9FAFB;
  border: 1px solid #E5E7EB;
  color: #111827;
  border-radius: 10px;
}

.q-badge {
  font-family: 'Inter', sans-serif;
}

/* img */
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

button,
a,
input,
select,
textarea {
  touch-action: manipulation;
}
</style>
