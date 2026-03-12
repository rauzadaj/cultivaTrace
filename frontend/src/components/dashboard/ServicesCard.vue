<template>
  <v-card flat class="surface-card services-card">
    <div class="section-header section-header--compact">
      <div>
        <p class="section-header__eyebrow">Services</p>
        <h2>{{ title }}</h2>
      </div>
    </div>

    <div :class="dense ? 'services-grid' : 'service-list'">
      <article v-for="service in items" :key="service.title" :class="dense ? 'service-panel' : 'service-item'">
        <div :class="dense ? 'service-panel__header' : 'service-item__header'">
          <div class="service-item__icon" :class="`service-item__icon--${service.tone}`">
            <v-icon :icon="service.icon" size="20" />
          </div>
          <div>
            <strong>{{ service.title }}</strong>
            <p>{{ service.description }}</p>
          </div>
        </div>
        <div v-if="dense" class="service-panel__body">
          <span>Realtime status</span>
          <strong>{{ service.status }}</strong>
        </div>
      </article>
    </div>
  </v-card>
</template>

<script setup lang="ts">
interface ServiceCardItem {
  title: string
  description: string
  icon: string
  tone: 'primary' | 'warning' | 'success'
  status: string
}

withDefaults(defineProps<{
  title?: string
  dense?: boolean
  items: ServiceCardItem[]
}>(), {
  title: 'System health',
  dense: false,
})
</script>

<style scoped>
.surface-card {
  border: 1px solid #dbe4ea;
  border-radius: 10px;
  background: #fff;
  box-shadow: 0 1px 2px rgba(23, 35, 45, 0.06);
}

.services-card {
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

.service-list,
.services-grid {
  display: grid;
  gap: 12px;
}

.services-grid {
  grid-template-columns: repeat(2, minmax(0, 1fr));
}

.service-item,
.service-panel {
  padding: 12px;
  border: 1px solid #e4e8ec;
  border-radius: 8px;
  background: #fafbfc;
}

.service-item__header,
.service-panel__header {
  display: grid;
  grid-template-columns: auto 1fr;
  gap: 12px;
}

.service-panel__body {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  margin-top: 12px;
  padding-top: 10px;
  border-top: 1px solid #e4e8ec;
  color: #607d8b;
}

.service-item__icon {
  display: grid;
  place-items: center;
  width: 34px;
  height: 34px;
  border-radius: 10px;
}

.service-item__icon--primary {
  background: #e0f7fa;
  color: #00838f;
}

.service-item__icon--warning {
  background: #fff3e0;
  color: #ef6c00;
}

.service-item__icon--success {
  background: #e8f5e9;
  color: #2e7d32;
}

.service-item strong,
.service-panel strong {
  display: block;
  font-size: 0.96rem;
  font-weight: 600;
}

.service-item p,
.service-panel p {
  margin: 0;
  color: #78909c;
}

@media (max-width: 1279px) {
  .services-grid {
    grid-template-columns: 1fr;
  }
}
</style>
