<template>
  <div class="shell-navigation" :class="{ 'shell-navigation--compact': compact }">
    <ShellBrand :compact="compact" :plan="plan" />
    <nav class="shell-navigation__groups" aria-label="Navigation principale">
      <section v-for="group in groups" :key="group.label" class="shell-navigation__group">
        <p v-if="!compact" class="shell-navigation__heading">{{ group.label }}</p>
        <div class="shell-navigation__links">
          <RouterLink
            v-for="item in group.items"
            :key="item.to"
            :to="item.to"
            class="shell-navigation__link"
            active-class="shell-navigation__link--active"
            :aria-label="displayLabel(item)"
            :title="compact ? displayLabel(item) : undefined"
            @click="$emit('navigate')"
          >
            <img v-if="iconAsset(item.to)" :src="iconAsset(item.to)" alt="" class="shell-navigation__icon" aria-hidden="true">
            <q-icon v-else :name="item.icon" size="16px" aria-hidden="true" />
            <span v-if="!compact" class="shell-navigation__label">{{ displayLabel(item) }}</span>
            <span v-if="item.to === '/dashboard/overview' && !compact" class="shell-navigation__current-dot" aria-hidden="true" />
          </RouterLink>
        </div>
      </section>
    </nav>
    <button v-if="!mobile" type="button" class="shell-navigation__collapse" :aria-label="compact ? 'Développer le menu' : 'Réduire le menu'" @click="$emit('toggle-collapse')">
      <q-icon :name="compact ? 'mdi-chevron-right' : 'mdi-chevron-left'" size="16px" aria-hidden="true" />
      <span v-if="!compact">Réduire le menu</span>
    </button>
    <div class="shell-navigation__footer">
      <div v-if="mobile" class="shell-navigation__user">
        <strong>{{ userDisplayName }}</strong>
        <span>{{ userIdentity }}</span>
        <q-btn flat no-caps align="left" icon="mdi-logout" label="Se déconnecter" @click="$emit('logout')" />
      </div>
      <div class="shell-navigation__connection" :class="{ 'shell-navigation__connection--offline': !online }" role="status" :title="connectionLabel">
        <span class="shell-navigation__status-dot" aria-hidden="true" />
        <div v-if="!compact" class="shell-navigation__connection-copy">
          <strong>{{ online ? 'Navigateur en ligne' : 'Hors ligne' }}</strong>
          <span>{{ pendingCount ? `${pendingCount} action${pendingCount > 1 ? 's' : ''} en attente` : 'Aucune action en attente' }}</span>
        </div>
        <img v-if="!compact && online" src="/design/executive/1-3052/21eae.svg" alt="" class="shell-navigation__connection-icon" aria-hidden="true">
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { RouterLink } from 'vue-router'
import type { RoleScopedNavigationItem } from '@/router/access'
import ShellBrand from './ShellBrand.vue'

const props = withDefaults(defineProps<{
  items: readonly RoleScopedNavigationItem[]
  compact?: boolean
  mobile?: boolean
  plan?: string
  online: boolean
  pendingCount: number
  userDisplayName: string
  userIdentity: string
}>(), { compact: false, mobile: false, plan: '' })

defineEmits<{ navigate: []; logout: []; 'toggle-collapse': [] }>()

const governanceRoutes = new Set(['/compliance', '/billing', '/settings', '/more'])
const groups = computed(() => [
  { label: 'Supervision & culture', items: props.items.filter((item) => !governanceRoutes.has(item.to)) },
  { label: 'Gouvernance & système', items: props.items.filter((item) => governanceRoutes.has(item.to)) },
].filter((group) => group.items.length > 0))

const labels: Record<string, string> = {
  '/dashboard/overview': 'Vue générale',
  '/plants': 'Gestion des plants',
  '/rooms': 'Salles de culture',
  '/sensors': 'Capteurs & monitoring',
  '/journal': 'Journal de Culture',
  '/reporting': 'Reporting & exports',
  '/compliance': 'Conformité',
  '/settings': 'Paramètres',
}
const assets: Record<string, string> = {
  '/dashboard/overview': '1635b.svg',
  '/plants': '25cdd.svg',
  '/rooms': '111e6.svg',
  '/sensors': 'c9d61.svg',
  '/reporting': '98cfc.svg',
  '/compliance': '80cba.svg',
  '/settings': '3ab10.svg',
}
const connectionLabel = computed(() => `${props.online ? 'Navigateur en ligne' : 'Hors ligne'} — ${props.pendingCount} action(s) en attente`)
const displayLabel = (item: RoleScopedNavigationItem) => labels[item.to] ?? item.label
const iconAsset = (route: string) => assets[route] ? `/design/executive/1-3052/${assets[route]}` : undefined
</script>

<style scoped>
.shell-navigation { display: flex; flex-direction: column; min-height: 100%; background: var(--ct-nav); color: var(--ct-secondary); font-family: var(--ct-font-sans); }
.shell-navigation__groups { display: flex; flex-direction: column; gap: var(--ct-space-4); padding: var(--ct-space-3) var(--ct-space-2); flex: 1 0 auto; }
.shell-navigation__group { min-width: 0; }
.shell-navigation__heading { margin: 0 0 var(--ct-space-1); padding: 6px var(--ct-space-2) var(--ct-space-2); font-size: 11px; line-height: 14px; font-weight: 500; letter-spacing: 0.55px; color: var(--ct-muted); text-transform: uppercase; }
.shell-navigation__links { display: grid; gap: 2px; }
.shell-navigation__link { display: flex; align-items: center; gap: var(--ct-space-2); min-height: 28px; padding: 6px var(--ct-space-2); border-radius: var(--ct-radius-md); color: var(--ct-secondary); font-size: 12px; font-weight: 500; line-height: 16px; letter-spacing: 0.12px; text-decoration: none; }
.shell-navigation__link:hover { background: var(--ct-surface); color: var(--ct-text); }
.shell-navigation__link:focus-visible { outline: 2px solid var(--ct-mint); outline-offset: 2px; }
.shell-navigation__link--active { background: color-mix(in srgb, var(--ct-mint) 7%, transparent); color: var(--ct-text); }
.shell-navigation__label { min-width: 0; }
.shell-navigation__icon { width: 16px; height: 16px; object-fit: contain; flex: 0 0 auto; }
.shell-navigation__current-dot { display: none; width: 6px; height: 6px; margin-left: auto; flex: 0 0 auto; border-radius: 50%; background: var(--ct-mint); opacity: 0.6; }
.shell-navigation__link--active .shell-navigation__current-dot { display: block; }
.shell-navigation__collapse { display: flex; align-items: center; justify-content: flex-start; gap: var(--ct-space-2); min-height: 40px; margin: 0 var(--ct-space-2) var(--ct-space-2); padding: 0 var(--ct-space-2); border: 0; border-radius: var(--ct-radius-md); background: transparent; color: var(--ct-muted); font: 11px/16px var(--ct-font-sans); cursor: pointer; }
.shell-navigation__collapse:hover { background: var(--ct-surface); color: var(--ct-text); }
.shell-navigation--compact .shell-navigation__collapse { justify-content: center; padding-inline: 0; }
.shell-navigation__footer { padding: var(--ct-space-2) var(--ct-space-2) calc(var(--ct-space-2) + env(safe-area-inset-bottom)); background: color-mix(in srgb, var(--ct-surface) 60%, transparent); border-top: 1px solid var(--ct-border); }
.shell-navigation__connection { display: flex; align-items: center; gap: var(--ct-space-2); padding: 9px; min-height: 44px; border: 1px solid var(--ct-border); border-radius: var(--ct-radius-md); background: var(--ct-nav); }
.shell-navigation__status-dot { width: 8px; height: 8px; flex: 0 0 auto; border-radius: 50%; background: var(--ct-mint); }
.shell-navigation__connection--offline .shell-navigation__status-dot { background: var(--ct-danger); }
.shell-navigation__connection-copy { display: flex; flex-direction: column; gap: 2px; min-width: 0; }
.shell-navigation__connection-copy strong { color: var(--ct-text); font-size: 11px; font-weight: 500; line-height: 12px; }
.shell-navigation__connection-copy span { color: var(--ct-muted); font: 10px/13px var(--ct-font-serif); }
.shell-navigation__connection-icon { width: 12px; height: 12px; margin-left: auto; }
.shell-navigation__user { display: grid; gap: var(--ct-space-1); padding: var(--ct-space-2); margin-bottom: var(--ct-space-2); }
.shell-navigation__user strong, .shell-navigation__user span { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.shell-navigation__user strong { font-size: 13px; color: var(--ct-text); }
.shell-navigation__user span { font-size: 11px; color: var(--ct-muted); }
.shell-navigation__user .q-btn { min-height: 44px; margin-top: var(--ct-space-2); padding: 0; font-size: 12px; color: var(--ct-mint); }
.shell-navigation--compact .shell-navigation__link { justify-content: center; min-height: 44px; padding: var(--ct-space-2); }
.shell-navigation--compact .shell-navigation__connection { justify-content: center; padding-inline: var(--ct-space-2); }
@media (max-width: 767px) { .shell-navigation__link { min-height: 44px; } }
</style>
