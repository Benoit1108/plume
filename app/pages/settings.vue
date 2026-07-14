<script setup lang="ts">
import type { Profile } from '~/types/leads'

const { t } = useI18n()
const profileApi = useProfile()
const toast = useToast()

const { data: profile, refresh, status } = await useAsyncData<Profile | null>(
  'profile-settings',
  () => profileApi.get(),
  { server: false, default: () => null },
)
const loading = computed(() => status.value === 'idle' || status.value === 'pending')

const weeklyGoal = ref(5)
const bio = ref('')
const specialties = ref('')
const signature = ref('')

watch(profile, (value) => {
  if (!value) return
  weeklyGoal.value = value.weeklyGoal
  bio.value = value.bio ?? ''
  specialties.value = value.specialties ?? ''
  signature.value = value.signature ?? ''
}, { immediate: true })

const saving = ref(false)

async function save(): Promise<void> {
  saving.value = true
  try {
    await profileApi.update({
      weeklyGoal: weeklyGoal.value,
      bio: bio.value.trim() || null,
      specialties: specialties.value.trim() || null,
      signature: signature.value.trim() || null,
    })
    await refresh()
    toast.add({ title: t('settings.toasts.saved'), color: 'success' })
  }
  catch {
    toast.add({ title: t('common.error'), color: 'error' })
  }
  finally {
    saving.value = false
  }
}
</script>

<template>
  <UContainer class="py-8 max-w-2xl">
    <p class="text-[11px] uppercase tracking-widest text-dimmed font-semibold">{{ t('settings.eyebrow') }}</p>
    <h1 class="font-serif text-3xl font-semibold mt-1">{{ t('settings.title') }}</h1>

    <div v-if="loading" class="py-12 text-center text-dimmed">{{ t('common.loading') }}</div>

    <form v-else class="mt-6 flex flex-col gap-8" @submit.prevent="save">
      <!-- Objectif hebdomadaire -->
      <section class="border border-default rounded-xl p-4 bg-elevated/40">
        <p class="text-sm font-semibold">{{ t('settings.goal.title') }}</p>
        <UFormField :label="t('settings.goal.label')" :hint="t('settings.goal.hint')" class="mt-3">
          <UInput v-model.number="weeklyGoal" type="number" min="1" max="99" class="w-32" />
        </UFormField>
      </section>

      <!-- Présentation (matière première de la rédaction assistée) -->
      <section class="border border-default rounded-xl p-4 bg-elevated/40 flex flex-col gap-4">
        <div>
          <p class="text-sm font-semibold">{{ t('settings.presentation.title') }}</p>
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
  </UContainer>
</template>
