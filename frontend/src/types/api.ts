/**
 * apps/frontend/src/types/api.ts
 *
 * SOURCE UNIQUE DE VÉRITÉ pour tous les types TypeScript.
 *
 * ⚠️ NE PAS définir de types inline dans les composants ou stores.
 * ⚠️ NE PAS dupliquer un type déjà défini ici.
 * Si un type manque → l'ajouter ICI et signaler la modification.
 *
 * Ces types correspondent exactement aux entités Doctrine du backend.
 * En cas de divergence → le backend fait foi.
 */

// ── Enums ────────────────────────────────────────────────────────────────

export type PlantStage =
  | 'germination'
  | 'vegetation'
  | 'flowering'
  | 'harvest'
  | 'archived'

export type PlantStatus =
  | 'active'
  | 'harvested'
  | 'destroyed'
  | 'archived'

export type LicenseStatus =
  | 'pending'
  | 'active'
  | 'suspended'
  | 'expired'
  | 'rejected'

export type SubscriptionPlan =
  | 'starter'
  | 'pro'
  | 'business'
  | 'enterprise'

export type UserRole =
  | 'ROLE_SUPER_ADMIN'
  | 'ROLE_ORG_ADMIN'
  | 'ROLE_ORG_USER'
  | 'ROLE_API'

export type SensorType =
  | 'temperature'
  | 'co2'
  | 'humidity'
  | 'ph'
  | 'ec'

export type RoomType =
  | 'veg'
  | 'flower'
  | 'drying'
  | 'clone'
  | 'mixed'

// ── Entités principales ───────────────────────────────────────────────────

export interface Organization {
  '@id'?: string
  id: string
  name: string
  country: string
  contactEmail?: string | null
  config?: Record<string, unknown>
  plan: SubscriptionPlan
  licenseStatus: LicenseStatus
  licenseExpiresAt?: string // ISO 8601
  createdAt: string
}

export interface Farm {
  '@id'?: string
  id: string
  name: string
  address?: string
  surfaceM2?: number
  organization: string | Pick<Organization, 'id' | 'name'>
}

export interface Room {
  '@id'?: string
  id: string
  name: string
  type: RoomType
  description?: string
  capacityMax: number
  farm: string | Pick<Farm, 'id' | 'name'>
  // computed (non stocké en DB — calculé par le backend)
  activePlantCount?: number
  occupancyRate?: number
}

export interface Strain {
  '@id'?: string
  id: string
  name: string
  genetics: 'indica' | 'sativa' | 'hybrid'
  cannabisType?: 'hemp' | 'marijuana'
  floweringDays?: number
  notes?: string
}

export interface Plant {
  '@id'?: string
  id: string
  stage: PlantStage
  status: PlantStatus
  room: string | Pick<Room, 'id' | 'name'>
  strain?: string | Pick<Strain, 'id' | 'name' | 'genetics'>
  rfidTag?: string
  germinatedAt: string   // ISO 8601 date
  createdAt: string      // ISO 8601 datetime
  createdBy?: string | Pick<User, 'id' | 'email'>
  ageInDays?: number     // computed
}

export interface PlantEvent {
  '@id'?: string
  id: string
  eventType:
    | 'germination'
    | 'stage_change'
    | 'note'
    | 'photo'
    | 'input_record'
    | 'harvest'
    | 'destruction_intent'
    | 'destruction_confirmed'
    | 'room_move'
    | 'legacy_activity'
  notes?: string
  photoUrls?: string[]
  occurredAt: string
  user: string | Pick<User, 'id' | 'email'>
  plant?: string | Pick<Plant, 'id'>
  hashPrevious?: string
  hashSelf?: string
  payload?: PlantEventPayload
}

// Payloads typés selon eventType
export type PlantEventPayload =
  | { from: PlantStage; to: PlantStage }                         // stage_change
  | { fromRoomId: string; toRoomId: string }                     // room_move
  | { product: string; quantity: number; unit: string }          // input_record
  | { reason: string; plannedDate: string }                      // destruction_intent
  | { grossWeightG: number; nonCannabisRatio: number }           // destruction_confirmed
  | Record<string, unknown>                                      // autres

export interface HarvestRecord {
  '@id'?: string
  id: string
  grossWeightG: string   // decimal en string (précision Doctrine)
  netWeightG: string
  harvestedAt: string
  notes?: string
  harvestedBy?: string | Pick<User, 'id' | 'email'>
}

export interface InputRecord {
  '@id'?: string
  id: string
  inputType: 'nutrient' | 'pesticide' | 'water' | 'energy'
  productName: string
  quantity: string
  unit: string
  appliedAt: string
  appliedBy?: string | Pick<User, 'id' | 'email'>
}

export interface Sensor {
  '@id'?: string
  id: string
  type: SensorType
  deviceId: string
  protocol: 'mqtt' | 'rest' | 'simulated'
  lastSeen?: string
  thresholds?: {
    min: number
    max: number
    unit: string
  }
  room: string | Pick<Room, 'id' | 'name'>
}

export interface SensorReading {
  sensorId: string
  value: number
  recordedAt: string
}

export interface SensorHistoryPoint {
  bucket: string
  avg_value: number
  min_value: number
  max_value: number
}

export interface SensorHistoryResponse {
  sensorId: string
  type: SensorType
  period: '7d' | '30d' | '90d' | '365d'
  data: SensorHistoryPoint[]
  count: number
}

export interface SensorVpdSnapshot {
  vpd: number
  status: string
  message: string
  optimal_min: number
  optimal_max: number
  stage: string
}

export interface SensorLiveReading {
  value: number
  unit: string
  recordedAt: string
  status: 'normal' | 'warning' | 'critical'
  vpd?: SensorVpdSnapshot | null
}

export interface DashboardAlert {
  id: string
  title: string
  message: string
  severity: 'warning' | 'critical' | 'default'
  context?: string
}

export interface DashboardSpotlightPlant {
  id: string
  name: string
  strain: string
  room: string
  stage: PlantStage
  status: PlantStatus
  ageInDays: number
}

export interface DashboardRecentEvent {
  id: string
  eventType: PlantEvent['eventType']
  notes?: string | null
  occurredAt: string
  payload?: PlantEventPayload | null
}

export interface DashboardOverviewResponse {
  organization: {
    name: string
    plan: SubscriptionPlan
    licenseStatus: LicenseStatus
  }
  plants: {
    byStage: Partial<Record<PlantStage, number>>
    total: number
    inFlowering: number
  }
  harvests: {
    last30Days: number
    totalGrams: number
  }
  alerts: {
    sensorsInAlert: number
    saturatedRooms: number
    items: DashboardAlert[]
  }
  rooms: {
    total: number
  }
  overview: {
    spotlightPlants: DashboardSpotlightPlant[]
    recentEvents: DashboardRecentEvent[]
  }
  limits: Record<string, unknown>
  generatedAt: string
}

export interface User {
  '@id'?: string
  id: string
  email: string
  roles: UserRole[]
  mfaEnabled: boolean
  organization?: Pick<Organization, 'id' | 'name' | 'plan' | 'licenseStatus'>
}

// ── Réponses API Platform (Hydra) ─────────────────────────────────────────

export interface HydraCollection<T> {
  '@context': string
  '@id': string
  '@type': 'hydra:Collection'
  'hydra:totalItems': number
  'hydra:member': T[]
  totalItems?: number
  member?: T[]
  'hydra:view'?: {
    '@id': string
    '@type': 'hydra:PartialCollectionView'
    'hydra:first'?: string
    'hydra:last'?: string
    'hydra:next'?: string
    'hydra:previous'?: string
  }
  view?: {
    first?: string
    last?: string
    next?: string
    previous?: string
  }
}

// ── Auth ──────────────────────────────────────────────────────────────────

export interface LoginCredentials {
  email: string
  password: string
}

export type RegistrationPlan = Extract<SubscriptionPlan, 'starter' | 'pro' | 'business'>

export interface OrganizationRegistrationPayload {
  organizationName: string
  email: string
  password: string
  country: string
  plan: RegistrationPlan
}

export interface JwtResponse {
  token: string
  refreshToken: string
  expiresIn: number
}

export type AuthTokenResponse = JwtResponse

export interface RegistrationResponse extends JwtResponse {
  organization: Pick<Organization, 'id' | 'name' | 'plan' | 'licenseStatus'>
  selectedPlan: RegistrationPlan
  nextPath: string
}

export interface OrganizationMember {
  id: string
  email: string
  roles: UserRole[]
  accountStatus: 'pending_verification' | 'active' | 'suspended'
  emailVerifiedAt?: string | null
}

export interface OrganizationInvitation {
  id: string
  email: string
  roles: UserRole[]
  createdAt: string
  expiresAt: string
}

export interface OrganizationSettingsResponse {
  id: string
  name: string
  country: string
  contactEmail?: string | null
  config: Record<string, unknown>
  plan: SubscriptionPlan
  licenseStatus: LicenseStatus
  licenseExpiresAt?: string | null
  stripeCustomerId?: string | null
}

export interface JwtPayload {
  username?: string
  roles?: UserRole[]
  exp?: number
  iat?: number
}

// ── Design System / UI ───────────────────────────────────────────────────

export type CBtnVariant =
  | 'primary'
  | 'secondary'
  | 'danger'
  | 'ghost'

export type AlertSeverity =
  | 'warning'
  | 'critical'
  | 'healthy'

export interface AlertItem {
  id: string
  title: string
  message: string
  severity: AlertSeverity
  context?: string
}

export interface DashboardStatChip {
  id: string
  label: string
  value: string
  tone: 'default' | 'positive' | 'warning' | 'critical'
}

export interface PlantCardSummary {
  id: string
  iri?: string
  name: string
  strain: string
  room: string
  stage: PlantStage
  ageInDays: number
  batchCode?: string
  status?: PlantStatus
}

export interface SensorMetricCard {
  id: string
  label: string
  value: string
  unit: string
  status: 'ok' | 'warning' | 'critical'
  detail: string
}

// ── Utilitaires ───────────────────────────────────────────────────────────

export type ApiError = {
  '@context': string
  '@type': 'hydra:Error'
  'hydra:title': string
  'hydra:description': string
  violations?: Array<{
    propertyPath: string
    message: string
  }>
}

export const PLANT_STAGE_LABELS: Record<PlantStage, string> = {
  germination: 'Germination',
  vegetation: 'Végétation',
  flowering: 'Floraison',
  harvest: 'Récolte',
  archived: 'Archivé',
}

export const PLANT_STAGE_COLORS: Record<PlantStage, string> = {
  germination: '#81C784', // vert clair
  vegetation:  '#4CAF50', // vert
  flowering:   '#FF9800', // orange
  harvest:     '#F44336', // rouge
  archived:    '#9E9E9E', // gris
}
