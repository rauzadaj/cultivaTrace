import { expect, test } from '@playwright/test'
import { mkdirSync } from 'node:fs'
import { join } from 'node:path'

test('Figma review captures dashboard and existing plants navigation', async ({ page }) => {
  const rooms = [{
    id: 'room-1',
    '@id': '/api/rooms/room-1',
    name: 'Salle Veg A',
    type: 'veg',
    capacityMax: 24,
    farm: '/api/farms/farm-1',
  }]
  const plants = [{
    id: 'plant-1',
    '@id': '/api/plants/plant-1',
    stage: 'vegetation',
    status: 'active',
    room: '/api/rooms/room-1',
    strain: '/api/strains/strain-1',
    rfidTag: 'RFID-001',
    germinatedAt: '2026-03-01T00:00:00+00:00',
    createdAt: '2026-03-01T08:00:00+00:00',
    ageInDays: 33,
  }]

  await page.route('**/api/**', async (route) => {
    const request = route.request()
    const path = new URL(request.url()).pathname

    if (request.method() === 'POST' && path === '/api/auth/login') {
      await route.fulfill({ json: { token: 'test.jwt.token', refreshToken: 'test.refresh.token' } })
      return
    }

    if (request.method() === 'GET' && path === '/api/me') {
      await route.fulfill({ json: {
        id: 'user-1', email: 'demo@cultivatrace.local', roles: ['ROLE_ORG_ADMIN'], mfaEnabled: false,
        organization: { id: 'org-1', name: 'CultivaTrace Demo', plan: 'pro', licenseStatus: 'active' },
      } })
      return
    }

    if (request.method() === 'GET' && path === '/api/dashboard') {
      await route.fulfill({ json: {
        organization: { name: 'CultivaTrace Demo', plan: 'pro', licenseStatus: 'active' },
        plants: { byStage: { germination: 0, vegetation: plants.length, flowering: 0, harvest: 0 }, total: plants.length, inFlowering: 0 },
        harvests: { last30Days: 0, totalGrams: 0 },
        alerts: { sensorsInAlert: 0, saturatedRooms: 0, items: [] },
        rooms: { total: rooms.length },
        overview: { spotlightPlants: [{ id: 'plant-1', name: 'RFID-001', strain: 'Gelato', room: 'Salle Veg A', stage: 'vegetation', status: 'active', ageInDays: 33 }], recentEvents: [] },
        limits: { plants: { current: plants.length, max: 200 } }, generatedAt: '2026-04-03T12:00:00+00:00',
      } })
      return
    }

    if (request.method() === 'GET' && path === '/api/rooms') {
      await route.fulfill({ json: { '@context': '/api/contexts/Collection', '@id': '/api/rooms', '@type': 'hydra:Collection', 'hydra:totalItems': rooms.length, 'hydra:member': rooms } })
      return
    }

    if (request.method() === 'GET' && path === '/api/strains') {
      await route.fulfill({ json: { '@context': '/api/contexts/Collection', '@id': '/api/strains', '@type': 'hydra:Collection', 'hydra:totalItems': 1, 'hydra:member': [{ id: 'strain-1', '@id': '/api/strains/strain-1', name: 'Gelato', genetics: 'hybrid' }] } })
      return
    }

    if (request.method() === 'GET' && path === '/api/plants') {
      await route.fulfill({ json: { '@context': '/api/contexts/Collection', '@id': '/api/plants', '@type': 'hydra:Collection', 'hydra:totalItems': plants.length, 'hydra:member': plants } })
      return
    }

    if (request.method() === 'GET' && path === '/api/alerts') {
      await route.fulfill({ json: { '@context': '/api/contexts/Collection', '@id': '/api/alerts', '@type': 'hydra:Collection', 'hydra:totalItems': 0, 'hydra:member': [] } })
      return
    }

    await route.fulfill({ status: 404, json: { message: `Unhandled design review mock: ${request.method()} ${path}` } })
  })

  await page.goto('/dashboard/overview')
  await expect(page).toHaveURL(/\/auth/)
  await page.getByLabel('Email').fill('demo@cultivatrace.local')
  await page.getByLabel('Mot de passe').fill('TEST_ONLY_DEMO_PASSWORD')
  await page.getByRole('button', { name: 'Se connecter' }).click()
  await expect(page).toHaveURL(/\/dashboard\/overview/)
  await expect(page.locator('.dashboard-view')).toBeVisible()

  const phase = process.env.CT_DESIGN_CAPTURE_PHASE ?? 'after'
  const viewportPath = join(process.cwd(), '..', '.tmp', 'design-review', phase)
  mkdirSync(viewportPath, { recursive: true })

  for (const viewport of [
    { name: 'desktop-1280', width: 1280, height: 1000 },
    { name: 'desktop-1440', width: 1440, height: 1000 },
    { name: 'tablet-1024', width: 1024, height: 900 },
    { name: 'tablet-768', width: 768, height: 1024 },
    { name: 'mobile-375', width: 375, height: 812 },
    { name: 'mobile-390', width: 390, height: 844 },
  ]) {
    await page.setViewportSize({ width: viewport.width, height: viewport.height })
    await page.waitForTimeout(150)
    await page.screenshot({ path: join(viewportPath, `${viewport.name}-dashboard.png`), fullPage: true })
  }

  await page.setViewportSize({ width: 1280, height: 1000 })
  await page.getByRole('link', { name: /Plants/i }).click()
  await expect(page).toHaveURL(/\/plants/)
  await expect(page.getByText('RFID-001')).toBeVisible()
  await page.screenshot({ path: join(viewportPath, 'desktop-1280-plants.png'), fullPage: true })
})
