<script setup lang="ts">
/**
 * Onglet « Prospection » : ce qui pilote le rythme de travail — objectif hebdomadaire, séquence de
 * relance, seuil de réactivation des clients dormants, libellés d'étapes du pipeline.
 * Autonome : sa lecture, son enregistrement (PATCH partiel).
 */
const { t } = useI18n()
const { profile, loading, saving, save } = useProfileSave()

const weeklyGoal = ref(5)
/** Seuil de dormance des clients gagnés en jours (0 = réactivation désactivée). */
const dormantThreshold = ref(120)
/** Saisie libre « 7, 21, 45 » ; parsée en jours à l'enregistrement. */
const cadenceInput = ref('')
/** Libellés d'étapes personnalisés (ADR-0031) : un champ par statut, vide = libellé par défaut. */
const pipelineLabels = ref<Record<string, string>>({})

watch(profile, (value) => {
  if (!value) return
  weeklyGoal.value = value.weeklyGoal
  dormantThreshold.value = value.dormantClientThresholdDays
  cadenceInput.value = value.followUpCadence.join(', ')
  pipelineLabels.value = Object.fromEntries(LEAD_STATUSES.map(s => [s, value.pipelineLabels[s] ?? '']))
}, { immediate: true })

/** « 7, 21, 45 » → [7, 21, 45] (entiers positifs uniquement ; vide = pas de relance auto). */
const parsedCadence = computed<number[]>(() =>
  cadenceInput.value
    .split(/[\s,]+/)
    .map(part => Number.parseInt(part, 10))
    .filter(n => Number.isInteger(n) && n >= 1 && n <= 365),
)

/** v-model.number émet '' quand le champ est vidé : on n'envoie jamais un PATCH invalide. */
const goalValid = computed(() => Number.isInteger(weeklyGoal.value) && weeklyGoal.value >= 1 && weeklyGoal.value <= 99)

async function submit(): Promise<void> {
  await save({
    weeklyGoal: weeklyGoal.value,
    dormantClientThresholdDays: Number.isInteger(dormantThreshold.value) ? dormantThreshold.value : 120,
    followUpCadence: parsedCadence.value,
    pipelineLabels: Object.fromEntries(Object.entries(pipelineLabels.value).filter(([, v]) => v.trim() !== '')),
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

    <div class="flex justify-end">
      <UButton type="submit" :loading="saving" :disabled="!goalValid">{{ t('actions.save') }}</UButton>
    </div>
  </form>
</template>
