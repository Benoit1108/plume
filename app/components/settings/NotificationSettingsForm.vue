<script setup lang="ts">
/**
 * Onglet « Notifications » : ce qui arrive par email (digest, bilan hebdomadaire) et la matrice
 * fine type × canal. Autonome : sa lecture, son enregistrement (PATCH partiel).
 */
const { t } = useI18n()
const { profile, loading, saving, save } = useProfileSave()

const digestFrequency = ref<'NONE' | 'DAILY' | 'WEEKLY'>('DAILY')
/** Bilan hebdomadaire par email (opt-out). */
const weeklyReport = ref(true)
/** Préférences fines : matrice type × canal (défaut = tout activé). */
const NOTIFICATION_TYPES = ['reply_received', 'followup_due', 'candidate_to_triage', 'client_dormant', 'email_send_failed', 'mailbox_disconnected'] as const
const notificationPrefs = ref<Record<string, { inApp: boolean, email: boolean }>>({})

watch(profile, (value) => {
  if (!value) return
  digestFrequency.value = value.digestFrequency
  weeklyReport.value = value.weeklyReportEnabled
  // Défaut = tout activé : on fusionne les coupures stockées avec les valeurs par défaut (vraies).
  notificationPrefs.value = Object.fromEntries(NOTIFICATION_TYPES.map(type => [type, {
    inApp: value.notificationPreferences[type]?.inApp ?? true,
    email: value.notificationPreferences[type]?.email ?? true,
  }]))
}, { immediate: true })

const digestOptions = computed(() => [
  { value: 'DAILY', label: t('settings.digest.daily') },
  { value: 'WEEKLY', label: t('settings.digest.weekly') },
  { value: 'NONE', label: t('settings.digest.none') },
])

async function submit(): Promise<void> {
  await save({
    digestFrequency: digestFrequency.value,
    weeklyReportEnabled: weeklyReport.value,
    notificationPreferences: notificationPrefs.value,
  })
}
</script>

<template>
  <div v-if="loading" role="status" class="flex flex-col gap-4 max-w-2xl">
    <span class="sr-only">{{ t('common.loading') }}</span>
    <USkeleton class="h-24 rounded-xl" />
    <USkeleton class="h-72 rounded-xl" />
  </div>

  <form v-else class="flex flex-col gap-6 max-w-2xl" @submit.prevent="submit">
    <!-- Digest email des notifications + bilan hebdomadaire -->
    <section class="border border-default rounded-xl p-4 bg-elevated/40">
      <h2 class="text-sm font-semibold">{{ t('settings.digest.title') }}</h2>
      <UFormField :label="t('settings.digest.label')" :hint="t('settings.digest.hint')" class="mt-3">
        <USelect v-model="digestFrequency" :items="digestOptions" value-key="value" class="w-56" />
      </UFormField>
      <UCheckbox v-model="weeklyReport" :label="t('settings.weeklyReport.label')" class="mt-4" />
      <p class="text-xs text-muted mt-1">{{ t('settings.weeklyReport.hint') }}</p>
    </section>

    <!-- Matrice type × canal (in-app / email), défaut = tout activé -->
    <section class="border border-default rounded-xl p-4 bg-elevated/40">
      <h2 class="text-sm font-semibold">{{ t('settings.notifications.title') }}</h2>
      <p class="text-xs text-muted mt-1">{{ t('settings.notifications.hint') }}</p>
      <div class="mt-3 overflow-x-auto">
        <table class="w-full text-sm">
          <thead>
            <tr class="text-left text-xs text-dimmed uppercase tracking-wide">
              <th scope="col" class="py-1.5 pr-3 font-semibold">{{ t('settings.notifications.type') }}</th>
              <th scope="col" class="py-1.5 px-3 font-semibold text-center">{{ t('settings.notifications.inApp') }}</th>
              <th scope="col" class="py-1.5 pl-3 font-semibold text-center">{{ t('settings.notifications.email') }}</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-[var(--ui-border)]">
            <tr v-for="type in NOTIFICATION_TYPES" :key="type">
              <td class="py-2 pr-3">{{ t(`settings.notifications.types.${type}`) }}</td>
              <td class="py-2 px-3 text-center">
                <UCheckbox
                  v-if="notificationPrefs[type]"
                  v-model="notificationPrefs[type].inApp"
                  :aria-label="t('settings.notifications.inAppFor', { type: t(`settings.notifications.types.${type}`) })"
                />
              </td>
              <td class="py-2 pl-3 text-center">
                <UCheckbox
                  v-if="notificationPrefs[type]"
                  v-model="notificationPrefs[type].email"
                  :aria-label="t('settings.notifications.emailFor', { type: t(`settings.notifications.types.${type}`) })"
                />
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </section>

    <div class="flex justify-end">
      <UButton type="submit" :loading="saving">{{ t('actions.save') }}</UButton>
    </div>
  </form>
</template>
