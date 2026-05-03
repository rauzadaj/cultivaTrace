import { computed, reactive, ref } from 'vue'
import type { AxiosError } from 'axios'

type Validator<TValues, TField extends keyof TValues> = (value: TValues[TField], values: TValues) => string | null
type ValidationSchema<TValues extends Record<string, unknown>> = {
  [K in keyof TValues]?: Array<Validator<TValues, K>>
}

type ApiErrorPayload = {
  detail?: string
  error?: string
  message?: string
  violations?: Array<{ propertyPath?: string; message?: string }>
}

export function useFormValidation<TValues extends Record<string, unknown>>(
  values: TValues,
  schema: ValidationSchema<TValues>,
) {
  const fieldErrors = reactive<Record<string, string>>({})
  const formError = ref('')

  function validateField<TKey extends keyof TValues>(field: TKey): boolean {
    const validators = schema[field] ?? []

    for (const validator of validators) {
      const message = validator(values[field], values)
      if (message) {
        fieldErrors[String(field)] = message
        return false
      }
    }

    delete fieldErrors[String(field)]
    return true
  }

  function validateAll(): boolean {
    let valid = true

    for (const field of Object.keys(schema) as Array<keyof TValues>) {
      valid = validateField(field) && valid
    }

    return valid
  }

  function clearFieldError<TKey extends keyof TValues>(field: TKey): void {
    delete fieldErrors[String(field)]
  }

  function clearFormError(): void {
    formError.value = ''
  }

  function clearAllErrors(): void {
    for (const key of Object.keys(fieldErrors)) {
      delete fieldErrors[key]
    }

    clearFormError()
  }

  function setFormError(message: string): void {
    formError.value = message
  }

  function applyApiError(error: unknown, fallback: string): void {
    const { field, message } = extractApiError(error, fallback)

    if (field && field in values) {
      fieldErrors[field] = message
      formError.value = ''
      return
    }

    formError.value = message
  }

  return {
    fieldErrors,
    formError,
    hasErrors: computed(() => Object.keys(fieldErrors).length > 0 || formError.value !== ''),
    validateField,
    validateAll,
    clearFieldError,
    clearFormError,
    clearAllErrors,
    setFormError,
    applyApiError,
  }
}

function extractApiError(error: unknown, fallback: string): { field: string | null; message: string } {
  const axiosError = error as AxiosError<ApiErrorPayload> | undefined
  const payload = axiosError?.response?.data
  const violation = payload?.violations?.[0]
  const message = violation?.message ?? payload?.error ?? payload?.detail ?? payload?.message ?? fallback

  return {
    field: violation?.propertyPath ?? null,
    message,
  }
}

export const validators = {
  required:
    (message = 'Champ obligatoire') =>
    (value: unknown): string | null => {
      if (typeof value === 'string') {
        return value.trim() ? null : message
      }

      return value === null || value === undefined || value === '' ? message : null
    },

  email:
    (message = 'Email invalide') =>
    (value: unknown): string | null => {
      if (typeof value !== 'string' || value.trim() === '') {
        return message
      }

      return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value.trim()) ? null : message
    },

  minLength:
    (length: number, message?: string) =>
    (value: unknown): string | null => {
      if (typeof value !== 'string') {
        return message ?? `Minimum ${length} caractères`
      }

      return value.trim().length >= length ? null : (message ?? `Minimum ${length} caractères`)
    },

  isoDate:
    (message = 'Date invalide') =>
    (value: unknown): string | null => {
      if (typeof value !== 'string' || !/^\d{4}-\d{2}-\d{2}$/.test(value)) {
        return message
      }

      const parsed = new Date(`${value}T00:00:00Z`)
      if (Number.isNaN(parsed.getTime())) {
        return message
      }

      const [year, month, day] = value.split('-').map(Number) as [number, number, number]
      return parsed.getUTCFullYear() === year &&
        parsed.getUTCMonth() + 1 === month &&
        parsed.getUTCDate() === day
        ? null
        : message
    },

  positiveInteger:
    (message = 'Valeur invalide') =>
    (value: unknown): string | null => {
      if (typeof value !== 'number' || !Number.isInteger(value) || value < 1) {
        return message
      }

      return null
    },
}
