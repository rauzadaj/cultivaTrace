import { expect, test } from '@playwright/test'
import { mkdirSync } from 'node:fs'
import { join } from 'node:path'

test('journal page renders activity and filters responsively', async ({ page }) => {
  const startOfDay = new Date()
  startOfDay.setHours(0, 0, 0, 0)
  const day = new Date(startOfDay.getTime() - startOfDay.getTimezoneOffset() * 60_000).toISOString().slice(0, 10)
  const occurredAt = new Date(startOfDay.getTime() + 10 * 60 * 60 * 1000 + 30 * 60 * 1000).toISOString()
  const nextDayBoundary = new Date(startOfDay)
  nextDayBoundary.setDate(nextDayBoundary.getDate() + 1)
  const boundaryEventAt = nextDayBoundary.toISOString()
  const plants = [{
    id: 'journal-plant-1', '@id': '/api/plants/journal-plant-1', stage: 'germination', status: 'active',
    room: '/api/rooms/journal-room-1', strain: '/api/strains/journal-strain-1', rfidTag: 'TEST-PLANT-001',
    germinatedAt: `${day}T00:00:00+00:00`, createdAt: occurredAt, ageInDays: 0,
  }]
  const events = [
    {
      id: 'journal-event-1', eventType: 'germination', notes: 'Germination consignée pour TEST-PLANT-001.', occurredAt,
      user: { id: 'test-user', email: 'operateur@example.test' }, plant: { id: 'journal-plant-1' },
      hashSelf: '0123456789abcdef0123456789abcdef', payload: { to: 'germination' },
    },
    {
      id: 'journal-event-move', eventType: 'room_move', notes: 'Déplacement consigné.', occurredAt,
      user: { id: 'test-user', email: 'operateur@example.test' }, plant: { id: 'journal-plant-1' },
      payload: { fromRoomId: 'journal-room-1', toRoomId: 'journal-room-2' },
    },
    {
      id: 'journal-event-boundary', eventType: 'note', notes: 'BOUNDARY-EVENT-SHOULD-NOT-APPEAR', occurredAt: boundaryEventAt,
      user: { id: 'test-user', email: 'operateur@example.test' }, plant: { id: 'journal-plant-1' },
      payload: {},
    },
  ]
  const collection = (members: unknown[]) => ({
    '@context': '/api/contexts/Collection', '@id': '/api/collection', '@type': 'hydra:Collection',
    'hydra:totalItems': members.length, 'hydra:member': members,
  })

  let usedStrictUpperBound = false
  await page.route('**/api/**', async (route) => {
    const request = route.request()
    const path = new URL(request.url()).pathname
    if (request.method() === 'POST' && path === '/api/auth/login') {
      await route.fulfill({ json: { token: 'test.jwt.token', refreshToken: 'test.refresh.token' } })
    } else if (request.method() === 'GET' && path === '/api/me') {
      await route.fulfill({ json: {
        id: 'test-user', email: 'operateur@example.test', roles: ['ROLE_ORG_ADMIN'], mfaEnabled: false,
        organization: { id: 'test-org', name: 'CultivaTrace Test', plan: 'pro', licenseStatus: 'active' },
      } })
    } else if (request.method() === 'GET' && path === '/api/plant_events') {
      const params = new URL(request.url()).searchParams
      const lowerBound = Date.parse(params.get('occurredAt[after]') ?? '')
      const strictUpperBound = params.get('occurredAt[strictly_before]')
      const inclusiveUpperBound = params.get('occurredAt[before]')
      usedStrictUpperBound = Boolean(strictUpperBound) && !inclusiveUpperBound
      const upperBound = Date.parse(strictUpperBound ?? inclusiveUpperBound ?? '')
      const members = events.filter((event) => {
        const timestamp = Date.parse(event.occurredAt)
        return timestamp >= lowerBound && (strictUpperBound ? timestamp < upperBound : timestamp <= upperBound)
      })
      await route.fulfill({ json: collection(members) })
    } else if (request.method() === 'GET' && path === '/api/plants') {
      await route.fulfill({ json: collection(plants) })
    } else if (request.method() === 'GET' && path === '/api/rooms') {
      await route.fulfill({ json: collection([
        { id: 'journal-room-1', '@id': '/api/rooms/journal-room-1', name: 'Salle test', type: 'clone', capacityMax: 10, farm: '/api/farms/test' },
        { id: 'journal-room-2', '@id': '/api/rooms/journal-room-2', name: 'Salle floraison', type: 'flower', capacityMax: 10, farm: '/api/farms/test' },
      ]) })
    } else if (request.method() === 'GET' && path === '/api/strains') {
      await route.fulfill({ json: collection([{ id: 'journal-strain-1', '@id': '/api/strains/journal-strain-1', name: 'Variété test', genetics: 'hybrid' }]) })
    } else if (request.method() === 'GET' && path === '/api/alerts') {
      await route.fulfill({ json: collection([]) })
    } else {
      await route.fulfill({ status: 404, json: { message: `Unhandled journal test API request: ${request.method()} ${path}` } })
    }
  })

  await page.goto('/journal')
  await expect(page).toHaveURL(/\/auth/)
  await page.getByLabel('Email').fill('operateur@example.test')
  await page.locator('input[type="password"]').fill('TEST_ONLY_DEMO_PASSWORD')
  await page.getByRole('button', { name: /Se connecter|Sign in/i }).click()
  await expect(page).toHaveURL(/\/journal/)
  await expect(page.getByRole('heading', { name: 'Journal de Culture & Activités Agronomiques' })).toBeVisible()
  await expect(page.getByText('TEST-PLANT-001').first()).toBeVisible()
  await expect.poll(() => usedStrictUpperBound).toBe(true)
  await expect(page.getByText('BOUNDARY-EVENT-SHOULD-NOT-APPEAR')).toHaveCount(0)
  await expect(page.getByText(/Salle précédente : Salle test/)).toBeVisible()
  await expect(page.getByText(/Nouvelle salle : Salle floraison/)).toBeVisible()

  const captures = join(process.cwd(), '..', '.tmp', 'design-review', 'journal')
  mkdirSync(captures, { recursive: true })
  await page.setViewportSize({ width: 1280, height: 1000 })
  await page.screenshot({ path: join(captures, 'desktop-1280-journal.png'), fullPage: true })
  await page.getByRole('searchbox', { name: 'Rechercher dans le journal' }).fill('no-match')
  await expect(page.getByText('Aucun événement pour ces filtres')).toBeVisible()
  await page.getByRole('searchbox', { name: 'Rechercher dans le journal' }).fill('TEST-PLANT-001')
  await expect(page.getByText('TEST-PLANT-001').first()).toBeVisible()
  await page.setViewportSize({ width: 390, height: 844 })
  await page.waitForTimeout(200)
  expect(await page.evaluate(() => document.documentElement.scrollWidth)).toBeLessThanOrEqual(390)
  await page.screenshot({ path: join(captures, 'mobile-390-journal.png'), fullPage: true })
  await expect(page.getByRole('heading', { name: 'Journal de Culture & Activités Agronomiques' })).toBeVisible()
})
