export interface HydraCollection<T> {
  'hydra:member'?: T[]
  member?: T[]
  totalItems?: number
  'hydra:totalItems'?: number
  view?: {
    next?: string
  }
  'hydra:view'?: {
    'hydra:next'?: string
  }
}

export interface GeneticDto {
  '@id': string
  id: string
  code: string
  name: string
  vendor: string | null
  metadata: Record<string, unknown>
}

export interface CropDto {
  '@id': string
  id: string
  batchCode: string
  displayName: string
  currentStage: 'seedling' | 'veg' | 'flower' | 'harvest'
  seededAt: string
  harvestedAt: string | null
  finalYieldGrams: number | null
  genetic: GeneticDto
}

export interface PhLevelDto {
  value: number | null
}

export interface NutrientConcentrationDto {
  ppm: number | null
  unit: string
}

export interface JournalEntryDto {
  '@id': string
  id: string
  type: 'irrigation' | 'fertilization' | 'environment_check' | 'stage_transition' | 'observation'
  occurredAt: string
  notes: string | null
  metadata: Record<string, unknown>
  phLevel: PhLevelDto | null
  nutrientConcentration: NutrientConcentrationDto | null
  crop: CropDto
}

export interface GeneticCycleAverageDto {
  geneticId: string
  geneticCode: string
  geneticName: string
  completedCycles: number
  averageCycleDays: number
}

export interface OperationalServiceDto {
  '@id': string
  id: string
  name: string
  category: string
  description: string
  icon: string
  tone: 'primary' | 'warning' | 'success'
  statusLabel: string
  position: number
  updatedAt: string
}

export interface CropWritePayload {
  batchCode: string
  displayName: string
  seededAt: string
  genetic: string
}

export interface GeneticWritePayload {
  code: string
  name: string
  vendor: string | null
  metadata: Record<string, unknown>
}

export interface OperationalServiceWritePayload {
  name: string
  category: string
  description: string
  icon: string
  tone: 'primary' | 'warning' | 'success'
  statusLabel: string
  position: number
}

export interface JournalEntryWritePayload {
  crop: string
  type: JournalEntryDto['type']
  occurredAt: string
  notes: string | null
  metadata: Record<string, unknown>
}

export interface AnalyticsResponse {
  data: GeneticCycleAverageDto[]
}

export interface AuthTokenResponse {
  token: string
}

export interface RegistrationResponse {
  id: number
  email: string
}
