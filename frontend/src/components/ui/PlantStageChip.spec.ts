import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import type { PlantStage } from '@/types/api'
import PlantStageChip from './PlantStageChip.vue'

describe('PlantStageChip', () => {
  it.each([
    ['germination', 'Germination'],
    ['vegetation', 'Vegetation'],
    ['flowering', 'Flowering'],
    ['harvest', 'Harvest'],
    ['archived', 'Archived'],
  ] as [PlantStage, string][])('renders the %s stage label', (stage, label) => {
    const wrapper = mount(PlantStageChip, { props: { stage } })

    expect(wrapper.text()).toBe(label)
    expect(wrapper.classes()).toContain(`stage-chip--${stage}`)
  })
})
