<script setup lang="ts">
import type { OrganizationInput } from '~/types/directory'

const directory = useDirectory()
const saving = ref(false)
const error = ref('')

async function onSubmit(payload: OrganizationInput): Promise<void> {
  error.value = ''
  saving.value = true
  try {
    const org = await directory.create(payload)
    await navigateTo(`/organizations/${org.id}`)
  }
  catch {
    error.value = 'Création impossible. Vérifie les champs (le nom est requis).'
  }
  finally {
    saving.value = false
  }
}
</script>

<template>
  <UContainer class="py-8 max-w-2xl">
    <UButton variant="link" icon="i-lucide-arrow-left" to="/organizations" class="px-0 mb-2">Répertoire</UButton>
    <h1 class="font-serif text-3xl font-semibold mb-6">Nouvelle organisation</h1>

    <UAlert v-if="error" color="error" variant="subtle" :description="error" class="mb-4" />

    <OrgForm
      :submitting="saving"
      submit-label="Créer"
      @submit="onSubmit"
      @cancel="navigateTo('/organizations')"
    />
  </UContainer>
</template>
