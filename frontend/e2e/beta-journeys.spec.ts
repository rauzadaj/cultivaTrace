import { expect, test } from '@playwright/test'

// ---------------------------------------------------------------------------
// Test credentials — configure via env vars; defaults are safe for local dev
// ---------------------------------------------------------------------------

const DEMO_EMAIL = process.env['E2E_DEMO_EMAIL'] ?? 'demo@cultivatrace.local'
const DEMO_PASSWORD = process.env['E2E_DEMO_PASSWORD'] ?? 'demo123'

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

function hydraCollection<T>(members: T[]) {
  return {
    '@context': '/api/contexts/Collection',
    '@id': '/api/collection',
    '@type': 'hydra:Collection',
    'hydra:totalItems': members.length,
    'hydra:member': members,
  }
}

/**
 * Install API mocks and perform login via the auth form.
 * Uses the auth form (not page.goto to a protected route) so that the pinia
 * auth store is fully hydrated (fetchMe called, organization loaded) before
 * any subsequent navigation.
 */
async function loginAs(
  page: import('@playwright/test').Page,
  opts: { licenseStatus?: string; plan?: string } = {},
) {
  const { licenseStatus = 'active', plan = 'pro' } = opts

  await page.route('**/api/**', async (route) => {
    const req = route.request()
    const url = new URL(req.url())
    const path = url.pathname

    if (req.method() === 'POST' && path === '/api/auth/login') {
      await route.fulfill({ json: { token: 'test.jwt.token' } })
      return
    }

    if (req.method() === 'GET' && path === '/api/me') {
      await route.fulfill({
        json: {
          id: 'user-1',
          email: DEMO_EMAIL,
          roles: ['ROLE_ORG_ADMIN'],
          mfaEnabled: false,
          organization: { id: 'org-1', name: 'CultivaTrace Demo', plan, licenseStatus },
        },
      })
      return
    }

    if (req.method() === 'GET' && path === '/api/kyb/status') {
      await route.fulfill({ json: { licenseStatus, licenseExpiresAt: null } })
      return
    }

    // KYB upload (multipart form — frontend posts to /kyb/upload)
    if (req.method() === 'POST' && path === '/api/kyb/upload') {
      await route.fulfill({ status: 202, json: { status: 'pending' } })
      return
    }

    if (req.method() === 'GET' && path === '/api/dashboard') {
      await route.fulfill({
        json: {
          organization: { name: 'CultivaTrace Demo', plan, licenseStatus },
          plants: { byStage: {}, total: 0, inFlowering: 0 },
          harvests: { last30Days: 0, totalGrams: 0 },
          alerts: { sensorsInAlert: 0, saturatedRooms: 0, items: [] },
          rooms: { total: 0 },
          overview: { spotlightPlants: [], recentEvents: [] },
          limits: { plants: { current: 0, max: 50 } },
          generatedAt: new Date().toISOString(),
        },
      })
      return
    }

    if (req.method() === 'GET' && path === '/api/rooms') {
      await route.fulfill({
        json: hydraCollection([
          { id: 'room-1', '@id': '/api/rooms/room-1', name: 'Salle A', type: 'veg', capacityMax: 24, farm: '/api/farms/farm-1' },
        ]),
      })
      return
    }

    if (req.method() === 'GET' && path === '/api/strains') {
      await route.fulfill({ json: hydraCollection([]) })
      return
    }

    if (req.method() === 'GET' && path === '/api/plants') {
      await route.fulfill({ json: hydraCollection([]) })
      return
    }

    if (req.method() === 'GET' && path === '/api/billing/status') {
      await route.fulfill({
        json: { plan, licenseStatus, hasActiveSubscription: false, limits: null, stripeCustomerId: null, currentPeriodEnd: null },
      })
      return
    }

    if (req.method() === 'GET' && path === '/api/alerts') {
      await route.fulfill({ json: { '@type': 'hydra:Collection', 'hydra:totalItems': 0, 'hydra:member': [] } })
      return
    }

    if (req.method() === 'POST' && path === '/api/auth/logout') {
      await route.fulfill({ status: 204 })
      return
    }

    // Fallback so test-specific handlers registered after loginAs() can intercept
    // this endpoint (works whether Playwright applies FIFO or LIFO route ordering).
    if (req.method() === 'POST' && path === '/api/billing/checkout/confirm') {
      await route.fallback()
      return
    }

    await route.fulfill({ status: 404, json: { message: `Unhandled mock: ${req.method()} ${path}` } })
  })

  await page.goto('/auth')
  await page.getByLabel('Email').fill(DEMO_EMAIL)
  await page.getByLabel('Password').fill(DEMO_PASSWORD)
  await page.getByRole('button', { name: 'Sign in' }).click()
}

/**
 * Push a new route via Vue Router without triggering a full page reload.
 * Avoids the pinia auth state loss that page.goto() causes.
 */
async function routerPush(page: import('@playwright/test').Page, path: string): Promise<void> {
  await page.evaluate((p: string) => {
    const el = document.getElementById('app') as { __vue_app__?: { config: { globalProperties: { $router: { push: (path: string) => Promise<unknown> } } } } } | null
    return el?.__vue_app__?.config.globalProperties.$router.push(p)
  }, path)
}

// ---------------------------------------------------------------------------
// Scenario 1 — KYB pending: router guard blocks protected routes
// ---------------------------------------------------------------------------

test('kyb pending — protected routes redirect to /kyb', async ({ page }) => {
  await loginAs(page, { licenseStatus: 'pending' })

  // Router guard should redirect to /kyb
  await expect(page).toHaveURL(/\/kyb/)
  await expect(page.getByText('License verification')).toBeVisible()

  // Form fields present
  await expect(page.getByLabel(/license type/i)).toBeVisible()
  await expect(page.getByLabel(/license number/i)).toBeVisible()
})

test('kyb pending — submitting the form shows pending status', async ({ page }) => {
  await loginAs(page, { licenseStatus: 'pending' })
  await expect(page).toHaveURL(/\/kyb/)

  // Quasar q-select renders options in a portal — use .q-menu text, not getByRole('option')
  await page.getByLabel(/license type/i).click()
  await page.locator('.q-menu').waitFor({ timeout: 3_000 })
  await page.locator('.q-menu').getByText('Health Canada (Canada)').click()

  await page.getByLabel(/license number/i).fill('HC-LP-99999')
  await page.getByRole('button', { name: /submit/i }).click()

  // After upload, KYB view shows pending status text from statusMessage computed.
  // Two elements can match the regex (status banner + toast notification) — .first()
  // avoids the strict-mode violation while still asserting the pending state is visible.
  await expect(page.getByText(/verification in progress|in progress|pending/i).first()).toBeVisible({ timeout: 5_000 })
})

// ---------------------------------------------------------------------------
// Scenario 2 — Plan limits: 402 shows inline upgrade banner
// ---------------------------------------------------------------------------

test('plan limit — 402 on plant creation shows inline upgrade banner and disables submit', async ({
  page,
}) => {
  await loginAs(page, { licenseStatus: 'active', plan: 'starter' })
  await expect(page).toHaveURL(/\/dashboard\/overview/)

  await page.getByRole('link', { name: /plants/i }).click()
  await expect(page).toHaveURL(/\/plants/)

  // Override plant POST to return 402 with the planLimit shape PlantForm expects
  await page.route('**/api/plants', async (route) => {
    if (route.request().method() !== 'POST') {
      await route.continue()
      return
    }
    await route.fulfill({
      status: 402,
      json: {
        planLimit: {
          limitType: 'plants',
          current: 50,
          max: 50,
          upgradeTo: 'pro',
          upgradeUrl: '/billing',
        },
      },
    })
  })

  await page.getByRole('button', { name: /new plant/i }).click()
  await page.getByRole('button', { name: /create/i }).last().click()

  await expect(page.getByText(/plan limit reached/i)).toBeVisible({ timeout: 5_000 })
  await expect(page.getByRole('button', { name: /create/i }).last()).toBeDisabled()
})

// ---------------------------------------------------------------------------
// Scenario 3 — Billing success: confirms checkout and redirects to /billing
// ---------------------------------------------------------------------------

test('billing success — confirms checkout session and redirects to /billing', async ({ page }) => {
  let confirmCalled = false

  await loginAs(page, { licenseStatus: 'active', plan: 'starter' })
  await expect(page).toHaveURL(/\/dashboard\/overview/)

  await page.route('**/api/billing/checkout/confirm', async (route) => {
    confirmCalled = true
    await route.fulfill({ json: { plan: 'pro' } })
  })

  // SPA navigation — routerPush awaits router.push() so navigation reaches /billing/success
  // before onMounted fires; onMounted calls confirmCheckout then router.replace('/billing').
  // The intermediate /billing/success URL resolves too fast to assert on with toHaveURL.
  await routerPush(page, '/billing/success?session_id=cs_test_abc123')
  await page.waitForURL(url => new URL(url).pathname === '/billing', { timeout: 10_000 })
  expect(confirmCalled).toBe(true)
})

// ---------------------------------------------------------------------------
// Scenario 4 — Billing cancel: /billing renders plan options
// ---------------------------------------------------------------------------

test('billing cancel — navigating to /billing after cancel shows billing view', async ({ page }) => {
  await loginAs(page, { licenseStatus: 'active', plan: 'starter' })
  await expect(page).toHaveURL(/\/dashboard\/overview/)

  // SPA navigation preserves pinia auth state (isPlanActive stays true)
  await page.getByRole('link', { name: /Billing/i }).click()
  await expect(page).toHaveURL(/\/billing/)

  // 'Change plan' is unique to BillingView — avoids strict-mode violations from
  // getByText(/Starter/) matching multiple plan card elements.
  await expect(page.getByText('Change plan')).toBeVisible({ timeout: 5_000 })
})
