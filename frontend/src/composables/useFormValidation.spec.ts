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
      email: [validators.required('Email required.'), validators.email('Invalid email.')],
      password: [validators.required('Password required.'), validators.minLength(8, 'Minimum 8 characters.')],
    })

    expect(validation.validateAll()).toBe(false)
    expect(validation.fieldErrors.email).toBe('Email required.')
    expect(validation.fieldErrors.password).toBe('Password required.')

    values.email = 'test@example.local'
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
      licenseNumber: [validators.required('License required.')],
    })

    validation.applyApiError(
      {
        response: {
          data: {
            violations: [
              {
                propertyPath: 'licenseNumber',
                message: 'This number already exists.',
              },
            ],
          },
        },
      },
      'Generic error.',
    )

    expect(validation.fieldErrors.licenseNumber).toBe('This number already exists.')
    expect(validation.formError.value).toBe('')
  })
})
