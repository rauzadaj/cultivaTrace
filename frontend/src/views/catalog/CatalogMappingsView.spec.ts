import { beforeEach, describe, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import type { GeneticCatalogMapping } from '@/types/api'
import CatalogMappingsView from './CatalogMappingsView.vue'

const { listSpy, approveSpy, rejectSpy, notifySpy } = vi.hoisted(() => ({
  listSpy: vi.fn(),
  approveSpy: vi.fn(),
  rejectSpy: vi.fn(),
  notifySpy: vi.fn(),
}))

vi.mock('@/services/api', () => ({
  catalogMappingApi: {
    list: listSpy,
    approve: approveSpy,
    reject: rejectSpy,
  },
}))

vi.mock('quasar', async (importOriginal) => ({
  ...(await importOriginal<typeof import('quasar')>()),
  useQuasar: () => ({ notify: notifySpy }),
}))

function mapping(id: string, status: GeneticCatalogMapping['status'], code: string): GeneticCatalogMapping {
  return {
    id,
    externalCatalogEntry: { id: `ext-${id}`, sourceProvider: 'test', externalCode: code, name: `External ${code}` },
    genetic: { id: `gen-${id}`, code, name: `Internal ${code}` },
    status,
    notes: null,
    reviewedAt: null,
    createdAt: '2026-05-01T00:00:00+00:00',
    updatedAt: '2026-05-01T00:00:00+00:00',
  }
}

const stubs = {
  'q-btn': { template: '<button :data-test="$attrs[\'data-test\']" @click="$emit(\'click\')"><slot />{{ label }}</button>', props: ['label'] },
  'q-card': { template: '<div><slot /></div>' },
  'q-card-section': { template: '<div><slot /></div>' },
  'q-tabs': { template: '<div><slot /></div>' },
  'q-tab': { template: '<button class="q-tab" @click="$emit(\'click\')">{{ label }}</button>', props: ['name', 'label'] },
  'q-badge': { template: '<span>{{ label }}</span>', props: ['label'] },
  'q-icon': { template: '<i />' },
  'q-spinner-dots': { template: '<div />' },
}

function buildWrapper() {
  return mount(CatalogMappingsView, { global: { stubs } })
}

describe('CatalogMappingsView', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    listSpy.mockResolvedValue({
      data: {
        'hydra:member': [
          mapping('1', 'pending', 'ALPHA'),
          mapping('2', 'pending', 'BETA'),
          mapping('3', 'linked', 'GAMMA'),
        ],
      },
    })
  })

  it('loads mappings and shows the pending ones by default', async () => {
    const wrapper = buildWrapper()
    await flushPromises()

    expect(listSpy).toHaveBeenCalledTimes(1)
    const rows = wrapper.findAll('[data-test="mapping-row"]')
    expect(rows).toHaveLength(2)
    expect(wrapper.text()).toContain('External ALPHA')
    expect(wrapper.text()).toContain('Internal BETA')
    // The linked mapping must not appear under the default pending tab.
    expect(wrapper.text()).not.toContain('External GAMMA')
  })

  it('approves a pending mapping and removes it from the pending list', async () => {
    approveSpy.mockResolvedValue({ data: mapping('1', 'linked', 'ALPHA') })
    const wrapper = buildWrapper()
    await flushPromises()

    await wrapper.findAll('[data-test="approve-btn"]')[0].trigger('click')
    await flushPromises()

    expect(approveSpy).toHaveBeenCalledWith('1')
    expect(notifySpy).toHaveBeenCalledWith(expect.objectContaining({ type: 'positive' }))
    // ALPHA is now linked, so only BETA remains pending.
    expect(wrapper.findAll('[data-test="mapping-row"]')).toHaveLength(1)
    expect(wrapper.text()).toContain('External BETA')
  })

  it('rejects a pending mapping via the reject action', async () => {
    rejectSpy.mockResolvedValue({ data: mapping('1', 'rejected', 'ALPHA') })
    const wrapper = buildWrapper()
    await flushPromises()

    await wrapper.findAll('[data-test="reject-btn"]')[0].trigger('click')
    await flushPromises()

    expect(rejectSpy).toHaveBeenCalledWith('1')
    expect(wrapper.findAll('[data-test="mapping-row"]')).toHaveLength(1)
  })

  it('surfaces an error notification when loading fails', async () => {
    listSpy.mockRejectedValueOnce(new Error('boom'))
    const wrapper = buildWrapper()
    await flushPromises()

    expect(notifySpy).toHaveBeenCalledWith(expect.objectContaining({ type: 'negative', message: 'boom' }))
    expect(wrapper.findAll('[data-test="mapping-row"]')).toHaveLength(0)
  })
})
