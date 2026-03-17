<template>
  <q-card flat class="surface-card session-card mb-4">
    <div class="section-header section-header--compact">
      <div>
        <p class="section-header__eyebrow">Console</p>
        <h2>Workspace access</h2>
      </div>
    </div>

    <form class="session-form" @submit.prevent>
      <q-input
        :model-value="apiBaseUrl"
        label="API base URL"
        outlined
        @update:model-value="$emit('update:apiBaseUrl', String($event))"
      />
      <q-input
        :model-value="operatorLabel"
        label="Operateur"
        outlined
        @update:model-value="$emit('update:operatorLabel', String($event))"
      />
      <q-input
        :model-value="userEmail"
        label="Compte"
        outlined
        readonly
      />
    </form>
  </q-card>
</template>

<script setup lang="ts">
defineProps<{
  apiBaseUrl: string
  operatorLabel: string
  userEmail: string
}>()

defineEmits<{
  'update:apiBaseUrl': [value: string]
  'update:operatorLabel': [value: string]
}>()
</script>

<style scoped>
.surface-card {
  border: 1px solid #dbe4ea;
  border-radius: 10px;
  background: #fff;
  box-shadow: 0 1px 2px rgba(23, 35, 45, 0.06);
}

.session-card {
  padding: 18px;
}

.section-header {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 16px;
  margin-bottom: 18px;
}

.section-header--compact {
  margin-bottom: 14px;
}

.section-header__eyebrow {
  margin: 0 0 4px;
  color: #90a4ae;
  font-size: 0.72rem;
  text-transform: uppercase;
  letter-spacing: 0.12em;
}

.section-header h2 {
  margin: 0;
  color: #37474f;
  font-size: 1.2rem;
  font-weight: 500;
}

.session-form {
  display: grid;
  gap: 12px;
}
</style>
