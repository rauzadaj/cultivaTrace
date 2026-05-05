import { expect, test } from '@playwright/test'

// ---------------------------------------------------------------------------
// Shared helpers
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

/** Install a minimal API mock and return the page logged-in as the given user. */
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
          email: 'demo@cultivatrace.local',
          roles: ['ROLE_ORG_ADMIN'],
          mfaEnabled: false,
          organization: {
            id: 'org-1',
            name: 'CultivaTrace Demo',
            plan,
            licenseStatus,
          },
        },
      })
      return
    }

    if (req.method() === 'GET' && path === '/api/kyb/status') {
      await route.fulfill({
        json: { licenseStatus, licenseExpiresAt: null },
      })
      return
    }

    if (req.method() === 'POST' && path === '/api/kyb/submit') {
      await route.fulfill({ status: 202, json: { licenseStatus: 'pending' } })
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
        json: { plan, licenseStatus, stripeCustomerId: null, currentPeriodEnd: null },
      })
      return
    }

    if (req.method() === 'POST' && path === '/api/billing/checkout/confirm') {
      await route.fulfill({ json: { plan } })
      return
    }

    // let unhandled routes fall through with 404
    await route.fulfill({ status: 404, json: { message: `Unhandled mock: ${req.method()} ${path}` } })
  })

  await page.goto('/auth')
  await page.getByLabel('Email').fill('demo@cultivatrace.local')
  await page.getByLabel('Mot de passe').fill('demo123')
  await page.getByRole('button', { name: 'Se connecter' }).click()
}

// ---------------------------------------------------------------------------
// Scenario 1 — KYB pending: protected routes redirect to /kyb
// ---------------------------------------------------------------------------

test('kyb pending — protected routes redirect to /kyb and form is accessible', async ({ page }) => {
  await loginAs(page, { licenseStatus: 'pending' })

  // Router guard should redirect to /kyb
  await expect(page).toHaveURL(/\/kyb/)

  // KYB page heading visible
  await expect(page.getByText('Vérification de licence')).toBeVisible()

  // Form fields present
  await expect(page.getByLabel(/type de licence/i)).toBeVisible()
  await expect(page.getByLabel(/numéro de licence/i)).toBeVisible()

  // Attempting to navigate to a protected route stays on /kyb
  await page.goto('/dashboard/overview')
  await expect(page).toHaveURL(/\/kyb/)
})

test('kyb pending — submitting the form shows pending status', async ({ page }) => {
  await loginAs(page, { licenseStatus: 'pending' })
  await expect(page).toHaveURL(/\/kyb/)

  // Fill and submit KYB form
  await page.getByLabel(/type de licence/i).click()
  await page.getByRole('option', { name: /health canada/i }).click()
  await page.getByLabel(/numéro de licence/i).fill('HC-LP-99999')
  await page.getByRole('button', { name: /soumettre/i }).click()

  // After submission, a pending status message should appear
  await expect(page.getByText(/en cours|pending|vérification/i)).toBeVisible({ timeout: 5_000 })
})

// ---------------------------------------------------------------------------
// Scenario 2 — Plan limits: 402 response shows inline upgrade banner
// ---------------------------------------------------------------------------

test('plan limit — 402 on plant creation shows inline upgrade banner and disables submit', async ({
  page,
}) => {
  await loginAs(page, { licenseStatus: 'active', plan: 'starter' })
  await expect(page).toHaveURL(/\/dashboard\/overview/)

  await page.getByRole('link', { name: /plants/i }).click()
  await expect(page).toHaveURL(/\/plants/)

  // Override plant creation to return 402 with plan limit payload
  await page.route('**/api/plants', async (route) => {
    if (route.request().method() !== 'POST') {
      await route.continue()
      return
    }
    await route.fulfill({
      status: 402,
      json: {
        '@type': 'hydra:Error',
        title: 'Plan limit reached',
        detail: 'You have reached the maximum number of plants for your plan.',
        current: 50,
        max: 50,
        upgradeTo: 'pro',
      },
    })
  })

  await page.getByRole('button', { name: /nouveau plant/i }).click()
  await page.getByRole('button', { name: /creer/i }).last().click()

  // Inline banner should appear
  await expect(page.getByText(/limite de plan atteinte/i)).toBeVisible({ timeout: 5_000 })

  // Submit button should be disabled once limit error is set
  const submitBtn = page.getByRole('button', { name: /creer/i }).last()
  await expect(submitBtn).toBeDisabled()
})

// ---------------------------------------------------------------------------
// Scenario 3 — Billing success: confirms checkout and redirects to /billing
// ---------------------------------------------------------------------------

test('billing success — confirms checkout session and redirects to /billing', async ({ page }) => {
  let confirmCalled = false

  await loginAs(page, { licenseStatus: 'active', plan: 'starter' })
  await expect(page).toHaveURL(/\/dashboard\/overview/)

  // Track the confirm call
  await page.route('**/api/billing/checkout/confirm**', async (route) => {
    confirmCalled = true
    await route.fulfill({ json: { plan: 'pro' } })
  })

  await page.goto('/billing/success?session_id=cs_test_abc123')

  // Should redirect to /billing after confirming
  await expect(page).toHaveURL(/\/billing/, { timeout: 10_000 })
  expect(confirmCalled).toBe(true)
})

// ---------------------------------------------------------------------------
// Scenario 4 — Billing cancel: navigating back to /billing shows billing UI
// ---------------------------------------------------------------------------

test('billing cancel — navigating to /billing after cancel shows billing view', async ({ page }) => {
  await loginAs(page, { licenseStatus: 'active', plan: 'starter' })
  await expect(page).toHaveURL(/\/dashboard\/overview/)

  // Simulate cancelling Stripe checkout (user lands back on /billing directly)
  await page.goto('/billing')
  await expect(page).toHaveURL(/\/billing/)

  // Billing page should render plan information
  await expect(page.getByText(/starter|pro|business/i)).toBeVisible({ timeout: 5_000 })
})
