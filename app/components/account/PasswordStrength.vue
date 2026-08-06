<script setup lang="ts">
/**
 * Exigences du mot de passe, cochées EN DIRECT, plus un indice de robustesse.
 *
 * Sans ça, l'utilisatrice découvre les règles au moment du refus — et doit deviner laquelle a
 * manqué. Les règles (binaires) et la robustesse (indicative) sont séparées visuellement : une
 * barre verte sur un mot de passe refusé serait un mensonge.
 */
const props = defineProps<{ password: string }>()

const { t } = useI18n()
const assessment = computed(() => assessPassword(props.password))

const STRENGTH = [
  { label: 'weak', class: 'bg-error' },
  { label: 'weak', class: 'bg-error' },
  { label: 'fair', class: 'bg-warning' },
  { label: 'good', class: 'bg-info' },
  { label: 'strong', class: 'bg-success' },
] as const

const strength = computed(() => STRENGTH[assessment.value.score] ?? STRENGTH[0])
</script>

<template>
  <div v-if="password !== ''" class="flex flex-col gap-2">
    <!-- Robustesse : indicatif, jamais bloquant. `aria-hidden` sur les segments — l'information
         est donnée en toutes lettres juste à côté, inutile de faire lire quatre barres. -->
    <div class="flex items-center gap-2">
      <div class="flex gap-1 flex-1" aria-hidden="true">
        <div
          v-for="step in 4"
          :key="step"
          class="h-1 flex-1 rounded-full motion-safe:transition-colors"
          :class="step <= assessment.score ? strength.class : 'bg-elevated'"
        />
      </div>
      <span class="text-xs text-muted shrink-0">{{ t(`account.password.strength.${strength.label}`) }}</span>
    </div>

    <!-- Les exigences, elles, sont annoncées : c'est ce qui autorise (ou non) l'envoi. -->
    <ul class="flex flex-col gap-0.5" :aria-label="t('account.password.requirements')">
      <li
        v-for="rule in PASSWORD_RULES"
        :key="rule"
        class="text-xs flex items-center gap-1.5"
        :class="assessment.rules[rule] ? 'text-success' : 'text-muted'"
      >
        <UIcon
          :name="assessment.rules[rule] ? 'i-lucide-check' : 'i-lucide-circle'"
          class="size-3 shrink-0"
          aria-hidden="true"
        />
        <span>{{ t(`account.password.rules.${rule}`, { count: PASSWORD_MIN_LENGTH }) }}</span>
        <span class="sr-only">{{ assessment.rules[rule] ? t('account.password.ruleMet') : t('account.password.ruleUnmet') }}</span>
      </li>
    </ul>
  </div>
</template>
