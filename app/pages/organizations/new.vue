<script setup lang="ts">
import type { OrganizationInput } from '~/types/directory'

const { t } = useI18n()
const directory = useDirectory()
const toast = useToast()

const saving = ref(false)
const error = ref('')

async function onSubmit(payload: OrganizationInput): Promise<void> {
  error.value = ''
  saving.value = true
  try {
    const org = await directory.create(payload)
    toast.add({ title: t('directory.toasts.created'), color: 'success' })
    await navigateTo(`/organizations/${org.id}`)
  }
  catch {
    error.value = t('directory.new.error')
  }
  finally {
    saving.value = false
  }
}
</script>

<template>
  <UContainer class="py-8 max-w-2xl">
    <UButton variant="link" icon="i-lucide-arrow-left" to="/organizations" class="px-0 mb-2">
      {{ t('directory.title') }}
    </UButton>
    <h1 class="font-serif text-3xl font-semibold mb-6">{{ t('directory.new.title') }}</h1>

    <UAlert v-if="error" color="error" variant="subtle" :description="error" class="mb-4" />

    <OrgForm
      :submitting="saving"
      :submit-label="t('actions.create')"
      @submit="onSubmit"
      @cancel="navigateTo('/organizations')"
    />
  </UContainer>
</template>
