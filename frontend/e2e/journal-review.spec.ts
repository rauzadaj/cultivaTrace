import { expect, test } from '@playwright/test'
import { mkdirSync } from 'node:fs'
import { join } from 'node:path'

test('journal page renders activity and filters responsively', async ({ page }) => {
  const day = new Date().toISOString().slice(0, 10)
  const occurredAt = `${day}T10:30:00+00:00`
  const plants = [{
    id: 'journal-plant-1', '@id': '/api/plants/journal-plant-1', stage: 'germination', status: 'active',
    room: '/api/rooms/journal-room-1', strain: '/api/strains/journal-strain-1', rfidTag: 'TEST-PLANT-001',
    germinatedAt: `${day}T00:00:00+00:00`, createdAt: occurredAt, ageInDays: 0,
  }]
  const events = [{
    id: 'journal-event-1', eventType: 'germination', notes: 'Germination consignée pour TEST-PLANT-001.', occurredAt,
    user: { id: 'test-user', email: 'operateur@example.test' }, plant: { id: 'journal-plant-1' },
    hashSelf: '0123456789abcdef0123456789abcdef', payload: { to: 'germination' },
  }]
  const collection = (members: unknown[]) => ({
    '@context': '/api/contexts/Collection', '@id': '/api/collection', '@type': 'hydra:Collection',
    'hydra:totalItems': members.length, 'hydra:member': members,
  })

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
      await route.fulfill({ json: collection(events) })
    } else if (request.method() === 'GET' && path === '/api/plants') {
      await route.fulfill({ json: collection(plants) })
    } else if (request.method() === 'GET' && path === '/api/rooms') {
      await route.fulfill({ json: collection([{ id: 'journal-room-1', '@id': '/api/rooms/journal-room-1', name: 'Salle test', type: 'clone', capacityMax: 10, farm: '/api/farms/test' }]) })
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
  await page.getByLabel('Mot de passe').fill('TEST_ONLY_DEMO_PASSWORD')
  await page.getByRole('button', { name: 'Se connecter' }).click()
  await expect(page).toHaveURL(/\/journal/)
  await expect(page.getByRole('heading', { name: 'Journal de Culture & Activités Agronomiques' })).toBeVisible()
  await expect(page.getByText('TEST-PLANT-001').first()).toBeVisible()

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
