import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import type { AlertSeverity } from '@/types/api'
import AlertBadge from './AlertBadge.vue'

const stubs = {
  'q-icon': { template: '<i :data-name="name" />', props: ['name'] },
}

describe('AlertBadge', () => {
  it.each([
    ['critical', 'mdi-alert-circle'],
    ['healthy', 'mdi-check-circle'],
    ['warning', 'mdi-alert'],
  ] as [AlertSeverity, string][])('maps %s severity to its icon and modifier class', (severity, icon) => {
    const wrapper = mount(AlertBadge, {
      props: { severity },
      slots: { default: 'Sensor offline' },
      global: { stubs },
    })

    expect(wrapper.classes()).toContain(`alert-badge--${severity}`)
    expect(wrapper.find('i').attributes('data-name')).toBe(icon)
    expect(wrapper.text()).toContain('Sensor offline')
  })
})
