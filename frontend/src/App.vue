<template>
  <v-app>
    <v-navigation-drawer
      v-model="drawer"
      :permanent="$vuetify.display.mdAndUp"
      :temporary="$vuetify.display.smAndDown"
      width="290"
      class="app-drawer"
      border="0"
    >
      <div class="drawer-brand">
        <div class="brand-mark">CT</div>
        <div>
          <div class="brand-title">CultivaTrace</div>
          <div class="brand-subtitle">Agri Ops Cloud</div>
        </div>
      </div>

      <v-list nav class="px-2">
        <v-list-item
          v-for="item in navigation"
          :key="item.title"
          :prepend-icon="item.icon"
          :title="item.title"
          :subtitle="item.subtitle"
          rounded="xl"
          class="mb-1 nav-item"
          :class="{ 'nav-item--active': item.active }"
          :active="item.active"
          color="primary"
        />
      </v-list>

      <template #append>
        <div class="pa-4">
          <v-card class="drawer-card" rounded="xl" elevation="0">
            <v-card-text>
              <div class="text-caption text-medium-emphasis mb-2">
                Saison 2026
              </div>
              <div class="text-h6 font-weight-bold mb-2">
                86% de conformité
              </div>
              <v-progress-linear
                model-value="86"
                color="success"
                bg-color="rgba(255,255,255,0.12)"
                rounded
                height="10"
              />
              <v-btn
                block
                color="white"
                variant="tonal"
                class="mt-4"
                prepend-icon="mdi-file-chart-outline"
              >
                Rapport mensuel
              </v-btn>
            </v-card-text>
          </v-card>
        </div>
      </template>
    </v-navigation-drawer>

    <v-app-bar flat class="app-bar">
      <v-app-bar-nav-icon
        v-if="$vuetify.display.smAndDown"
        @click="drawer = !drawer"
      />
      <v-app-bar-title class="text-body-1 font-weight-bold">
        Tableau de bord
      </v-app-bar-title>

      <div class="top-search d-none d-md-flex">
        <v-icon icon="mdi-magnify" size="18" class="mr-2 text-medium-emphasis" />
        <span class="text-body-2 text-medium-emphasis">
          Rechercher parcelle, lot, traitement...
        </span>
      </div>

      <v-spacer />

      <v-btn icon variant="text">
        <v-badge color="error" dot>
          <v-icon icon="mdi-bell-outline" />
        </v-badge>
      </v-btn>
      <v-btn icon variant="text">
        <v-icon icon="mdi-cog-outline" />
      </v-btn>
      <v-chip class="ml-2 user-chip" rounded="xl" variant="flat">
        <v-avatar size="28" color="primary" class="mr-2">
          <span class="text-caption font-weight-bold">JD</span>
        </v-avatar>
        <span class="user-chip__name">Jonathan</span>
      </v-chip>
    </v-app-bar>

    <v-main>
      <div class="app-shell">
        <v-container fluid class="pa-4 pa-md-6">
          <v-row>
            <v-col cols="12" lg="8">
              <v-card class="hero-card reveal" rounded="xl" elevation="0">
                <v-card-text class="pa-6 pa-md-8">
                  <div class="text-overline hero-overline mb-2">
                    Exploitation • Temps réel
                  </div>
                  <h1 class="hero-title mb-3">
                    Pilotez vos opérations agricoles comme un vrai SaaS.
                  </h1>
                  <p class="hero-subtitle mb-6">
                    Suivi de parcelles, traçabilité, conformité et activités terrain dans une interface unifiée.
                  </p>
                  <div class="d-flex flex-wrap ga-3">
                    <v-btn color="white" size="large" rounded="xl" prepend-icon="mdi-plus">
                      Ajouter une activité
                    </v-btn>
                    <v-btn variant="tonal" color="white" size="large" rounded="xl" prepend-icon="mdi-play-circle-outline">
                      Voir la démo
                    </v-btn>
                  </div>
                </v-card-text>
                <div class="hero-orb hero-orb-1" />
                <div class="hero-orb hero-orb-2" />
              </v-card>
            </v-col>

            <v-col cols="12" lg="4">
              <v-card class="reveal delay-1" rounded="xl" elevation="0">
                <v-card-text class="pa-5">
                  <div class="d-flex align-center justify-space-between mb-4">
                    <div>
                      <div class="text-caption text-medium-emphasis">Santé des cultures</div>
                      <div class="text-h6 font-weight-bold">Indice global</div>
                    </div>
                    <v-chip color="success" variant="tonal" size="small">+4.2%</v-chip>
                  </div>

                  <div class="health-score mb-4">
                    <div class="health-score__ring">
                      <div class="health-score__value">92</div>
                    </div>
                    <div class="health-score__meta">
                      <div class="text-body-2 font-weight-medium">Très bon niveau</div>
                      <div class="text-caption text-medium-emphasis">
                        Basé sur humidité, interventions et anomalies
                      </div>
                    </div>
                  </div>

                  <div
                    v-for="signal in signals"
                    :key="signal.label"
                    class="signal-row"
                  >
                    <div class="d-flex justify-space-between mb-1">
                      <span class="text-body-2">{{ signal.label }}</span>
                      <span class="text-body-2 font-weight-medium">{{ signal.value }}%</span>
                    </div>
                    <v-progress-linear
                      :model-value="signal.value"
                      :color="signal.color"
                      rounded
                      height="8"
                      bg-color="rgba(15,23,42,0.08)"
                    />
                  </div>
                </v-card-text>
              </v-card>
            </v-col>
          </v-row>

          <v-row class="mt-1">
            <v-col
              v-for="(metric, index) in metrics"
              :key="metric.title"
              cols="12"
              sm="6"
              xl="3"
            >
              <v-card
                class="metric-card reveal"
                :class="`delay-${(index % 4) + 1}`"
                rounded="xl"
                elevation="0"
              >
                <v-card-text class="pa-5">
                  <div class="d-flex justify-space-between align-start">
                    <div>
                      <div class="text-caption text-medium-emphasis mb-1">{{ metric.title }}</div>
                      <div class="text-h5 font-weight-bold">{{ metric.value }}</div>
                    </div>
                    <div class="metric-icon" :style="{ background: metric.iconBg }">
                      <v-icon :icon="metric.icon" color="white" />
                    </div>
                  </div>

                  <div class="d-flex align-center justify-space-between mt-5">
                    <v-chip
                      :color="metric.trendColor"
                      size="small"
                      variant="tonal"
                    >
                      {{ metric.trend }}
                    </v-chip>
                    <span class="text-caption text-medium-emphasis">{{ metric.note }}</span>
                  </div>
                </v-card-text>
              </v-card>
            </v-col>
          </v-row>

          <v-row class="mt-1">
            <v-col cols="12" xl="8">
              <v-card class="reveal delay-2" rounded="xl" elevation="0">
                <v-card-item>
                  <template #prepend>
                    <v-avatar color="primary" variant="tonal">
                      <v-icon icon="mdi-map-marker-radius-outline" />
                    </v-avatar>
                  </template>
                  <v-card-title>Activités terrain du jour</v-card-title>
                  <v-card-subtitle>
                    Vue opérationnelle des interventions planifiées et en cours
                  </v-card-subtitle>
                  <template #append>
                    <v-btn variant="text" color="primary">Voir tout</v-btn>
                  </template>
                </v-card-item>

                <v-divider />

                <div class="table-wrap">
                  <v-table class="ops-table">
                    <thead>
                      <tr>
                        <th>Parcelle</th>
                        <th>Intervention</th>
                        <th>Opérateur</th>
                        <th>Heure</th>
                        <th>Statut</th>
                      </tr>
                    </thead>
                    <tbody>
                      <tr v-for="row in operations" :key="row.id">
                        <td>
                          <div class="font-weight-medium">{{ row.plot }}</div>
                          <div class="text-caption text-medium-emphasis">{{ row.surface }}</div>
                        </td>
                        <td>{{ row.action }}</td>
                        <td>{{ row.operator }}</td>
                        <td>{{ row.time }}</td>
                        <td>
                          <v-chip
                            size="small"
                            :color="row.statusColor"
                            variant="tonal"
                            rounded="lg"
                          >
                            {{ row.status }}
                          </v-chip>
                        </td>
                      </tr>
                    </tbody>
                  </v-table>
                </div>
              </v-card>
            </v-col>

            <v-col cols="12" xl="4">
              <v-card class="reveal delay-3 mb-4" rounded="xl" elevation="0">
                <v-card-title class="d-flex align-center justify-space-between">
                  <span>Alertes conformité</span>
                  <v-chip size="small" color="warning" variant="tonal">3 ouvertes</v-chip>
                </v-card-title>
                <v-card-text class="pt-2">
                  <div
                    v-for="alert in alerts"
                    :key="alert.title"
                    class="alert-item"
                  >
                    <div class="alert-icon" :class="`alert-${alert.level}`">
                      <v-icon :icon="alert.icon" size="18" />
                    </div>
                    <div class="flex-grow-1">
                      <div class="font-weight-medium">{{ alert.title }}</div>
                      <div class="text-caption text-medium-emphasis">{{ alert.description }}</div>
                    </div>
                    <v-btn icon variant="text" size="small">
                      <v-icon icon="mdi-chevron-right" />
                    </v-btn>
                  </div>
                </v-card-text>
              </v-card>

              <v-card class="reveal delay-4" rounded="xl" elevation="0">
                <v-card-title class="d-flex align-center justify-space-between">
                  <span>Pipeline de saisie</span>
                  <v-btn variant="text" size="small">Sync</v-btn>
                </v-card-title>
                <v-card-text class="pt-2">
                  <div v-for="step in pipeline" :key="step.label" class="pipeline-row">
                    <div class="d-flex justify-space-between mb-1">
                      <span class="text-body-2">{{ step.label }}</span>
                      <span class="text-caption text-medium-emphasis">{{ step.count }} éléments</span>
                    </div>
                    <v-progress-linear
                      :model-value="step.percent"
                      :color="step.color"
                      rounded
                      height="9"
                      bg-color="rgba(15,23,42,0.08)"
                    />
                  </div>
                </v-card-text>
              </v-card>
            </v-col>
          </v-row>
        </v-container>
      </div>
    </v-main>
  </v-app>
</template>

<script setup>
import { ref } from 'vue'

const drawer = ref(true)

const navigation = [
  { title: 'Dashboard', subtitle: 'Vue globale', icon: 'mdi-view-dashboard-outline', active: true },
  { title: 'Parcelles', subtitle: 'Cartographie & lots', icon: 'mdi-map-outline', active: false },
  { title: 'Activités', subtitle: 'Saisie terrain', icon: 'mdi-clipboard-text-clock-outline', active: false },
  { title: 'Conformité', subtitle: 'Contrôles & audits', icon: 'mdi-shield-check-outline', active: false },
  { title: 'Rapports', subtitle: 'Exports & analyses', icon: 'mdi-chart-box-outline', active: false },
]

const signals = [
  { label: 'Hydratation', value: 88, color: 'info' },
  { label: 'Nutrition', value: 74, color: 'success' },
  { label: 'Pression ravageurs', value: 39, color: 'warning' },
]

const metrics = [
  {
    title: 'Parcelles actives',
    value: '24',
    trend: '+12% vs mois dernier',
    trendColor: 'success',
    note: '3 nouvelles',
    icon: 'mdi-sprout',
    iconBg: 'linear-gradient(135deg, #22c55e, #15803d)',
  },
  {
    title: 'Interventions aujourd’hui',
    value: '18',
    trend: '+5 planifiées',
    trendColor: 'info',
    note: '11 terminées',
    icon: 'mdi-tractor-variant',
    iconBg: 'linear-gradient(135deg, #0ea5e9, #0369a1)',
  },
  {
    title: 'Documents en attente',
    value: '07',
    trend: '2 urgents',
    trendColor: 'warning',
    note: 'Registre phytosanitaire',
    icon: 'mdi-file-document-outline',
    iconBg: 'linear-gradient(135deg, #f59e0b, #b45309)',
  },
  {
    title: 'Rendement estimé',
    value: '42.8 t',
    trend: '+3.4% projection',
    trendColor: 'success',
    note: 'Campagne Q2',
    icon: 'mdi-chart-areaspline',
    iconBg: 'linear-gradient(135deg, #8b5cf6, #6d28d9)',
  },
]

const operations = [
  {
    id: 1,
    plot: 'Parcelle A1',
    surface: '4.2 ha • Maïs',
    action: 'Irrigation goutte-à-goutte',
    operator: 'M. Diallo',
    time: '08:30',
    status: 'Terminée',
    statusColor: 'success',
  },
  {
    id: 2,
    plot: 'Parcelle B3',
    surface: '2.8 ha • Tomate',
    action: 'Traitement phytosanitaire',
    operator: 'S. Karim',
    time: '10:15',
    status: 'En cours',
    statusColor: 'info',
  },
  {
    id: 3,
    plot: 'Parcelle C2',
    surface: '6.1 ha • Blé',
    action: 'Contrôle humidité sol',
    operator: 'A. Ndao',
    time: '11:40',
    status: 'À valider',
    statusColor: 'warning',
  },
  {
    id: 4,
    plot: 'Parcelle D7',
    surface: '3.5 ha • Oignon',
    action: 'Fertilisation foliaire',
    operator: 'R. Bamba',
    time: '14:00',
    status: 'Planifiée',
    statusColor: 'secondary',
  },
]

const alerts = [
  {
    level: 'warning',
    icon: 'mdi-alert-outline',
    title: 'Dose traitement à confirmer',
    description: 'Parcelle B3 • Saisie incomplète du volume appliqué',
  },
  {
    level: 'error',
    icon: 'mdi-timer-sand-alert',
    title: 'Délai avant récolte non renseigné',
    description: 'Lot T-2026-014 • Action requise avant validation',
  },
  {
    level: 'info',
    icon: 'mdi-calendar-clock-outline',
    title: 'Audit interne programmé',
    description: 'Vendredi 09:00 • Préparer les documents de traçabilité',
  },
]

const pipeline = [
  { label: 'Saisies brouillon', count: 12, percent: 65, color: 'info' },
  { label: 'En validation superviseur', count: 6, percent: 34, color: 'warning' },
  { label: 'Prêtes pour export', count: 18, percent: 90, color: 'success' },
]
</script>

<style>
@import url('https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&display=swap');

:root {
  --ct-bg: #eef3ee;
  --ct-panel: rgba(255, 255, 255, 0.78);
  --ct-border: rgba(15, 23, 42, 0.08);
  --ct-text: #0f172a;
  --ct-muted: #64748b;
}

html,
body,
#app {
  min-height: 100%;
}

body {
  margin: 0;
  font-family: 'Manrope', 'Segoe UI', sans-serif;
  color: var(--ct-text);
  background:
    radial-gradient(circle at 15% 15%, rgba(34, 197, 94, 0.12), transparent 38%),
    radial-gradient(circle at 85% 10%, rgba(14, 165, 233, 0.12), transparent 32%),
    radial-gradient(circle at 70% 80%, rgba(168, 85, 247, 0.08), transparent 38%),
    var(--ct-bg);
}

.app-shell {
  min-height: calc(100vh - 64px);
}

.app-drawer {
  position: relative;
  background: linear-gradient(180deg, #0f172a 0%, #101b34 55%, #12233c 100%);
  color: #fff;
}

.app-drawer::before {
  content: '';
  position: absolute;
  top: 14px;
  bottom: 14px;
  left: 10px;
  width: 3px;
  border-radius: 999px;
  background: linear-gradient(180deg, rgba(34, 197, 94, 0.15), rgba(14, 165, 233, 0.6), rgba(168, 85, 247, 0.2));
  pointer-events: none;
}

.drawer-brand {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 20px 18px 14px;
}

.brand-mark {
  width: 42px;
  height: 42px;
  border-radius: 14px;
  display: grid;
  place-items: center;
  font-weight: 800;
  background: linear-gradient(135deg, #22c55e, #16a34a 45%, #0ea5e9);
  color: #fff;
  box-shadow: 0 10px 30px rgba(14, 165, 233, 0.18);
}

.brand-title {
  font-size: 0.95rem;
  font-weight: 800;
  letter-spacing: 0.01em;
}

.brand-subtitle {
  font-size: 0.75rem;
  color: rgba(255, 255, 255, 0.65);
}

.nav-item {
  position: relative;
  margin-left: 10px;
  color: rgba(255, 255, 255, 0.92);
}

.nav-item::before {
  content: '';
  position: absolute;
  left: -11px;
  top: 10px;
  bottom: 10px;
  width: 3px;
  border-radius: 999px;
  background: transparent;
  transition: background-color 0.2s ease, box-shadow 0.2s ease;
}

.nav-item--active::before {
  background: linear-gradient(180deg, #22c55e, #0ea5e9);
  box-shadow: 0 0 0 4px rgba(14, 165, 233, 0.12);
}

.nav-item--active {
  background: linear-gradient(90deg, rgba(255,255,255,0.08), rgba(255,255,255,0.02));
}

.drawer-card {
  background: linear-gradient(160deg, rgba(255,255,255,0.08), rgba(255,255,255,0.03));
  color: #fff;
  border: 1px solid rgba(255,255,255,0.08);
  backdrop-filter: blur(8px);
}

.app-bar {
  background: rgba(238, 243, 238, 0.72) !important;
  backdrop-filter: blur(14px);
  border-bottom: 1px solid var(--ct-border);
}

.top-search {
  align-items: center;
  min-width: 360px;
  padding: 10px 14px;
  border-radius: 14px;
  background: rgba(255,255,255,0.75);
  border: 1px solid var(--ct-border);
}

.user-chip {
  background: rgba(255,255,255,0.85);
  border: 1px solid var(--ct-border);
  padding-inline: 8px !important;
  gap: 2px;
}

.user-chip__name {
  max-width: 96px;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.hero-card {
  position: relative;
  overflow: hidden;
  color: #fff;
  background:
    linear-gradient(135deg, rgba(12, 74, 110, 0.95), rgba(21, 128, 61, 0.92) 48%, rgba(88, 28, 135, 0.9));
  border: 1px solid rgba(255,255,255,0.08);
}

.hero-overline {
  color: rgba(255,255,255,0.8);
  letter-spacing: 0.12em;
}

.hero-title {
  font-size: clamp(1.5rem, 2.2vw, 2.4rem);
  line-height: 1.1;
  font-weight: 800;
  max-width: 16ch;
}

.hero-subtitle {
  max-width: 58ch;
  color: rgba(255,255,255,0.85);
  font-size: 0.98rem;
}

.hero-orb {
  position: absolute;
  border-radius: 999px;
  filter: blur(8px);
  opacity: 0.35;
  pointer-events: none;
}

.hero-orb-1 {
  width: 180px;
  height: 180px;
  right: -20px;
  top: -35px;
  background: #38bdf8;
}

.hero-orb-2 {
  width: 220px;
  height: 220px;
  right: 60px;
  bottom: -90px;
  background: #22c55e;
}

.v-card {
  background: var(--ct-panel) !important;
  border: 1px solid var(--ct-border);
  backdrop-filter: blur(12px);
  box-shadow: 0 8px 30px rgba(15, 23, 42, 0.04) !important;
}

.health-score {
  display: flex;
  align-items: center;
  gap: 16px;
}

.health-score__ring {
  width: 92px;
  height: 92px;
  border-radius: 50%;
  display: grid;
  place-items: center;
  background:
    radial-gradient(circle at center, #fff 53%, transparent 54%),
    conic-gradient(#16a34a 0 70%, #22c55e 70% 92%, rgba(15,23,42,0.08) 92% 100%);
  box-shadow: inset 0 0 0 1px rgba(15,23,42,0.05);
}

.health-score__value {
  font-weight: 800;
  font-size: 1.35rem;
}

.health-score__meta {
  flex: 1;
}

.signal-row + .signal-row {
  margin-top: 12px;
}

.metric-card {
  height: 100%;
}

.metric-icon {
  width: 42px;
  height: 42px;
  border-radius: 14px;
  display: grid;
  place-items: center;
  box-shadow: 0 10px 20px rgba(2, 6, 23, 0.15);
}

.table-wrap {
  overflow-x: auto;
}

.ops-table th {
  color: var(--ct-muted) !important;
  font-size: 0.75rem !important;
  letter-spacing: 0.06em;
  text-transform: uppercase;
  white-space: nowrap;
}

.ops-table td {
  white-space: nowrap;
}

.ops-table tbody tr:hover {
  background: rgba(34, 197, 94, 0.04);
}

.alert-item {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 10px;
  border-radius: 14px;
  transition: background-color 0.2s ease;
}

.alert-item + .alert-item {
  margin-top: 8px;
}

.alert-item:hover {
  background: rgba(15, 23, 42, 0.03);
}

.alert-icon {
  width: 34px;
  height: 34px;
  border-radius: 10px;
  display: grid;
  place-items: center;
}

.alert-warning {
  background: rgba(245, 158, 11, 0.14);
  color: #b45309;
}

.alert-error {
  background: rgba(239, 68, 68, 0.14);
  color: #b91c1c;
}

.alert-info {
  background: rgba(14, 165, 233, 0.14);
  color: #0369a1;
}

.pipeline-row + .pipeline-row {
  margin-top: 14px;
}

.reveal {
  animation: revealUp 0.55s ease both;
}

.delay-1 { animation-delay: 0.05s; }
.delay-2 { animation-delay: 0.1s; }
.delay-3 { animation-delay: 0.15s; }
.delay-4 { animation-delay: 0.2s; }

@keyframes revealUp {
  from {
    opacity: 0;
    transform: translateY(10px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

@media (prefers-reduced-motion: reduce) {
  .reveal {
    animation: none;
  }
}

@media (max-width: 959px) {
  .health-score {
    align-items: flex-start;
    flex-direction: column;
  }

  .v-app-bar-title {
    font-size: 0.95rem !important;
  }

  .user-chip {
    padding-inline-end: 8px !important;
  }

  .user-chip__name {
    max-width: 62px;
  }
}

@media (max-width: 600px) {
  .v-app-bar-title {
    display: none !important;
  }

  .user-chip {
    min-width: 0;
    padding-inline: 6px !important;
  }

  .user-chip__name {
    display: none;
  }
}
</style>
