<script setup lang="ts">
/**
 * Retour du consentement Google : le navigateur arrive ici avec ?code&state.
 * On finalise côté API (le client_secret n'existe que là-bas) puis direction
 * les Réglages. En cas d'échec : Réglages aussi, avec l'erreur affichée.
 */
const { t } = useI18n()
const route = useRoute()
const mailbox = useMailbox()
const toast = useToast()

const failed = ref(false)

onMounted(async () => {
  const code = typeof route.query.code === 'string' ? route.query.code : ''
  const state = typeof route.query.state === 'string' ? route.query.state : ''
  if (!code || !state) {
    failed.value = true
    return
  }
  try {
    await mailbox.connect(code, state)
    toast.add({ title: t('mailbox.toasts.connected'), color: 'success' })
    await navigateTo('/settings')
  }
  catch {
    failed.value = true
  }
})
</script>

<template>
  <UContainer class="py-16 max-w-md text-center">
    <template v-if="!failed">
      <UIcon name="i-lucide-loader-circle" class="animate-spin text-primary" aria-hidden="true" />
      <p class="mt-3 text-muted">{{ t('mailbox.callback.connecting') }}</p>
    </template>
    <template v-else>
      <UAlert color="error" variant="soft" icon="i-lucide-alert-triangle" :title="t('mailbox.callback.failed')" />
      <UButton class="mt-4" variant="outline" to="/settings">{{ t('mailbox.callback.backToSettings') }}</UButton>
    </template>
  </UContainer>
</template>
