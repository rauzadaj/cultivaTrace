export interface HydraCollection<T> {
  'hydra:member': T[]
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
