<template>
  <div class="landing-shell">
    <header class="landing-hero">
      <nav class="landing-nav">
        <RouterLink class="landing-brand" to="/" aria-label="CultivaTrace home">
          <svg
            class="landing-brand__logo"
            viewBox="0 0 420 280"
            role="img"
            aria-label="CultivaTrace"
            xmlns="http://www.w3.org/2000/svg"
          >
            <image :href="navLogoDataUri" width="420" height="280" preserveAspectRatio="xMidYMid meet" />
          </svg>
        </RouterLink>

        <div class="landing-nav__actions">
          <a class="landing-nav__link" href="#product" @click.prevent="scrollToSection('product')">Product</a>
          <a class="landing-nav__link" href="#pricing" @click.prevent="scrollToSection('pricing')">Pricing</a>
          <RouterLink class="landing-nav__link" to="/auth">Sign in</RouterLink>
          <RouterLink class="landing-nav__cta" to="/onboarding">Start free</RouterLink>
        </div>
      </nav>

      <section class="landing-hero__layout">
        <div class="landing-hero__copy" :style="parallaxStyle(0.08)">
          <p class="landing-hero__eyebrow">CultivaTrace</p>
          <h1>Run cultivation with clean control.</h1>
          <p class="landing-hero__text">
            Track plants, rooms, sensors, compliance and billing in one platform built for regulated operations.
          </p>

          <div class="landing-hero__actions">
            <RouterLink class="landing-button landing-button--primary" to="/onboarding">Start free</RouterLink>
            <a class="landing-button landing-button--ghost" href="#product" @click.prevent="scrollToSection('product')">See product</a>
          </div>

          <div class="landing-hero__chips">
            <span>Secure workflow</span>
            <span>Immutable log</span>
            <span>Real-time analytics</span>
          </div>
        </div>

        <div class="landing-hero__panel" :style="parallaxStyle(0.14)">
          <article class="hero-card hero-card--main">
            <div class="hero-card__head">
              <div>
                <p class="hero-card__eyebrow">Operations cockpit</p>
                <strong>Live overview</strong>
              </div>
              <span class="hero-card__badge">Active</span>
            </div>

            <div class="hero-card__stats">
              <div>
                <small>Compliance</small>
                <strong>98.7%</strong>
              </div>
              <div>
                <small>Open alerts</small>
                <strong>03</strong>
              </div>
              <div>
                <small>Rooms online</small>
                <strong>12</strong>
              </div>
            </div>
          </article>

          <div class="hero-card-grid">
            <article class="hero-card">
              <p class="hero-card__eyebrow">KYB</p>
              <strong>Approved</strong>
              <small>Read and write access open</small>
            </article>

            <article class="hero-card">
              <p class="hero-card__eyebrow">Plan limit</p>
              <strong>1000 / 1000</strong>
              <small>Upgrade path ready</small>
            </article>
          </div>
        </div>
      </section>
    </header>

    <main class="landing-main">
      <section class="trust-strip" aria-label="platform highlights" :style="parallaxStyle(0.03)">
        <span>Multi-tenant</span>
        <span>Mercure SSE</span>
        <span>PDF exports</span>
        <span>Server limits</span>
        <span>Role access</span>
      </section>

      <section id="product" class="landing-section">
        <div class="section-heading">
          <p>Product</p>
          <h2>Built like the rest of the app.</h2>
        </div>

        <div class="feature-grid">
          <article class="feature-card feature-card--wide">
            <p class="feature-card__eyebrow">Compliance</p>
            <h3>Append-only events and export-ready proof.</h3>
            <p>Keep a clean history for audits, approvals and operational reviews.</p>
          </article>

          <article class="feature-card">
            <p class="feature-card__eyebrow">Operations</p>
            <h3>Plants, rooms and sensors in one flow.</h3>
            <p>Less context switching, faster decisions.</p>
          </article>

          <article class="feature-card">
            <p class="feature-card__eyebrow">Billing</p>
            <h3>Plans that are enforced server-side.</h3>
            <p>Real limits, real upgrades, clean Stripe flows.</p>
          </article>
        </div>
      </section>

      <section class="landing-section metrics-grid">
        <article class="metric-card">
          <strong>30 min</strong>
          <span>to launch a tenant</span>
        </article>
        <article class="metric-card">
          <strong>Real-time</strong>
          <span>alerts and room signals</span>
        </article>
        <article class="metric-card">
          <strong>1 workspace</strong>
          <span>for ops, compliance and billing</span>
        </article>
      </section>

      <section id="pricing" class="landing-section">
        <div class="section-heading section-heading--split">
          <div>
            <p>Pricing</p>
            <h2>Simple plans for growing teams.</h2>
          </div>
          <RouterLink class="landing-button landing-button--ghost" to="/demo">Book a demo</RouterLink>
        </div>

        <div class="pricing-grid">
          <article class="pricing-card">
            <p class="pricing-card__label">Starter</p>
            <p class="pricing-card__price">99 EUR<span>/month</span></p>
            <ul>
              <li>Up to 100 plants</li>
              <li>Core dashboard</li>
              <li>Email support</li>
            </ul>
          </article>

          <article class="pricing-card pricing-card--featured">
            <p class="pricing-card__badge">Most popular</p>
            <p class="pricing-card__label">Growth</p>
            <p class="pricing-card__price">299 EUR<span>/month</span></p>
            <ul>
              <li>Up to 1000 plants</li>
              <li>Live operations</li>
              <li>Advanced compliance</li>
            </ul>
          </article>

          <article class="pricing-card">
            <p class="pricing-card__label">Enterprise</p>
            <p class="pricing-card__price">Custom</p>
            <ul>
              <li>Expanded scale</li>
              <li>Dedicated SLA</li>
              <li>Custom architecture</li>
            </ul>
          </article>
        </div>
      </section>

      <section class="landing-section final-card">
        <div class="final-card__content">
          <p class="final-card__eyebrow">Ready to roll</p>
          <h2>Launch a workspace that already feels operational.</h2>
        </div>

        <div class="final-card__actions">
          <RouterLink class="landing-button landing-button--primary" to="/onboarding">Create workspace</RouterLink>
          <RouterLink class="landing-button landing-button--ghost" to="/auth">Sign in</RouterLink>
        </div>
      </section>
    </main>
  </div>
</template>

<script setup lang="ts">
import { onBeforeUnmount, onMounted, ref } from 'vue'
import { RouterLink } from 'vue-router'
import navLogoBase64 from '@/assets/cultivatrace-logo-nav-base64.txt?raw'

const scrollPosition = ref(0)
const prefersReducedMotion = ref(false)
let animationFrame = 0
const navLogoDataUri = `data:image/png;base64,${navLogoBase64.trim()}`

function parallaxStyle(intensity: number) {
  return {
  transform: `translate3d(0, ${Math.round(scrollPosition.value * intensity)}px, 0)`,
  }
}

function updateScrollPosition() {
  scrollPosition.value = window.scrollY * -1
  animationFrame = 0
}

function queueScrollUpdate() {
  if (animationFrame !== 0) {
    return
  }

  animationFrame = window.requestAnimationFrame(updateScrollPosition)
}

function scrollToSection(id: string) {
  const element = document.getElementById(id)

  if (!element) {
    return
  }

  const targetTop = element.getBoundingClientRect().top + window.scrollY - 24

  if (prefersReducedMotion.value) {
    window.scrollTo({ top: targetTop, behavior: 'auto' })
    return
  }

  const startTop = window.scrollY
  const distance = targetTop - startTop
  const duration = 700
  const startTime = performance.now()

  const step = (currentTime: number) => {
    const elapsed = currentTime - startTime
    const progress = Math.min(elapsed / duration, 1)
    const eased = 1 - Math.pow(1 - progress, 3)

    window.scrollTo({ top: startTop + distance * eased, behavior: 'auto' })

    if (progress < 1) {
      window.requestAnimationFrame(step)
    }
  }

  window.requestAnimationFrame(step)
}

onMounted(() => {
  prefersReducedMotion.value = window.matchMedia('(prefers-reduced-motion: reduce)').matches
  queueScrollUpdate()
  window.addEventListener('scroll', queueScrollUpdate, { passive: true })
})

onBeforeUnmount(() => {
  window.removeEventListener('scroll', queueScrollUpdate)

  if (animationFrame !== 0) {
    window.cancelAnimationFrame(animationFrame)
  }
})
</script>

<style scoped lang="scss">
.landing-shell {
  min-height: 100vh;
  background:
    radial-gradient(circle at top left, rgba(27, 107, 58, 0.12), transparent 28%),
    radial-gradient(circle at bottom right, rgba(245, 158, 11, 0.08), transparent 30%),
    #f7f8fa;
  color: #33434d;
}

.landing-hero,
.landing-main {
  width: min(1120px, calc(100% - 32px));
  margin: 0 auto;
}

.landing-hero {
  padding: 20px 0 40px;
}

.landing-nav {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 16px;
  margin-bottom: 28px;
}

.landing-brand {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  padding: 0;
  border-radius: 0;
  background: transparent;
  border: 0;
  color: #33434d;
  text-decoration: none;
  box-shadow: none;
}

.landing-brand__logo {
  display: block;
  width: clamp(180px, 20vw, 260px);
  aspect-ratio: 420 / 280;
  height: auto;
  pointer-events: none;
}

.landing-nav__actions,
.landing-hero__actions,
.landing-hero__chips,
.final-card__actions {
  display: flex;
  flex-wrap: wrap;
  gap: 10px;
}

.landing-nav__link,
.landing-nav__cta,
.landing-button {
  border-radius: 999px;
  font-weight: 600;
  text-decoration: none;
  transition:
    transform 0.18s ease,
    box-shadow 0.18s ease,
    background 0.18s ease;
}

.landing-hero__copy,
.landing-hero__panel,
.trust-strip,
.feature-grid,
.metrics-grid,
.pricing-grid,
.final-card {
  will-change: transform;
  transition:
    transform 0.35s ease-out,
    box-shadow 0.18s ease,
    background 0.18s ease;
}

.landing-nav__link {
  color: #607d8b;
  padding: 10px 12px;
}

.landing-nav__cta,
.landing-button--primary {
  color: #fff;
  background: linear-gradient(135deg, #1b6b3a 0%, #2d8d57 100%);
  box-shadow: 0 14px 28px rgba(27, 107, 58, 0.2);
}

.landing-nav__cta,
.landing-button {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  padding: 12px 18px;
}

.landing-button--ghost {
  color: #33434d;
  background: rgba(255, 255, 255, 0.76);
  border: 1px solid rgba(148, 163, 184, 0.2);
}

.landing-nav__link:hover,
.landing-nav__cta:hover,
.landing-button:hover {
  transform: translateY(-1px);
}

.landing-hero__layout {
  display: grid;
  gap: 20px;
}

.landing-hero__copy {
  display: grid;
  gap: 18px;
}

.landing-hero__eyebrow,
.hero-card__eyebrow,
.feature-card__eyebrow,
.section-heading p,
.pricing-card__label,
.pricing-card__badge,
.final-card__eyebrow {
  margin: 0 0 8px;
  color: #78909c;
  font-size: 0.75rem;
  letter-spacing: 0.14em;
  text-transform: uppercase;
}

.landing-hero h1,
.section-heading h2,
.final-card h2,
.feature-card h3 {
  margin: 0;
  font-weight: 600;
  letter-spacing: -0.03em;
}

.landing-hero h1 {
  max-width: 10ch;
  font-size: clamp(2.5rem, 7vw, 4.8rem);
  line-height: 0.98;
}

.landing-hero__text,
.feature-card p,
.metric-card span,
.pricing-card li {
  margin: 0;
  color: #607d8b;
  line-height: 1.65;
}

.landing-hero__text {
  max-width: 48ch;
}

.landing-hero__chips span,
.trust-strip span {
  padding: 10px 14px;
  border-radius: 999px;
  background: rgba(255, 255, 255, 0.72);
  border: 1px solid rgba(148, 163, 184, 0.16);
  color: #455a64;
}

.landing-hero__panel,
.hero-card-grid,
.feature-grid,
.metrics-grid,
.pricing-grid {
  display: grid;
  gap: 16px;
}

.hero-card,
.feature-card,
.metric-card,
.pricing-card,
.final-card {
  padding: 20px;
  border: 1px solid #e2e8f0;
  background: rgba(255, 255, 255, 0.96);
  border-radius: 20px;
  box-shadow: 0 12px 28px rgba(148, 163, 184, 0.12);
}

.hero-card--main {
  background:
    linear-gradient(135deg, rgba(27, 107, 58, 0.96), rgba(17, 24, 39, 0.94)),
    #1b6b3a;
  color: #fff;
}

.hero-card--main .hero-card__eyebrow,
.hero-card--main small {
  color: rgba(240, 248, 244, 0.76);
}

.hero-card__head,
.hero-card__stats {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 12px;
}

.hero-card__badge {
  padding: 6px 10px;
  border-radius: 999px;
  background: rgba(255, 255, 255, 0.14);
  font-size: 0.8rem;
}

.hero-card__stats {
  margin-top: 20px;
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
}

.hero-card__stats div,
.metric-card {
  display: grid;
  gap: 6px;
}

.hero-card__stats strong,
.metric-card strong,
.pricing-card__price,
.hero-card strong {
  font-size: 1.5rem;
  line-height: 1;
}

.landing-main {
  padding-bottom: 80px;
}

.trust-strip {
  display: flex;
  flex-wrap: wrap;
  gap: 10px;
  margin-bottom: 28px;
}

.landing-section {
  padding: 20px 0 28px;
}

.section-heading {
  display: grid;
  gap: 10px;
  margin-bottom: 22px;
}

.section-heading h2,
.final-card h2 {
  max-width: 12ch;
  font-size: clamp(2rem, 5vw, 3.4rem);
  line-height: 1;
}

.feature-card--wide {
  background: linear-gradient(180deg, rgba(232, 245, 238, 0.96), rgba(255, 255, 255, 0.96));
}

.pricing-card {
  display: grid;
  gap: 14px;
}

.pricing-card--featured {
  border-color: rgba(27, 107, 58, 0.22);
  background: linear-gradient(180deg, rgba(232, 245, 238, 0.96), rgba(255, 255, 255, 0.98));
}

.pricing-card__badge {
  color: #1b6b3a;
}

.pricing-card__price {
  margin: 0;
  color: #33434d;
}

.pricing-card__price span {
  margin-left: 8px;
  font-size: 1rem;
  font-weight: 500;
  color: #78909c;
}

.pricing-card ul {
  display: grid;
  gap: 10px;
  margin: 0;
  padding-left: 18px;
}

.section-heading--split {
  gap: 18px;
}

.final-card {
  display: grid;
  gap: 18px;
  padding: 28px 24px;
}

.final-card__content {
  display: grid;
  gap: 10px;
  min-width: 0;
}

@media (min-width: 720px) {
  .landing-hero,
  .landing-main {
    width: min(1120px, calc(100% - 48px));
  }

  .hero-card-grid,
  .metrics-grid {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }
}

@media (min-width: 960px) {
  .landing-hero__layout {
    grid-template-columns: minmax(0, 1.05fr) minmax(380px, 0.95fr);
    align-items: center;
  }

  .feature-grid {
    grid-template-columns: repeat(3, minmax(0, 1fr));
  }

  .feature-card--wide {
    grid-column: span 1;
  }

  .metrics-grid,
  .pricing-grid {
    grid-template-columns: repeat(3, minmax(0, 1fr));
  }

  .section-heading--split,
  .final-card {
    grid-template-columns: minmax(0, 1fr) auto;
    align-items: end;
  }

  .final-card {
    align-items: center;
    padding: 36px 32px;
  }

  .final-card__content {
    max-width: 560px;
  }
}
</style>
