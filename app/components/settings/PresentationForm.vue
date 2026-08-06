<script setup lang="ts">
/**
 * Onglet « Profil » : la présentation (bio, spécialités, signature) — matière première de la
 * rédaction assistée. Autonome : sa lecture, son enregistrement (PATCH partiel).
 */
const { t } = useI18n()
const { profile, loading, saving, save } = useProfileSave()

const bio = ref('')
const specialties = ref('')
const signature = ref('')

watch(profile, (value) => {
  if (!value) return
  bio.value = value.bio ?? ''
  specialties.value = value.specialties ?? ''
  signature.value = value.signature ?? ''
}, { immediate: true })

async function submit(): Promise<void> {
  await save({
    bio: bio.value.trim() || null,
    specialties: specialties.value.trim() || null,
    signature: signature.value.trim() || null,
  })
}
</script>

<template>
  <div v-if="loading" role="status" class="flex flex-col gap-4 max-w-2xl">
    <span class="sr-only">{{ t('common.loading') }}</span>
    <USkeleton class="h-72 rounded-xl" />
  </div>

  <form v-else class="flex flex-col gap-6 max-w-2xl" @submit.prevent="submit">
    <section class="border border-default rounded-xl p-4 bg-elevated/40 flex flex-col gap-4">
      <div>
        <h2 class="text-sm font-semibold">{{ t('settings.presentation.title') }}</h2>
        <p class="text-xs text-muted mt-1">{{ t('settings.presentation.intro') }}</p>
      </div>
      <UFormField :label="t('settings.presentation.bioLabel')" :hint="t('settings.presentation.bioHint')">
        <UTextarea v-model="bio" :rows="4" autoresize class="w-full" maxlength="2000" />
      </UFormField>
      <UFormField :label="t('settings.presentation.specialtiesLabel')" :hint="t('settings.presentation.specialtiesHint')">
        <UTextarea v-model="specialties" :rows="3" autoresize class="w-full" maxlength="1000" />
      </UFormField>
      <UFormField :label="t('settings.presentation.signatureLabel')" :hint="t('settings.presentation.signatureHint')">
        <UTextarea v-model="signature" :rows="3" autoresize class="w-full" maxlength="500" />
      </UFormField>
    </section>

    <div class="flex justify-end">
      <UButton type="submit" :loading="saving">{{ t('actions.save') }}</UButton>
    </div>
  </form>
</template>
