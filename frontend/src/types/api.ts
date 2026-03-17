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
  | 'ROLE_ADMIN'
  | 'ROLE_MANAGER'
  | 'ROLE_OPERATOR'
  | 'ROLE_OBSERVER'

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
  id: string
  name: string
  country: string
  plan: SubscriptionPlan
  licenseStatus: LicenseStatus
  licenseExpiresAt?: string // ISO 8601
  createdAt: string
}

export interface Farm {
  id: string
  name: string
  address?: string
  surfaceM2?: number
  organization: Pick<Organization, 'id' | 'name'>
}

export interface Room {
  id: string
  name: string
  type: RoomType
  description?: string
  capacityMax: number
  farm: Pick<Farm, 'id' | 'name'>
  // computed (non stocké en DB — calculé par le backend)
  activePlantCount?: number
  occupancyRate?: number
}

export interface Strain {
  id: string
  name: string
  genetics: 'indica' | 'sativa' | 'hybrid'
  floweringDays?: number
  notes?: string
}

export interface Plant {
  id: string
  stage: PlantStage
  status: PlantStatus
  room: Pick<Room, 'id' | 'name'>
  strain?: Pick<Strain, 'id' | 'name' | 'genetics'>
  rfidTag?: string
  germinatedAt: string   // ISO 8601 date
  createdAt: string      // ISO 8601 datetime
  createdBy: Pick<User, 'id' | 'email'>
  ageInDays?: number     // computed
}

export interface PlantEvent {
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
  user: Pick<User, 'id' | 'email'>
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
  id: string
  grossWeightG: string   // decimal en string (précision Doctrine)
  netWeightG: string
  harvestedAt: string
  notes?: string
  harvestedBy: Pick<User, 'id' | 'email'>
}

export interface InputRecord {
  id: string
  inputType: 'nutrient' | 'pesticide' | 'water' | 'energy'
  productName: string
  quantity: string
  unit: string
  appliedAt: string
  appliedBy: Pick<User, 'id' | 'email'>
}

export interface Sensor {
  id: string
  type: SensorType
  deviceId: string
  protocol: 'mqtt' | 'rest'
  lastSeen?: string
  thresholds?: {
    min: number
    max: number
    unit: string
  }
  room: Pick<Room, 'id' | 'name'>
}

export interface SensorReading {
  sensorId: string
  value: number
  recordedAt: string
}

export interface User {
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
  'hydra:view'?: {
    '@id': string
    '@type': 'hydra:PartialCollectionView'
    'hydra:first'?: string
    'hydra:last'?: string
    'hydra:next'?: string
    'hydra:previous'?: string
  }
}

// ── Auth ──────────────────────────────────────────────────────────────────

export interface LoginCredentials {
  email: string
  password: string
}

export interface JwtResponse {
  token: string
  refresh_token?: string
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
