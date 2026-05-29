import { expect, test } from '@playwright/test'

const DEMO_EMAIL = process.env['E2E_DEMO_EMAIL'] ?? 'admin@cultivatrace.local'
const DEMO_PASSWORD = process.env['E2E_DEMO_PASSWORD'] ?? 'demo123'

type MockMapping = {
  id: string
  '@id': string
  externalCatalogEntry: { id: string; sourceProvider: string; externalCode: string; name: string }
  genetic: { id: string; code: string; name: string }
  status: 'pending' | 'linked' | 'rejected'
  notes: string | null
  reviewedAt: string | null
  createdAt: string
  updatedAt: string
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

function mapping(id: string, code: string): MockMapping {
  return {
    id,
    '@id': `/api/genetic_catalog_mappings/${id}`,
    externalCatalogEntry: { id: `ext-${id}`, sourceProvider: 'humboldtseedcompany.com', externalCode: code, name: `External ${code}` },
    genetic: { id: `gen-${id}`, code, name: `Internal ${code}` },
    status: 'pending',
    notes: null,
    reviewedAt: null,
    createdAt: '2026-05-01T00:00:00+00:00',
    updatedAt: '2026-05-01T00:00:00+00:00',
  }
}

test('super admin reviews and approves a catalog mapping', async ({ page }) => {
  const mappings: MockMapping[] = [mapping('1', 'ALPHA'), mapping('2', 'BETA')]

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
          roles: ['ROLE_SUPER_ADMIN'],
          mfaEnabled: false,
          organization: { id: 'org-1', name: 'CultivaTrace Demo', plan: 'pro', licenseStatus: 'active' },
        },
      })
      return
    }

    if (request.method() === 'GET' && path === '/api/genetic_catalog_mappings') {
      await route.fulfill({ json: hydraCollection(mappings) })
      return
    }

    if (request.method() === 'PATCH' && path.startsWith('/api/genetic_catalog_mappings/')) {
      const id = path.split('/').pop() ?? ''
      const payload = request.postDataJSON() as { status: MockMapping['status'] }
      const target = mappings.find((m) => m.id === id)
      if (target) {
        target.status = payload.status
        target.reviewedAt = '2026-05-02T00:00:00+00:00'
      }
      await route.fulfill({ json: target ?? mappings[0] })
      return
    }

    if (request.method() === 'GET' && path === '/api/dashboard') {
      await route.fulfill({
        json: {
          organization: { name: 'CultivaTrace Demo', plan: 'pro', licenseStatus: 'active' },
          plants: { byStage: { germination: 0, vegetation: 0, flowering: 0, harvest: 0 }, total: 0, inFlowering: 0 },
          harvests: { last30Days: 0, totalGrams: 0 },
          alerts: { sensorsInAlert: 0, saturatedRooms: 0, items: [] },
          rooms: { total: 0 },
          overview: { spotlightPlants: [], recentEvents: [] },
          limits: { plants: { current: 0, max: 200 } },
          generatedAt: '2026-05-02T12:00:00+00:00',
        },
      })
      return
    }

    await route.fulfill({ status: 404, json: { message: `Unhandled mock for ${request.method()} ${path}` } })
  })

  // Authenticate as super admin.
  await page.goto('/dashboard/overview')
  await expect(page).toHaveURL(/\/auth/)
  await page.getByLabel('Email').fill(DEMO_EMAIL)
  await page.getByLabel('Password').fill(DEMO_PASSWORD)
  await page.getByRole('button', { name: 'Sign in' }).click()
  await expect(page).toHaveURL(/\/dashboard\/overview/)

  // Navigate within the SPA (in-app, so the auth store stays hydrated) via the
  // super-admin-only Catalog nav entry.
  await page.locator('a[href="/catalog/mappings"]').first().click()
  await expect(page).toHaveURL(/\/catalog\/mappings/)
  await expect(page.getByRole('heading', { name: 'Genetic catalog mappings' })).toBeVisible()

  // Two pending mappings are listed.
  await expect(page.locator('[data-test="mapping-row"]')).toHaveCount(2)
  await expect(page.getByText('External ALPHA')).toBeVisible()

  // Approve the first one — it leaves the pending list.
  await page.locator('[data-test="approve-btn"]').first().click()
  await expect(page.locator('[data-test="mapping-row"]')).toHaveCount(1)
  await expect(page.getByText('External BETA')).toBeVisible()
})
