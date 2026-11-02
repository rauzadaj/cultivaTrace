import { describe, expect, it } from 'vitest'
import { isSensorOffline } from './sensorFreshness'

describe('isSensorOffline', () => {
  const now = Date.parse('2026-11-02T15:00:00.000Z')

  it('uses a fresh live reading when the REST snapshot is stale', () => {
    expect(isSensorOffline('2026-11-02T14:50:00.000Z', '2026-11-02T14:59:30.000Z', now)).toBe(false)
  })

  it('keeps a sensor offline when both known signals are stale', () => {
    expect(isSensorOffline('2026-11-02T14:50:00.000Z', '2026-11-02T14:54:00.000Z', now)).toBe(true)
  })

  it('falls back to the REST snapshot when no live reading exists', () => {
    expect(isSensorOffline('2026-11-02T14:59:30.000Z', undefined, now)).toBe(false)
  })

  it('treats missing or invalid timestamps as offline', () => {
    expect(isSensorOffline(undefined, undefined, now)).toBe(true)
    expect(isSensorOffline('invalid', undefined, now)).toBe(true)
  })
})
