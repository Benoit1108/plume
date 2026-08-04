<script setup lang="ts">
import type { LeadStatus } from '~/types/domain/leads'

/** Tableau de bord — pipeline : barre segmentée + légende (valeurs en texte pour l'a11y). */
const props = defineProps<{ pipeline: { status: LeadStatus, count: number }[], total: number }>()

const { t } = useI18n()
const { statusLabel } = useLeadLabels()

/** Teintes par statut — mêmes familles que les badges du kanban. */
const STATUS_TINTS: Record<LeadStatus, string> = {
  TO_CONTACT: 'bg-neutral-400 dark:bg-neutral-500',
  CONTACTED: 'bg-primary/70',
  FOLLOWED_UP: 'bg-primary',
  IN_DISCUSSION: 'bg-info-500',
  SAMPLE_TEST: 'bg-warning-500',
  PAUSED: 'bg-neutral-300 dark:bg-neutral-600',
  WON: 'bg-success-500',
  LOST: 'bg-error-400',
}
</script>

<template>
  <section v-if="pipeline.length" class="mt-8 border border-default rounded-xl p-4 bg-elevated/40">
    <p class="text-sm font-semibold">{{ t('dashboard.pipeline.title') }}</p>
    <div
      class="mt-3 flex h-3 rounded-full overflow-hidden"
      role="img"
      :aria-label="pipeline.map(slice => `${statusLabel(slice.status)} : ${slice.count}`).join(', ')"
    >
      <div
        v-for="(slice, i) in pipeline"
        :key="slice.status"
        class="grow-x"
        :class="STATUS_TINTS[slice.status]"
        :style="{ width: `${(slice.count / Math.max(1, props.total)) * 100}%`, animationDelay: `${i * 0.05}s` }"
      />
    </div>
    <ul class="mt-3 flex flex-wrap gap-x-4 gap-y-1 text-xs">
      <li v-for="slice in pipeline" :key="slice.status" class="flex items-center gap-1.5">
        <span class="size-2 rounded-full inline-block" :class="STATUS_TINTS[slice.status]" aria-hidden="true" />
        <span class="text-muted">{{ statusLabel(slice.status) }}</span>
        <span class="font-mono tabular-nums">{{ slice.count }}</span>
      </li>
    </ul>
  </section>
</template>
