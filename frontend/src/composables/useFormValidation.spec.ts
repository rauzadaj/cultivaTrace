import { reactive } from 'vue'
import { describe, expect, it } from 'vitest'
import { useFormValidation, validators } from './useFormValidation'

describe('useFormValidation', () => {
  it('validates fields and exposes form errors', () => {
    const values = reactive({
      email: '',
      password: '',
    })

    const validation = useFormValidation(values, {
      email: [validators.required('Email requis.'), validators.email('Email invalide.')],
      password: [validators.required('Mot de passe requis.'), validators.minLength(8, 'Minimum 8 caractères.')],
    })

    expect(validation.validateAll()).toBe(false)
    expect(validation.fieldErrors.email).toBe('Email requis.')
    expect(validation.fieldErrors.password).toBe('Mot de passe requis.')

    values.email = 'demo@cultivatrace.local'
    values.password = 'secret123'

    expect(validation.validateAll()).toBe(true)
    expect(validation.fieldErrors.email).toBeUndefined()
    expect(validation.fieldErrors.password).toBeUndefined()
  })

  it('maps API violations to field errors', () => {
    const values = reactive({
      licenseNumber: '',
    })

    const validation = useFormValidation(values, {
      licenseNumber: [validators.required('Licence requise.')],
    })

    validation.applyApiError(
      {
        response: {
          data: {
            violations: [
              {
                propertyPath: 'licenseNumber',
                message: 'Ce numéro existe déjà.',
              },
            ],
          },
        },
      },
      'Erreur générique.',
    )

    expect(validation.fieldErrors.licenseNumber).toBe('Ce numéro existe déjà.')
    expect(validation.formError.value).toBe('')
  })
})
