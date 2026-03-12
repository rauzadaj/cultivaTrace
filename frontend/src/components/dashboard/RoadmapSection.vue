<template>
  <v-row class="roadmap-section" dense>
    <v-col cols="12" lg="8">
      <v-card rounded="xl" class="roadmap-card">
        <div class="roadmap-card__header">
          <div>
            <p class="roadmap-card__eyebrow">Delivery plan</p>
            <h2>Execution roadmap</h2>
          </div>
          <v-chip color="primary" variant="flat" size="small">{{ filteredMilestones.length }} active milestones</v-chip>
        </div>

        <v-timeline side="end" density="compact" class="roadmap-timeline">
          <v-timeline-item
            v-for="milestone in filteredMilestones"
            :key="milestone.id"
            :dot-color="statusColor[milestone.status]"
            size="small"
          >
            <template #opposite>
              <span class="milestone-quarter">{{ milestone.quarter }}</span>
            </template>

            <v-card rounded="lg" class="milestone-card" elevation="0">
              <div class="milestone-header">
                <strong>{{ milestone.title }}</strong>
                <v-chip size="x-small" :color="statusColor[milestone.status]" variant="tonal">
                  {{ statusLabel[milestone.status] }}
                </v-chip>
              </div>
              <p>{{ milestone.description }}</p>
              <ul>
                <li v-for="deliverable in milestone.deliverables" :key="deliverable">{{ deliverable }}</li>
              </ul>
            </v-card>
          </v-timeline-item>
        </v-timeline>
      </v-card>
    </v-col>

    <v-col cols="12" lg="4">
      <v-card rounded="xl" class="roadmap-card roadmap-card--summary">
        <p class="roadmap-card__eyebrow">Portfolio health</p>
        <h3>Roadmap overview</h3>
        <div class="summary-grid">
          <article v-for="metric in summaryMetrics" :key="metric.label">
            <span>{{ metric.label }}</span>
            <strong>{{ metric.value }}</strong>
          </article>
        </div>
      </v-card>
    </v-col>
  </v-row>
</template>

<script setup lang="ts">
import { computed } from 'vue'

interface RoadmapMilestone {
  id: string
  title: string
  quarter: string
  status: 'planned' | 'in_progress' | 'blocked' | 'completed'
  description: string
  deliverables: string[]
}

const props = defineProps<{
  search: string
}>()

const statusLabel: Record<RoadmapMilestone['status'], string> = {
  planned: 'Planned',
  in_progress: 'In progress',
  blocked: 'Blocked',
  completed: 'Completed',
}

const statusColor: Record<RoadmapMilestone['status'], string> = {
  planned: 'info',
  in_progress: 'warning',
  blocked: 'error',
  completed: 'success',
}

const milestones = computed<RoadmapMilestone[]>(() => [
  {
    id: 'workflow-hardening',
    title: 'Lifecycle workflow hardening',
    quarter: 'Q2 2026',
    status: 'in_progress',
    description: 'Finalize deterministic lifecycle transitions and enforce domain guards end-to-end.',
    deliverables: [
      'Workflow guard conditions for invalid stage skips',
      'Immutable audit event for every transition',
      'Replay-safe transition API idempotency keys',
    ],
  },
  {
    id: 'observability',
    title: 'Telemetry & anomaly detection',
    quarter: 'Q3 2026',
    status: 'planned',
    description: 'Correlate pH/EC/temperature drifts with yield deviations to trigger operational alerts.',
    deliverables: [
      'Streaming ingestion for sensor snapshots',
      'Outlier detection model for crop stress',
      'Dashboard alert panel with severity filters',
    ],
  },
  {
    id: 'compliance',
    title: 'Compliance export engine',
    quarter: 'Q3 2026',
    status: 'planned',
    description: 'Provide signed export packages for regulatory audits and partner integrations.',
    deliverables: [
      'Tamper-evident PDF + JSON export bundle',
      'Scheduled export job pipeline',
      'Partner webhook delivery status tracking',
    ],
  },
  {
    id: 'mobile-offline',
    title: 'Field mobile offline mode',
    quarter: 'Q4 2026',
    status: 'planned',
    description: 'Enable low-latency field journaling when connectivity is unstable.',
    deliverables: [
      'Offline-first quick actions queue',
      'Conflict-free append-only sync merge',
      'Device-level cryptographic signing',
    ],
  },
])

const filteredMilestones = computed(() => {
  if (!props.search) {
    return milestones.value
  }

  const needle = props.search.toLowerCase()

  return milestones.value.filter((milestone) => {
    const payload = [
      milestone.title,
      milestone.quarter,
      milestone.description,
      ...milestone.deliverables,
      statusLabel[milestone.status],
    ]
      .join(' ')
      .toLowerCase()

    return payload.includes(needle)
  })
})

const summaryMetrics = computed(() => {
  const total = milestones.value.length
  const completed = milestones.value.filter((item) => item.status === 'completed').length
  const inProgress = milestones.value.filter((item) => item.status === 'in_progress').length
  const blocked = milestones.value.filter((item) => item.status === 'blocked').length

  return [
    { label: 'Total milestones', value: total },
    { label: 'In progress', value: inProgress },
    { label: 'Completed', value: completed },
    { label: 'Blocked', value: blocked },
  ]
})
</script>

<style scoped>
.roadmap-section {
  gap: 16px;
}

.roadmap-card {
  padding: 20px;
  background: white;
  border: 1px solid #d7e1e9;
}

.roadmap-card__header {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 12px;
  margin-bottom: 8px;
}

.roadmap-card__eyebrow {
  margin: 0;
  font-size: 0.72rem;
  letter-spacing: 0.14em;
  text-transform: uppercase;
  color: #5f7383;
}

.roadmap-card h2,
.roadmap-card h3 {
  margin: 6px 0 0;
  font-size: 1.1rem;
  color: #23313a;
}

.roadmap-timeline {
  margin-top: 10px;
}

.milestone-quarter {
  color: #5f7383;
  font-size: 0.78rem;
  font-weight: 600;
}

.milestone-card {
  border: 1px solid #e1e9ef;
  padding: 12px;
  background: #f8fbfd;
}

.milestone-header {
  display: flex;
  justify-content: space-between;
  gap: 10px;
}

.milestone-card p {
  margin: 8px 0;
  color: #3e4d57;
}

.milestone-card ul {
  margin: 0;
  padding-left: 18px;
  color: #41505a;
}

.milestone-card li + li {
  margin-top: 6px;
}

.roadmap-card--summary {
  display: grid;
  gap: 16px;
}

.summary-grid {
  display: grid;
  gap: 10px;
}

.summary-grid article {
  border: 1px solid #dce7ef;
  border-radius: 12px;
  padding: 12px;
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.summary-grid span {
  color: #587080;
}

.summary-grid strong {
  color: #23313a;
  font-size: 1rem;
}
</style>
