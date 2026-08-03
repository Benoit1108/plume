<script setup lang="ts">
import type { Profile } from '~/types/leads'

/** Réglages du profil (objectif, cadence, digest, préférences de notif, libellés pipeline, présentation). */
const { t } = useI18n()
const profileApi = useProfile()
const toast = useToast()
const queryClient = useQueryClient()

const { data: profileData, isPending: loading } = useQuery({ queryKey: queryKeys.profile, queryFn: () => profileApi.get() })
const profile = computed<Profile | null>(() => profileData.value ?? null)

const weeklyGoal = ref(5)
const bio = ref('')
const specialties = ref('')
const signature = ref('')
const digestFrequency = ref<'NONE' | 'DAILY' | 'WEEKLY'>('DAILY')
/** Seuil de dormance des clients gagnés en jours (0 = réactivation désactivée). */
const dormantThreshold = ref(120)
/** Saisie libre « 7, 21, 45 » ; parsée en jours à l'enregistrement. */
const cadenceInput = ref('')
/** Libellés d'étapes personnalisés (ADR-0031) : un champ par statut, vide = libellé par défaut. */
const pipelineLabels = ref<Record<string, string>>({})
/** Préférences fines de notification : matrice type × canal (défaut = tout activé). */
const NOTIFICATION_TYPES = ['reply_received', 'followup_due', 'candidate_to_triage', 'client_dormant', 'email_send_failed', 'mailbox_disconnected'] as const
const notificationPrefs = ref<Record<string, { inApp: boolean, email: boolean }>>({})

watch(profile, (value) => {
  if (!value) return
  weeklyGoal.value = value.weeklyGoal
  bio.value = value.bio ?? ''
  specialties.value = value.specialties ?? ''
  signature.value = value.signature ?? ''
  digestFrequency.value = value.digestFrequency
  dormantThreshold.value = value.dormantClientThresholdDays
  cadenceInput.value = value.followUpCadence.join(', ')
  pipelineLabels.value = Object.fromEntries(LEAD_STATUSES.map(s => [s, value.pipelineLabels[s] ?? '']))
  // Défaut = tout activé : on fusionne les coupures stockées avec les valeurs par défaut (vraies).
  notificationPrefs.value = Object.fromEntries(NOTIFICATION_TYPES.map(type => [type, {
    inApp: value.notificationPreferences[type]?.inApp ?? true,
    email: value.notificationPreferences[type]?.email ?? true,
  }]))
}, { immediate: true })

/** « 7, 21, 45 » → [7, 21, 45] (entiers positifs uniquement ; vide = pas de relance auto). */
const parsedCadence = computed<number[]>(() =>
  cadenceInput.value
    .split(/[\s,]+/)
    .map(part => Number.parseInt(part, 10))
    .filter(n => Number.isInteger(n) && n >= 1 && n <= 365),
)

const digestOptions = computed(() => [
  { value: 'DAILY', label: t('settings.digest.daily') },
  { value: 'WEEKLY', label: t('settings.digest.weekly') },
  { value: 'NONE', label: t('settings.digest.none') },
])

const saving = ref(false)
/** v-model.number émet '' quand le champ est vidé : on n'envoie jamais un PATCH invalide. */
const goalValid = computed(() => Number.isInteger(weeklyGoal.value) && weeklyGoal.value >= 1 && weeklyGoal.value <= 99)

async function save(): Promise<void> {
  saving.value = true
  try {
    await profileApi.update({
      weeklyGoal: weeklyGoal.value,
      bio: bio.value.trim() || null,
      specialties: specialties.value.trim() || null,
      signature: signature.value.trim() || null,
      digestFrequency: digestFrequency.value,
      dormantClientThresholdDays: Number.isInteger(dormantThreshold.value) ? dormantThreshold.value : 120,
      followUpCadence: parsedCadence.value,
      pipelineLabels: Object.fromEntries(Object.entries(pipelineLabels.value).filter(([, v]) => v.trim() !== '')),
      notificationPreferences: notificationPrefs.value,
    })
    await queryClient.invalidateQueries({ queryKey: queryKeys.profile })
    toast.add({ title: t('settings.toasts.saved'), color: 'success' })
  }
  catch (error) {
    toast.add({ title: errorToastTitle(t, error), color: 'error' })
  }
  finally {
    saving.value = false
  }
}
</script>

<template>
  <div v-if="loading" role="status" class="mt-6 flex flex-col gap-4 max-w-2xl">
    <span class="sr-only">{{ t('common.loading') }}</span>
    <USkeleton class="h-24 rounded-xl" />
    <USkeleton class="h-72 rounded-xl" />
  </div>

  <form v-else class="mt-6 flex flex-col gap-8 max-w-2xl" @submit.prevent="save">
    <!-- Objectif hebdomadaire -->
    <section class="border border-default rounded-xl p-4 bg-elevated/40">
      <h2 class="text-sm font-semibold">{{ t('settings.goal.title') }}</h2>
      <UFormField :label="t('settings.goal.label')" :hint="t('settings.goal.hint')" class="mt-3">
        <UInput v-model.number="weeklyGoal" type="number" min="1" max="99" class="w-32" />
      </UFormField>
    </section>

    <!-- Séquence de relance -->
    <section class="border border-default rounded-xl p-4 bg-elevated/40">
      <h2 class="text-sm font-semibold">{{ t('settings.cadence.title') }}</h2>
      <UFormField :label="t('settings.cadence.label')" :hint="t('settings.cadence.hint')" class="mt-3">
        <UInput v-model="cadenceInput" placeholder="7, 21, 45" class="w-64" />
      </UFormField>
      <p class="text-xs text-muted mt-2">
        {{ parsedCadence.length === 0 ? t('settings.cadence.none') : t('settings.cadence.preview', { days: parsedCadence.join(' · J+') }) }}
      </p>
    </section>

    <!-- Réactivation des clients dormants (V2.4) -->
    <section class="border border-default rounded-xl p-4 bg-elevated/40">
      <h2 class="text-sm font-semibold">{{ t('settings.dormant.title') }}</h2>
      <UFormField :label="t('settings.dormant.label')" :hint="t('settings.dormant.hint')" class="mt-3">
        <UInput v-model.number="dormantThreshold" type="number" min="0" max="730" class="w-32" />
      </UFormField>
      <p class="text-xs text-muted mt-2">
        {{ dormantThreshold === 0 ? t('settings.dormant.disabled') : t('settings.dormant.preview', { days: dormantThreshold }) }}
      </p>
    </section>

    <!-- Digest email des notifications -->
    <section class="border border-default rounded-xl p-4 bg-elevated/40">
      <h2 class="text-sm font-semibold">{{ t('settings.digest.title') }}</h2>
      <UFormField :label="t('settings.digest.label')" :hint="t('settings.digest.hint')" class="mt-3">
        <USelect v-model="digestFrequency" :items="digestOptions" value-key="value" class="w-56" />
      </UFormField>
    </section>

    <!-- Préférences fines de notification : matrice type × canal (in-app / email), défaut = tout activé -->
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

    <!-- Libellés d'étapes du pipeline (ADR-0031 : cosmétique, la logique ne change pas) -->
    <section class="border border-default rounded-xl p-4 bg-elevated/40">
      <h2 class="text-sm font-semibold">{{ t('settings.pipeline.title') }}</h2>
      <p class="text-xs text-muted mt-1">{{ t('settings.pipeline.hint') }}</p>
      <div class="mt-3 grid grid-cols-1 sm:grid-cols-2 gap-3">
        <UFormField v-for="status in LEAD_STATUSES" :key="status" :label="t(`pipeline.statuses.${status}`)">
          <UInput v-model="pipelineLabels[status]" :placeholder="t(`pipeline.statuses.${status}`)" maxlength="40" class="w-full" />
        </UFormField>
      </div>
    </section>

    <!-- Présentation (matière première de la rédaction assistée) -->
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
      <UButton type="submit" :loading="saving" :disabled="!goalValid">{{ t('actions.save') }}</UButton>
    </div>
  </form>
</template>
