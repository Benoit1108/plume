<script setup lang="ts">
/** Tableau de bord — activité hebdomadaire : barres maison + ligne d'objectif. */
defineProps<{
  weeks: { weekStart: string, acts: number }[]
  target: number
  barHeightPercent: (acts: number) => number
  goalLinePercent: number
}>()

const { t, locale } = useI18n()
function weekLabel(weekStart: string): string {
  return new Date(`${weekStart}T00:00:00`).toLocaleDateString(locale.value, { day: 'numeric', month: 'short' })
}
</script>

<template>
  <section class="mt-8 border border-default rounded-xl p-4 bg-elevated/40">
    <div class="flex items-baseline gap-3 flex-wrap">
      <p class="text-sm font-semibold">{{ t('dashboard.weekly.title') }}</p>
      <p class="text-xs text-dimmed">{{ t('dashboard.weekly.goalLine', { goal: target }) }}</p>
    </div>
    <div class="mt-4 relative">
      <!-- Zone des barres = h-24 en haut de chaque colonne : l'overlay s'y superpose. -->
      <div class="absolute inset-x-0 top-0 h-24 pointer-events-none" aria-hidden="true">
        <div class="absolute inset-x-0 border-t border-dashed border-primary/60" :style="{ bottom: `${goalLinePercent}%` }" />
      </div>
      <ol class="grid grid-cols-8 gap-1 sm:gap-2 items-end">
        <li v-for="(week, i) in weeks" :key="week.weekStart" class="flex flex-col items-center gap-1">
          <div class="h-24 w-full flex items-end">
            <div
              class="w-full rounded-t-sm min-h-0.5 grow-y"
              :class="week.acts >= target ? 'bg-primary' : 'bg-primary/35'"
              :style="{ height: `${barHeightPercent(week.acts)}%`, animationDelay: `${i * 0.05}s` }"
              role="img"
              :aria-label="`${t('dashboard.weekly.weekOf', { date: weekLabel(week.weekStart) })} : ${t('dashboard.weekly.acts', { count: week.acts }, week.acts)}`"
            />
          </div>
          <span class="font-mono tabular-nums text-xs">{{ week.acts }}</span>
          <span class="text-[10px] text-dimmed whitespace-nowrap">{{ weekLabel(week.weekStart) }}</span>
        </li>
      </ol>
    </div>
  </section>
</template>
