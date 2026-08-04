<script setup lang="ts">
import type { Profile } from '~/types/domain/leads'

/**
 * Checklist « Bien démarrer » (onboarding V2.1) sur Aujourd'hui. Les étapes sont dérivées des
 * données réelles ; une fois tout complété (ou masquée), mémorisé en localStorage par compte —
 * les requêtes sont alors coupées (`enabled: false`) : coût nul pour un compte installé.
 */
const { t } = useI18n()
const auth = useAuthStore()
const mailboxApi = useMailbox()
const sourcingApi = useSourcing()
const directoryApi = useDirectory()
const profileApi = useProfile()

const storageKey = computed(() => onboardingStorageKey(auth.email ?? ''))
const dismissed = ref(true) // fermé par défaut : on n'affiche qu'après lecture du localStorage
onMounted(() => {
  dismissed.value = localStorage.getItem(storageKey.value) !== null
})

const enabled = computed(() => !dismissed.value)

// Le profil est déjà chargé par la page (même clé → cache partagé, pas de requête en plus).
const { data: profile } = useQuery({ queryKey: queryKeys.profile, queryFn: () => profileApi.get(), enabled })
const { data: mailbox } = useQuery({ queryKey: queryKeys.mailbox, queryFn: () => mailboxApi.get(), enabled })
const { data: feeds } = useQuery({ queryKey: queryKeys.feeds, queryFn: () => sourcingApi.feeds(), enabled })
const { data: organizations } = useQuery({ queryKey: queryKeys.organizations, queryFn: () => directoryApi.list(), enabled })

const loaded = computed(() =>
  profile.value !== undefined && mailbox.value !== undefined
  && feeds.value !== undefined && organizations.value !== undefined)

const steps = computed(() => computeOnboardingSteps({
  profile: (profile.value ?? null) as Pick<Profile, 'bio' | 'specialties' | 'signature'> | null,
  mailboxStatus: mailbox.value?.status ?? null,
  feedCount: feeds.value?.length ?? 0,
  organizationCount: organizations.value?.length ?? 0,
}))

const doneCount = computed(() => steps.value.filter(s => s.done).length)
const allDone = computed(() => loaded.value && isOnboardingComplete(steps.value))

// Tout est fait : on le mémorise (la carte reste visible cette session — satisfaction des coches —
// et ne réapparaîtra plus ensuite, requêtes comprises).
watch(allDone, (done) => {
  if (done) localStorage.setItem(storageKey.value, new Date().toISOString())
})

function dismiss(): void {
  localStorage.setItem(storageKey.value, new Date().toISOString())
  dismissed.value = true
}
</script>

<template>
  <section
    v-if="!dismissed && loaded && !allDone"
    class="mt-6 border border-primary/30 rounded-xl p-4 bg-primary/5"
    :aria-label="t('onboarding.title')"
  >
    <div class="flex items-start justify-between gap-3">
      <div>
        <p class="text-sm font-semibold">{{ t('onboarding.title') }}</p>
        <p class="text-xs text-muted mt-0.5">{{ t('onboarding.intro', { done: doneCount, total: steps.length }) }}</p>
      </div>
      <UButton size="xs" color="neutral" variant="ghost" icon="i-lucide-x" :aria-label="t('onboarding.dismiss')" @click="dismiss" />
    </div>

    <ul class="mt-3 flex flex-col gap-2">
      <li v-for="step in steps" :key="step.id" class="flex items-center gap-2.5">
        <UIcon
          :name="step.done ? 'i-lucide-circle-check' : 'i-lucide-circle'"
          :class="step.done ? 'text-primary' : 'text-muted'"
          aria-hidden="true"
        />
        <span class="text-sm" :class="step.done ? 'line-through text-muted' : ''">
          {{ t(`onboarding.steps.${step.id}`) }}
        </span>
        <UButton
          v-if="!step.done"
          :to="step.to"
          size="xs"
          variant="soft"
          class="ml-auto"
        >
          {{ t(`onboarding.actions.${step.id}`) }}
        </UButton>
      </li>
    </ul>
  </section>
</template>
