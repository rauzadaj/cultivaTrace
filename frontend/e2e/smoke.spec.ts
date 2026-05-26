import { expect, test } from '@playwright/test'

const DEMO_EMAIL = process.env['E2E_DEMO_EMAIL'] ?? 'demo@cultivatrace.local'
const DEMO_PASSWORD = process.env['E2E_DEMO_PASSWORD'] ?? 'demo123'

type MockPlant = {
  id: string
  '@id': string
  stage: 'germination' | 'vegetation' | 'flowering' | 'harvest'
  status: 'active' | 'archived' | 'destroyed' | 'harvested'
  room: string
  strain: string
  rfidTag?: string | null
  germinatedAt: string
  createdAt: string
  ageInDays: number
}

function hydraCollection<T>(members: T[]) {
  return {
    '@context': '/api/contexts/Collection',
    '@id': '/api/collection',
    '@type': 'hydra:Collection',
    'hydra:totalItems': members.length,
    'hydra:member': members,
  }
}

test('login, navigation, and plant creation smoke journey', async ({ page }) => {
  const rooms = [
    {
      id: 'room-1',
      '@id': '/api/rooms/room-1',
      name: 'Salle Veg A',
      type: 'veg',
      capacityMax: 24,
      farm: '/api/farms/farm-1',
    },
  ]
  const strains = [
    {
      id: 'strain-1',
      '@id': '/api/strains/strain-1',
      name: 'Gelato',
      genetics: 'hybrid',
    },
  ]
  const plants: MockPlant[] = [
    {
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
    },
  ]

  await page.route('**/api/**', async (route) => {
    const request = route.request()
    const url = new URL(request.url())
    const path = url.pathname

    if (request.method() === 'POST' && path === '/api/auth/login') {
      await route.fulfill({ json: { token: 'test.jwt.token' } })
      return
    }

    if (request.method() === 'GET' && path === '/api/me') {
      await route.fulfill({
        json: {
          id: 'user-1',
          email: DEMO_EMAIL,
          roles: ['ROLE_ORG_ADMIN'],
          mfaEnabled: false,
          organization: {
            id: 'org-1',
            name: 'CultivaTrace Demo',
            plan: 'pro',
            licenseStatus: 'active',
          },
        },
      })
      return
    }

    if (request.method() === 'GET' && path === '/api/dashboard') {
      await route.fulfill({
        json: {
          organization: { name: 'CultivaTrace Demo', plan: 'pro', licenseStatus: 'active' },
          plants: {
            byStage: { germination: 0, vegetation: plants.length, flowering: 0, harvest: 0 },
            total: plants.length,
            inFlowering: 0,
          },
          harvests: { last30Days: 0, totalGrams: 0 },
          alerts: { sensorsInAlert: 0, saturatedRooms: 0, items: [] },
          rooms: { total: rooms.length },
          overview: {
            spotlightPlants: plants.slice(0, 1).map((plant) => ({
              id: plant.id,
              name: plant.rfidTag ?? plant.id,
              strain: 'Gelato',
              room: 'Salle Veg A',
              stage: plant.stage,
              status: plant.status,
              ageInDays: plant.ageInDays,
            })),
            recentEvents: [],
          },
          limits: { plants: { current: plants.length, max: 200 } },
          generatedAt: '2026-04-03T12:00:00+00:00',
        },
      })
      return
    }

    if (request.method() === 'GET' && path === '/api/rooms') {
      await route.fulfill({ json: hydraCollection(rooms) })
      return
    }

    if (request.method() === 'GET' && path === '/api/strains') {
      await route.fulfill({ json: hydraCollection(strains) })
      return
    }

    if (request.method() === 'GET' && path === '/api/plants') {
      await route.fulfill({ json: hydraCollection(plants) })
      return
    }

    if (request.method() === 'POST' && path === '/api/plants') {
      const payload = request.postDataJSON() as Record<string, string | null>
      const createdPlant: MockPlant = {
        id: 'plant-2',
        '@id': '/api/plants/plant-2',
        stage: 'germination',
        status: 'active',
        room: String(payload.room),
        strain: String(payload.strain ?? '/api/strains/strain-1'),
        rfidTag: payload.rfidTag ?? null,
        germinatedAt: String(payload.germinatedAt),
        createdAt: '2026-04-03T12:00:00+00:00',
        ageInDays: 0,
      }
      plants.unshift(createdPlant)
      await route.fulfill({ json: createdPlant })
      return
    }

    if (request.method() === 'GET' && path.startsWith('/api/plants/')) {
      const plantId = path.split('/').pop() ?? ''
      const plant = plants.find((item) => item.id === plantId)
      await route.fulfill({ json: plant ?? plants[0] })
      return
    }

    if (request.method() === 'GET' && path === '/api/plant_events') {
      await route.fulfill({ json: hydraCollection([]) })
      return
    }

    await route.fulfill({ status: 404, json: { message: `Unhandled mock for ${request.method()} ${path}` } })
  })

  await page.goto('/dashboard/overview')
  await expect(page).toHaveURL(/\/auth/)

  await page.getByLabel('Email').fill(DEMO_EMAIL)
  await page.getByLabel('Password').fill(DEMO_PASSWORD)
  await page.getByRole('button', { name: 'Sign in' }).click()

  await expect(page).toHaveURL(/\/dashboard\/overview/)
  await expect(page.getByText('Shift overview')).toBeVisible()

  await page.getByRole('link', { name: /Plants/i }).click()
  await expect(page).toHaveURL(/\/plants/)
  await expect(page.getByText('RFID-001')).toBeVisible()

  await page.getByRole('button', { name: 'New plant' }).click()
  await page.getByLabel('RFID number').fill('RFID-NEW-001')
  await page.getByRole('button', { name: 'Create' }).last().click()

  await expect(page).toHaveURL(/\/plants\/plant-2/)
  await expect(page.getByRole('heading', { name: 'RFID-NEW-001' })).toBeVisible()
  await expect(page.getByRole('tab', { name: 'Infos' })).toBeVisible()
})
