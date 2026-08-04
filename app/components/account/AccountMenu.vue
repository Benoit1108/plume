<script setup lang="ts">
import type { DropdownMenuItem } from '@nuxt/ui'

// Menu compte de l'en-tête (à côté de la cloche) : évite un onglet dédié dans la barre latérale
// pour un usage rare. Regroupe l'accès à la page Compte et la déconnexion.
const { t } = useI18n()
const auth = useAuthStore()

const initials = computed(() => {
  const local = (auth.email || '').split('@')[0] || ''
  return local.slice(0, 2).toUpperCase() || '?'
})

const items = computed<DropdownMenuItem[][]>(() => [
  [{ type: 'label', label: auth.email || '' }],
  [{ label: t('nav.account'), icon: 'i-lucide-circle-user', to: '/account' }],
  [{ label: t('auth.logout'), icon: 'i-lucide-log-out', color: 'error', onSelect: () => auth.logout() }],
])
</script>

<template>
  <UDropdownMenu :items="items" :ui="{ content: 'w-56' }">
    <UButton color="neutral" variant="ghost" size="sm" square :aria-label="t('nav.account')">
      <UAvatar :text="initials" size="2xs" />
    </UButton>
  </UDropdownMenu>
</template>
