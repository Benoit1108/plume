<script setup lang="ts">
// Aperçu produit : carrousel de 3 écrans (Aujourd'hui / Pipeline / Tableau de bord). Autonome :
// gère son état, le défilement auto (pause au survol), l'inclinaison 3D, les compteurs animés du
// tableau de bord et la barre d'objectif. Données FICTIVES, purement illustratives.
const { t } = useI18n()

const slides = ['today', 'pipeline', 'dashboard'] as const
const activeSlide = ref(0)
const activeTab = computed(() => t(`landing.showcase.${slides[activeSlide.value]}Tab`))

const todayRows = [
  { name: 'Éditions Margelle', status: 'due', color: 'warning', icon: 'i-lucide-clock' },
  { name: 'Studio Bleu Nuit', status: 'replied', color: 'success', icon: 'i-lucide-mail-check' },
  { name: 'Agence Verba', status: 'dormant', color: 'neutral', icon: 'i-lucide-moon' },
] as const

const pipelineCols: { key: string, count: number, cards: { name: string, meta?: string, metaKey?: string }[] }[] = [
  { key: 'colToContact', count: 4, cards: [{ name: 'Éditions Margelle', metaKey: 'landing.showcase.tagNovel' }, { name: 'Maison Aster', metaKey: 'landing.showcase.tagChildren' }] },
  { key: 'colInTalks', count: 2, cards: [{ name: 'Studio Bleu Nuit', metaKey: 'landing.showcase.tagDubbing' }] },
  { key: 'colWon', count: 3, cards: [{ name: 'Agence Verba', meta: '2 400 €' }] },
]

const kpis: { key: string, target: number, suffix: string, group?: boolean }[] = [
  { key: 'kpiResponse', target: 38, suffix: ' %' },
  { key: 'kpiFollowups', target: 12, suffix: '' },
  { key: 'kpiPipeline', target: 7200, suffix: ' €', group: true },
]
const bars = [40, 65, 52, 80, 70, 95]

// Compteurs animés du tableau de bord (idée 1).
const counts = ref<number[]>(kpis.map(() => 0))
function formatKpi(i: number): string {
  const k = kpis[i]
  if (!k) return ''
  const n = Math.round(counts.value[i] ?? 0)
  return (k.group ? n.toLocaleString('fr-FR') : String(n)) + k.suffix
}
function animateCounters(): void {
  let startTs = 0
  const dur = 900
  const step = (ts: number) => {
    if (!startTs) startTs = ts
    const p = Math.min((ts - startTs) / dur, 1)
    const eased = 1 - (1 - p) ** 3
    counts.value = kpis.map(k => k.target * eased)
    if (p < 1) requestAnimationFrame(step)
  }
  requestAnimationFrame(step)
}

// Barre d'objectif animée (idée 3) : se remplit quand l'écran « Aujourd'hui » est actif.
const mounted = ref(false)
const goalWidth = computed(() => (mounted.value && activeSlide.value === 0) ? '62.5%' : '0%')

watch(activeSlide, (n) => { if (n === 2) animateCounters() })

const showcaseEl = ref<HTMLElement>()
const frameEl = ref<HTMLElement>()
let cleanups: Array<() => void> = []

onMounted(() => {
  const reduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches
  mounted.value = true
  if (reduced) counts.value = kpis.map(k => k.target)

  if (!reduced && showcaseEl.value && frameEl.value) {
    const sc = showcaseEl.value
    const fr = frameEl.value
    const onFrame = (e: MouseEvent) => {
      const r = fr.getBoundingClientRect()
      const px = (e.clientX - r.left) / r.width - 0.5
      const py = (e.clientY - r.top) / r.height - 0.5
      fr.style.transform = `rotateY(${px * 6}deg) rotateX(${-py * 6}deg)`
    }
    const onLeave = () => { fr.style.transform = 'none' }
    sc.addEventListener('mousemove', onFrame)
    sc.addEventListener('mouseleave', onLeave)
    cleanups.push(() => { sc.removeEventListener('mousemove', onFrame); sc.removeEventListener('mouseleave', onLeave) })
  }

  let timer: ReturnType<typeof setInterval> | undefined
  const start = () => { if (!reduced) timer = setInterval(() => { activeSlide.value = (activeSlide.value + 1) % slides.length }, 3600) }
  const stop = () => { if (timer) clearInterval(timer) }
  start()
  if (showcaseEl.value) {
    const sc = showcaseEl.value
    sc.addEventListener('mouseenter', stop)
    sc.addEventListener('mouseleave', start)
    cleanups.push(() => { sc.removeEventListener('mouseenter', stop); sc.removeEventListener('mouseleave', start) })
  }
  cleanups.push(stop)
})

onBeforeUnmount(() => { cleanups.forEach(fn => fn()); cleanups = [] })
</script>

<template>
  <div ref="showcaseEl" class="relative z-10 max-w-3xl mx-auto px-4 sm:px-8 pb-16 [perspective:1400px]">
    <div ref="frameEl" class="frame rounded-2xl border border-default bg-elevated shadow-2xl shadow-primary/10 overflow-hidden will-change-transform">
      <div class="flex items-center gap-1.5 h-10 px-4 border-b border-default bg-muted/50">
        <span class="size-2.5 rounded-full bg-default/60" aria-hidden="true" />
        <span class="size-2.5 rounded-full bg-default/40" aria-hidden="true" />
        <span class="size-2.5 rounded-full bg-default/25" aria-hidden="true" />
        <span class="ml-2 font-mono text-xs text-dimmed">plume · {{ activeTab }}</span>
      </div>
      <div class="relative h-[336px]">
        <!-- Écran 1 : Aujourd'hui -->
        <div class="slide p-5" :class="{ active: activeSlide === 0 }">
          <p class="text-sm text-muted">{{ t('landing.preview.caption') }}</p>
          <ul class="mt-4 flex flex-col gap-2">
            <li v-for="row in todayRows" :key="row.name" class="flex items-center gap-3 rounded-lg border border-default bg-default px-3 py-2.5">
              <span class="grid size-8 place-items-center rounded-full bg-muted text-muted shrink-0">
                <UIcon :name="row.icon" class="size-4" aria-hidden="true" />
              </span>
              <span class="font-medium truncate">{{ row.name }}</span>
              <div class="flex-1" />
              <UBadge :color="row.color" variant="soft" size="sm">{{ t(`landing.preview.status.${row.status}`) }}</UBadge>
            </li>
          </ul>
          <div class="mt-5">
            <div class="flex items-center justify-between text-xs text-muted">
              <span>{{ t('landing.preview.goal') }}</span>
              <span class="font-mono tabular-nums text-default">5 / 8</span>
            </div>
            <div class="mt-1.5 h-2 rounded-full bg-muted overflow-hidden">
              <div class="goal-fill h-full rounded-full bg-primary" :style="{ width: goalWidth }" />
            </div>
          </div>
        </div>

        <!-- Écran 2 : Pipeline -->
        <div class="slide p-5" :class="{ active: activeSlide === 1 }">
          <p class="text-sm text-muted">{{ t('landing.showcase.pipelineCaption') }}</p>
          <div class="mt-4 grid grid-cols-3 gap-2.5">
            <div v-for="col in pipelineCols" :key="col.key" class="rounded-lg border border-default bg-default p-2.5">
              <div class="flex items-center justify-between text-xs text-muted mb-2">
                <span class="truncate">{{ t(`landing.showcase.${col.key}`) }}</span>
                <span class="font-mono tabular-nums">{{ col.count }}</span>
              </div>
              <div v-for="c in col.cards" :key="c.name" class="rounded-md bg-muted/60 p-2 mb-1.5">
                <div class="text-xs font-medium truncate">{{ c.name }}</div>
                <div v-if="c.metaKey || c.meta" class="text-[11px] text-dimmed mt-0.5 truncate" :class="{ 'font-mono': c.meta }">{{ c.metaKey ? t(c.metaKey) : c.meta }}</div>
              </div>
            </div>
          </div>
        </div>

        <!-- Écran 3 : Tableau de bord -->
        <div class="slide p-5" :class="{ active: activeSlide === 2 }">
          <p class="text-sm text-muted">{{ t('landing.showcase.dashboardCaption') }}</p>
          <div class="mt-4 grid grid-cols-3 gap-2.5">
            <div v-for="(k, i) in kpis" :key="k.key" class="rounded-lg border border-default bg-default p-3">
              <div class="font-serif text-2xl tabular-nums">{{ formatKpi(i) }}</div>
              <div class="text-[11px] text-muted">{{ t(`landing.showcase.${k.key}`) }}</div>
            </div>
          </div>
          <div class="mt-3 flex items-end gap-2 h-28 rounded-lg border border-default bg-default p-3">
            <div v-for="(b, i) in bars" :key="i" class="bar flex-1 rounded-t bg-primary" :style="{ height: `${b}%`, transitionDelay: `${i * 0.05}s` }" />
          </div>
        </div>
      </div>
    </div>

    <div class="mt-4 flex justify-center gap-2">
      <button
        v-for="(s, i) in slides"
        :key="s"
        type="button"
        class="h-2 rounded-full transition-all"
        :class="activeSlide === i ? 'w-5 bg-primary' : 'w-2 bg-muted hover:bg-primary/50'"
        :aria-label="t(`landing.showcase.${s}Tab`)"
        @click="activeSlide = i"
      />
    </div>

    <!-- Indice de scroll (idée 2) -->
    <a
      href="#how"
      class="mt-10 mx-auto flex size-9 items-center justify-center rounded-full border border-default text-dimmed hover:text-primary hover:border-primary/40 transition-colors motion-safe:animate-bounce"
      :aria-label="t('landing.how.eyebrow')"
    >
      <UIcon name="i-lucide-chevron-down" class="size-5" aria-hidden="true" />
    </a>
  </div>
</template>

<style scoped>
.frame { transition: transform 0.2s ease; }
.slide { position: absolute; inset: 0; opacity: 0; transform: translateX(20px) scale(0.99); transition: opacity 0.5s ease, transform 0.5s ease; pointer-events: none; }
.slide.active { opacity: 1; transform: none; pointer-events: auto; }
.bar { transform: scaleY(0); transform-origin: bottom; transition: transform 0.6s cubic-bezier(0.2, 0.7, 0.2, 1); }
.slide.active .bar { transform: scaleY(1); }
.goal-fill { transition: width 0.8s cubic-bezier(0.2, 0.7, 0.2, 1); }

@media (prefers-reduced-motion: reduce) {
  .slide { transition: none !important; }
  .bar { transition: none !important; transform: none !important; }
  .goal-fill { transition: none !important; }
}
</style>
