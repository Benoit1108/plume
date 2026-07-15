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
  <PageContainer width="form">
    <PageHeader back-to="/organizations" :back-label="t('directory.title')" :title="t('directory.new.title')" />

    <UAlert v-if="error" color="error" variant="subtle" :description="error" class="mb-4" />

    <OrgForm
      :submitting="saving"
      :submit-label="t('actions.create')"
      @submit="onSubmit"
      @cancel="navigateTo('/organizations')"
    />
  </PageContainer>
</template>
