<template>
  <q-toolbar class="shell-header">
    <q-btn v-if="mobile" flat round dense class="shell-header__icon-button" icon="mdi-menu" aria-label="Ouvrir le menu" @click="$emit('toggleDrawer')" />
    <div class="shell-header__context">
      <div v-if="!mobile" class="shell-header__organization" :title="organizationName">
        <img src="/design/executive/1-3006/d2963.svg" alt="" aria-hidden="true">
        <span>{{ organizationName || 'CultivaTrace' }}</span>
      </div>
      <div class="shell-header__location">
        <span v-if="mobile" class="shell-header__eyebrow">CultivaTrace</span>
        <strong>{{ mobile ? title : breadcrumb }}</strong>
      </div>
    </div>
    <div class="shell-header__actions">
      <div v-if="!mobile" class="shell-header__connection" :class="{ 'shell-header__connection--offline': !online }" role="status">
        <span class="shell-header__status-dot" aria-hidden="true" />
        <span>{{ online ? 'En ligne' : 'Hors ligne' }}</span>
        <span v-if="pendingCount" class="shell-header__pending">{{ pendingCount }} en attente</span>
      </div>
      <q-btn v-if="showAction" flat round dense class="shell-header__icon-button shell-header__quick-action" :icon="actionIcon" :aria-label="actionLabel" @click="$emit('action')" />
      <q-btn flat round dense class="shell-header__icon-button shell-header__notifications" :aria-label="alertCount ? `Notifications, ${alertCount} alertes` : 'Notifications'" @click="$emit('notifications')">
        <img src="/design/executive/1-3006/5a1ee.svg" alt="" aria-hidden="true">
        <span v-if="alertCount > 0" class="shell-header__notification-dot" aria-hidden="true" />
      </q-btn>
      <q-btn v-if="!mobile" flat no-caps dense class="shell-header__account" aria-label="Menu du compte">
        <span class="shell-header__user-copy">
          <strong>{{ userDisplayName }}</strong>
          <span>{{ userRole }}</span>
        </span>
        <q-avatar size="32px" class="shell-header__avatar">
          <img src="/design/executive/1-3006/f408b.svg" alt="" aria-hidden="true">
        </q-avatar>
        <q-menu anchor="bottom right" self="top right">
          <q-list style="min-width: 180px">
            <q-item><q-item-section>{{ userIdentity }}</q-item-section></q-item>
            <q-separator />
            <q-item clickable v-close-popup @click="$emit('logout')"><q-item-section>Se déconnecter</q-item-section></q-item>
          </q-list>
        </q-menu>
      </q-btn>
    </div>
  </q-toolbar>
</template>

<script setup lang="ts">
defineProps<{
  mobile: boolean
  title: string
  breadcrumb: string
  organizationName: string
  userIdentity: string
  userDisplayName: string
  userRole: string
  online: boolean
  pendingCount: number
  alertCount: number
  showAction: boolean
  actionIcon: string
  actionLabel: string
}>()

defineEmits<{ toggleDrawer: []; action: []; notifications: []; logout: [] }>()
</script>

<style scoped>
.shell-header { display: flex; align-items: center; gap: var(--ct-space-3); min-height: calc(var(--ct-header-height) - 1px); height: calc(var(--ct-header-height) - 1px); padding: 0 var(--ct-space-6); color: var(--ct-text); font-family: var(--ct-font-sans); }
.shell-header__context { display: flex; align-items: center; gap: var(--ct-space-3); min-width: 0; flex: 1 1 auto; }
.shell-header__organization { display: flex; align-items: center; gap: 6px; max-width: 300px; padding: 5px 11px; border: 1px solid var(--ct-border); border-radius: var(--ct-radius-md); background: var(--ct-surface); font-size: 11px; font-weight: 500; line-height: 14px; letter-spacing: 0.22px; }
.shell-header__organization img { width: 12px; height: 12px; flex: 0 0 auto; }
.shell-header__organization span { overflow: hidden; white-space: nowrap; text-overflow: ellipsis; }
.shell-header__location { min-width: 0; color: var(--ct-muted); }
.shell-header__location strong { display: block; overflow: hidden; white-space: nowrap; text-overflow: ellipsis; font-size: 12px; line-height: 16px; font-weight: 400; }
.shell-header__eyebrow { color: var(--ct-muted); font: 10px/14px var(--ct-font-serif); text-transform: uppercase; letter-spacing: 0.55px; }
.shell-header__actions { display: flex; align-items: center; gap: var(--ct-space-3); flex: 0 0 auto; }
.shell-header__connection { display: flex; align-items: center; gap: var(--ct-space-1); padding: 5px 11px; border: 1px solid var(--ct-border); border-radius: var(--ct-radius-sm); background: var(--ct-nav); color: var(--ct-secondary); font: 11px/16px var(--ct-font-serif); white-space: nowrap; }
.shell-header__status-dot { width: 6px; height: 6px; border-radius: 50%; background: var(--ct-mint); }
.shell-header__connection--offline .shell-header__status-dot { background: var(--ct-danger); }
.shell-header__pending { margin-left: 4px; color: var(--ct-muted); }
.shell-header__icon-button { width: 32px; height: 32px; min-height: 32px; min-width: 32px; border-radius: var(--ct-radius-md); color: var(--ct-secondary); }
.shell-header__quick-action { color: var(--ct-mint); }
.shell-header__notifications { margin-left: 1px; }
.shell-header__notifications img { width: 14px; height: 17px; }
.shell-header__notification-dot { position: absolute; top: 4px; right: 4px; width: 8px; height: 8px; border: 1px solid var(--ct-bg); border-radius: 50%; background: var(--ct-danger); }
.shell-header__account { min-height: 32px; min-width: 0; padding: 0 0 0 var(--ct-space-2); border-left: 1px solid var(--ct-border); border-radius: 0; }
.shell-header__account :deep(.q-btn__content) { flex-wrap: nowrap; gap: var(--ct-space-2); }
.shell-header__user-copy { display: flex; flex-direction: column; align-items: flex-end; gap: 2px; max-width: 160px; }
.shell-header__user-copy strong { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; max-width: 100%; font-size: 12px; line-height: 12px; letter-spacing: 0.12px; font-weight: 500; }
.shell-header__user-copy > span { color: var(--ct-muted); font-size: 10px; line-height: 13px; }
.shell-header__avatar { background: var(--ct-mint); border-radius: 12px; }
.shell-header__avatar :deep(img) { width: 12px; height: 12px; border-radius: 0; }
@media (min-width: 768px) and (max-width: 1199px) { .shell-header__location { display: none; } .shell-header { padding-inline: var(--ct-space-4); } }
@media (max-width: 767px) {
  .shell-header { padding-inline: var(--ct-space-2); gap: var(--ct-space-2); }
  .shell-header__actions { gap: var(--ct-space-1); }
  .shell-header__location strong { color: var(--ct-text); font-weight: 500; }
  .shell-header__icon-button { width: 44px; height: 44px; min-height: 44px; min-width: 44px; }
}
@media (max-width: 767px) and (orientation: landscape) { .shell-header { height: 47px; min-height: 47px; } }
</style>
