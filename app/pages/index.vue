<script setup lang="ts">
// Vitrine publique (V2). Racine « / » : page marketing pour les visiteurs, redirigée vers l'app
// (l'écran du quotidien) dès qu'on est connecté (décision M1.3 n°5).
// Direction « Encre vivante » : plume ambiante + particules + parallaxe + carrousel produit +
// révélations au scroll. Tout est neutralisé sous prefers-reduced-motion (plancher a11y).
definePageMeta({ layout: false })

const { t } = useI18n()
const auth = useAuthStore()
const toast = useToast()

if (auth.isAuthenticated) {
  await navigateTo('/today', { replace: true })
}

const features = ['pipeline', 'replies', 'drafting', 'sourcing', 'dashboard'] as const
const included = ['pipeline', 'drafting', 'mailbox', 'sourcing', 'dashboard', 'support'] as const
const steps = ['find', 'write', 'track'] as const
const audience = ['editorial', 'audiovisual', 'technical'] as const

const titleWords = computed(() => t('landing.hero.title').split(' '))

// --- Carrousel produit (données FICTIVES, illustratives) ---
const slides = ['today', 'pipeline', 'dashboard'] as const
const activeSlide = ref(0)
const activeTab = computed(() => t(`landing.showcase.${slides[activeSlide.value]}Tab`))

const todayRows = [
  { name: 'Éditions Margelle', status: 'due', color: 'warning', icon: 'i-lucide-clock' },
  { name: 'Studio Bleu Nuit', status: 'replied', color: 'success', icon: 'i-lucide-mail-check' },
  { name: 'Agence Verba', status: 'dormant', color: 'neutral', icon: 'i-lucide-moon' },
] as const

const pipelineCols: { key: string, count: number, cards: { name: string, meta?: string }[] }[] = [
  { key: 'colToContact', count: 4, cards: [{ name: 'Éditions Margelle' }, { name: 'Maison Aster' }] },
  { key: 'colInTalks', count: 2, cards: [{ name: 'Studio Bleu Nuit' }] },
  { key: 'colWon', count: 3, cards: [{ name: 'Agence Verba', meta: '2 400 €' }] },
]

const kpis = [
  { key: 'kpiResponse', value: '38 %' },
  { key: 'kpiFollowups', value: '12' },
  { key: 'kpiPipeline', value: '7 200 €' },
] as const
const bars = [40, 65, 52, 80, 70, 95]

// « Essayer la démo » : l'API monte un compte éphémère pré-rempli et nous connecte directement.
const enteringDemo = ref(false)
async function tryDemo() {
  if (enteringDemo.value) return
  enteringDemo.value = true
  try {
    await auth.enterDemo()
    await navigateTo('/today')
  }
  catch {
    toast.add({ title: t('landing.demo.error'), color: 'error' })
  }
  finally {
    enteringDemo.value = false
  }
}

// --- Mise en mouvement (client uniquement, SPA) ---
const root = ref<HTMLElement>()
const motes = ref<HTMLCanvasElement>()
const progress = ref<HTMLElement>()
const frame = ref<HTMLElement>()
const showcase = ref<HTMLElement>()

let cleanups: Array<() => void> = []

onMounted(() => {
  const reduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches
  const parallaxEls = root.value ? [...root.value.querySelectorAll<HTMLElement>('[data-parallax]')] : []
  let mx = 0
  let my = 0

  const applyParallax = () => {
    const y = window.scrollY
    for (const el of parallaxEls) {
      const f = Number.parseFloat(el.dataset.parallax || '0')
      const m = Number.parseFloat(el.dataset.mouse || '0')
      const rot = el.dataset.rot ? ` rotate(${el.dataset.rot}deg)` : ''
      el.style.transform = `translate3d(${mx * m}px, ${y * f + my * m}px, 0)${rot}`
    }
  }
  const onScroll = () => {
    if (progress.value) {
      const h = document.documentElement
      const max = h.scrollHeight - h.clientHeight
      progress.value.style.width = `${max > 0 ? (h.scrollTop / max) * 100 : 0}%`
    }
    if (!reduced) applyParallax()
  }
  window.addEventListener('scroll', onScroll, { passive: true })
  cleanups.push(() => window.removeEventListener('scroll', onScroll))
  onScroll()

  if (!reduced) {
    const onMouse = (e: MouseEvent) => {
      mx = e.clientX / window.innerWidth - 0.5
      my = e.clientY / window.innerHeight - 0.5
      applyParallax()
    }
    window.addEventListener('mousemove', onMouse, { passive: true })
    cleanups.push(() => window.removeEventListener('mousemove', onMouse))

    // Inclinaison 3D du cadre produit.
    if (showcase.value && frame.value) {
      const sc = showcase.value
      const fr = frame.value
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
  }

  // Carrousel : défilement auto, pause au survol.
  let timer: ReturnType<typeof setInterval> | undefined
  const start = () => { if (!reduced) timer = setInterval(() => { activeSlide.value = (activeSlide.value + 1) % slides.length }, 3600) }
  const stop = () => { if (timer) clearInterval(timer) }
  start()
  if (showcase.value) {
    const sc = showcase.value
    sc.addEventListener('mouseenter', stop)
    sc.addEventListener('mouseleave', start)
    cleanups.push(() => { sc.removeEventListener('mouseenter', stop); sc.removeEventListener('mouseleave', start) })
  }
  cleanups.push(stop)

  // Révélations au scroll.
  const revealEls = root.value ? [...root.value.querySelectorAll('.reveal')] : []
  if (reduced) {
    revealEls.forEach(el => el.classList.add('in'))
  }
  else {
    const io = new IntersectionObserver((entries) => {
      for (const en of entries) if (en.isIntersecting) { en.target.classList.add('in'); io.unobserve(en.target) }
    }, { threshold: 0.15 })
    revealEls.forEach(el => io.observe(el))
    cleanups.push(() => io.disconnect())
  }

  // Particules d'encre.
  if (!reduced && motes.value) {
    const canvas = motes.value
    const ctx = canvas.getContext('2d')
    if (ctx) {
      let raf = 0
      let w = 0
      let h = 0
      let dust: Array<{ x: number, y: number, r: number, s: number, a: number, d: number }> = []
      const seed = () => {
        w = canvas.width = window.innerWidth
        h = canvas.height = window.innerHeight
        dust = Array.from({ length: 64 }, () => ({
          x: Math.random() * w,
          y: Math.random() * h,
          r: Math.random() * 1.8 + 0.4,
          s: Math.random() * 0.25 + 0.05,
          a: Math.random() * 0.35 + 0.05,
          d: (Math.random() - 0.5) * 0.2,
        }))
      }
      const loop = () => {
        ctx.clearRect(0, 0, w, h)
        for (const p of dust) {
          p.y -= p.s
          p.x += p.d
          if (p.y < -5) { p.y = h + 5; p.x = Math.random() * w }
          ctx.beginPath()
          ctx.arc(p.x, p.y, p.r, 0, Math.PI * 2)
          ctx.fillStyle = `rgba(155, 135, 217, ${p.a})`
          ctx.fill()
        }
        raf = requestAnimationFrame(loop)
      }
      const onResize = () => seed()
      seed()
      loop()
      window.addEventListener('resize', onResize)
      cleanups.push(() => { cancelAnimationFrame(raf); window.removeEventListener('resize', onResize) })
    }
  }
})

onBeforeUnmount(() => { cleanups.forEach(fn => fn()); cleanups = [] })
</script>

<template>
  <div ref="root" class="relative min-h-screen bg-default text-default flex flex-col">
    <div ref="progress" class="fixed top-0 left-0 h-0.5 w-0 z-[60]" style="background: linear-gradient(90deg, var(--color-plume-600), var(--color-plume-300))" aria-hidden="true" />
    <canvas ref="motes" class="pointer-events-none fixed inset-0 z-0 opacity-70" aria-hidden="true" />

    <!-- Marque « plume » réutilisable (fond ambiant + bandeau CTA) -->
    <svg class="hidden" aria-hidden="true">
      <symbol id="plume-quill" viewBox="0 0 200 400">
        <path d="M150 20 C 90 70 55 150 60 250 C 62 300 80 350 96 384" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" />
        <path d="M150 20 C 120 40 60 60 40 120 C 70 108 110 96 138 60 Z" fill="currentColor" opacity="0.5" />
        <path d="M138 70 C 112 92 66 108 48 168 C 82 152 120 140 146 96 Z" fill="currentColor" opacity="0.42" />
        <path d="M132 128 C 106 150 64 168 48 226 C 84 208 118 196 142 150 Z" fill="currentColor" opacity="0.36" />
        <path d="M124 190 C 100 212 66 230 52 284 C 86 266 114 254 134 210 Z" fill="currentColor" opacity="0.3" />
        <path d="M116 250 C 96 270 70 286 60 330 C 90 314 110 304 126 268 Z" fill="currentColor" opacity="0.24" />
      </symbol>
    </svg>

    <!-- En-tête (bords gauche/droite) -->
    <header class="sticky top-0 z-40 h-16 flex items-center gap-4 px-4 sm:px-8 border-b border-default bg-default/80 backdrop-blur">
      <NuxtLink to="/" class="inline-flex rounded-md focus-visible:outline-2 focus-visible:outline-primary" :aria-label="t('nav.home')">
        <PlumeMark :size="24" />
      </NuxtLink>
      <nav class="hidden md:flex items-center gap-5 ml-2 text-sm">
        <a href="#how" class="text-muted hover:text-default transition-colors">{{ t('landing.how.eyebrow') }}</a>
        <a href="#features" class="text-muted hover:text-default transition-colors">{{ t('landing.features.eyebrow') }}</a>
        <a href="#pricing" class="text-muted hover:text-default transition-colors">{{ t('landing.pricing.eyebrow') }}</a>
      </nav>
      <div class="flex-1" />
      <LocaleSwitcher />
      <ThemeToggle />
      <UButton to="/login" color="neutral" variant="ghost" size="sm">{{ t('landing.nav.login') }}</UButton>
      <UButton to="/register" size="sm">{{ t('landing.nav.signup') }}</UButton>
    </header>

    <main class="relative z-10 flex-1">
      <!-- Hero -->
      <section class="relative overflow-hidden">
        <div class="ink-bloom pointer-events-none absolute inset-x-0 top-0 h-[520px]" data-parallax="0.12" aria-hidden="true" />
        <svg
          class="pointer-events-none absolute top-20 -right-16 w-[340px] md:w-[560px] text-primary opacity-[0.18] will-change-transform"
          data-parallax="-0.22" data-mouse="16" data-rot="8"
          style="transform: rotate(8deg)"
          aria-hidden="true"
        ><use href="#plume-quill" /></svg>

        <div class="relative z-10 max-w-3xl mx-auto px-4 sm:px-8 pt-24 pb-8 text-center">
          <p class="enter font-mono text-xs uppercase tracking-[0.2em] text-primary" style="animation-delay: 0.05s">{{ t('landing.hero.eyebrow') }}</p>
          <div class="relative mt-4 inline-block">
            <h1 class="font-serif text-4xl sm:text-6xl font-semibold text-balance leading-[1.05]">
              <span v-for="(w, i) in titleWords" :key="i" class="word" :style="{ animationDelay: `${0.12 + i * 0.06}s` }">{{ w }}</span>
            </h1>
            <svg
              class="ink-draw pointer-events-none absolute left-1/2 top-full w-48 sm:w-64 -translate-x-1/2 mt-3 text-primary/70"
              viewBox="0 0 320 28"
              fill="none"
              aria-hidden="true"
            >
              <path d="M8 18C70 6 138 5 184 13S276 22 312 9" stroke="currentColor" stroke-width="3" stroke-linecap="round" pathLength="1" />
            </svg>
          </div>
          <p class="enter mt-6 text-lg text-muted max-w-2xl mx-auto text-pretty" style="animation-delay: 0.5s">{{ t('landing.hero.subtitle') }}</p>
          <div class="enter mt-8 flex items-center justify-center gap-3 flex-wrap" style="animation-delay: 0.6s">
            <UButton to="/register" size="lg" icon="i-lucide-arrow-right" trailing>{{ t('landing.hero.cta') }}</UButton>
            <UButton size="lg" color="neutral" variant="outline" icon="i-lucide-play" :loading="enteringDemo" @click="tryDemo">{{ t('landing.hero.demoCta') }}</UButton>
          </div>
          <p class="enter mt-3 text-xs text-dimmed" style="animation-delay: 0.7s">{{ t('landing.hero.trialNote') }} · {{ t('landing.hero.demoNote') }}</p>
        </div>

        <!-- Vitrine produit : carrousel -->
        <div ref="showcase" class="relative z-10 max-w-2xl mx-auto px-4 sm:px-8 pb-20 [perspective:1400px]">
          <div ref="frame" class="frame rounded-2xl border border-default bg-elevated shadow-2xl shadow-primary/10 overflow-hidden will-change-transform">
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
                    <div class="h-full rounded-full bg-primary" style="width: 62.5%" />
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
                      <div v-if="c.meta" class="text-[11px] text-dimmed mt-0.5 font-mono">{{ c.meta }}</div>
                    </div>
                  </div>
                </div>
              </div>

              <!-- Écran 3 : Tableau de bord -->
              <div class="slide p-5" :class="{ active: activeSlide === 2 }">
                <p class="text-sm text-muted">{{ t('landing.showcase.dashboardCaption') }}</p>
                <div class="mt-4 grid grid-cols-3 gap-2.5">
                  <div v-for="k in kpis" :key="k.key" class="rounded-lg border border-default bg-default p-3">
                    <div class="font-serif text-2xl">{{ k.value }}</div>
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
        </div>
      </section>

      <!-- Comment ça marche -->
      <section id="how" class="bg-muted/40 border-y border-default scroll-mt-16">
        <div class="max-w-5xl mx-auto px-4 sm:px-8 py-16 sm:py-20">
          <div class="reveal max-w-xl mx-auto text-center">
            <p class="font-mono text-xs uppercase tracking-[0.2em] text-primary">{{ t('landing.how.eyebrow') }}</p>
            <h2 class="mt-3 font-serif text-3xl sm:text-4xl font-semibold text-balance">{{ t('landing.how.title') }}</h2>
          </div>
          <ol class="mt-12 grid gap-8 sm:grid-cols-3">
            <li v-for="(s, i) in steps" :key="s" class="reveal" :style="{ transitionDelay: `${i * 0.1}s` }">
              <div class="flex items-center gap-3">
                <span class="font-mono text-sm text-primary tabular-nums">0{{ i + 1 }}</span>
                <span class="h-px flex-1 bg-default" aria-hidden="true" />
                <UIcon :name="t(`landing.how.${s}.icon`)" class="size-5 text-primary" aria-hidden="true" />
              </div>
              <h3 class="mt-4 font-serif text-xl font-semibold">{{ t(`landing.how.${s}.title`) }}</h3>
              <p class="mt-2 text-sm text-muted text-pretty">{{ t(`landing.how.${s}.text`) }}</p>
            </li>
          </ol>
        </div>
      </section>

      <!-- Fonctionnalités -->
      <section id="features" class="max-w-5xl mx-auto px-4 sm:px-8 py-16 sm:py-20 scroll-mt-16">
        <div class="reveal max-w-xl mx-auto text-center">
          <p class="font-mono text-xs uppercase tracking-[0.2em] text-primary">{{ t('landing.features.eyebrow') }}</p>
          <h2 class="mt-3 font-serif text-3xl sm:text-4xl font-semibold text-balance">{{ t('landing.features.title') }}</h2>
        </div>
        <div class="mt-12 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
          <div
            v-for="(f, i) in features"
            :key="f"
            class="reveal border border-default rounded-xl p-5 bg-elevated/40 motion-safe:transition motion-safe:duration-200 hover:border-primary/40 hover:-translate-y-0.5"
            :style="{ transitionDelay: `${(i % 3) * 0.08}s` }"
          >
            <UIcon :name="t(`landing.features.${f}.icon`)" class="size-6 text-primary" aria-hidden="true" />
            <h3 class="mt-3 font-serif text-lg font-semibold">{{ t(`landing.features.${f}.title`) }}</h3>
            <p class="mt-1 text-sm text-muted text-pretty">{{ t(`landing.features.${f}.text`) }}</p>
          </div>
        </div>
      </section>

      <!-- Pour qui -->
      <section class="bg-muted/40 border-y border-default">
        <div class="max-w-5xl mx-auto px-4 sm:px-8 py-16 sm:py-20">
          <div class="reveal max-w-xl mx-auto text-center">
            <p class="font-mono text-xs uppercase tracking-[0.2em] text-primary">{{ t('landing.audience.eyebrow') }}</p>
            <h2 class="mt-3 font-serif text-3xl sm:text-4xl font-semibold text-balance">{{ t('landing.audience.title') }}</h2>
          </div>
          <div class="mt-12 grid gap-4 sm:grid-cols-3">
            <div v-for="(a, i) in audience" :key="a" class="reveal rounded-xl border border-default bg-default p-5" :style="{ transitionDelay: `${i * 0.08}s` }">
              <UIcon :name="t(`landing.audience.${a}.icon`)" class="size-6 text-primary" aria-hidden="true" />
              <h3 class="mt-3 font-serif text-lg font-semibold">{{ t(`landing.audience.${a}.title`) }}</h3>
              <p class="mt-1 text-sm text-muted text-pretty">{{ t(`landing.audience.${a}.text`) }}</p>
            </div>
          </div>
        </div>
      </section>

      <!-- Tarifs -->
      <section id="pricing" class="max-w-5xl mx-auto px-4 sm:px-8 py-16 sm:py-20 scroll-mt-16">
        <div class="reveal max-w-xl mx-auto text-center">
          <p class="font-mono text-xs uppercase tracking-[0.2em] text-primary">{{ t('landing.pricing.eyebrow') }}</p>
          <h2 class="mt-3 font-serif text-3xl sm:text-4xl font-semibold text-balance">{{ t('landing.pricing.title') }}</h2>
        </div>
        <div class="reveal mt-10 max-w-md mx-auto">
          <div class="border border-primary/40 rounded-2xl p-6 bg-elevated/40">
            <div class="flex items-baseline justify-between">
              <h3 class="font-serif text-xl font-semibold">{{ t('landing.pricing.planName') }}</h3>
              <UBadge color="primary" variant="soft">{{ t('landing.pricing.badge') }}</UBadge>
            </div>
            <p class="mt-4">
              <span class="font-serif text-4xl font-semibold tabular-nums">{{ t('landing.pricing.monthly') }}</span>
              <span class="text-muted text-sm"> {{ t('landing.pricing.perMonth') }}</span>
            </p>
            <p class="text-xs text-muted mt-1">{{ t('landing.pricing.annual') }}</p>
            <ul class="mt-5 flex flex-col gap-2 text-sm">
              <li v-for="item in included" :key="item" class="flex items-start gap-2">
                <UIcon name="i-lucide-check" class="size-4 text-primary mt-0.5 shrink-0" aria-hidden="true" />
                <span>{{ t(`landing.pricing.included.${item}`) }}</span>
              </li>
            </ul>
            <UButton to="/register" block size="lg" class="mt-6">{{ t('landing.hero.cta') }}</UButton>
            <p class="text-center text-xs text-dimmed mt-2">{{ t('landing.pricing.trust') }}</p>
          </div>
        </div>
      </section>

      <!-- Bandeau d'appel à l'action -->
      <section class="relative overflow-hidden border-t border-default">
        <div class="ink-bloom pointer-events-none absolute inset-0" aria-hidden="true" />
        <svg
          class="pointer-events-none absolute left-1/2 top-1/2 w-[520px] -translate-x-1/2 -translate-y-1/2 text-primary opacity-[0.06] will-change-transform"
          data-parallax="-0.15" data-rot="-6"
          style="transform: translate(-50%, -50%) rotate(-6deg)"
          aria-hidden="true"
        ><use href="#plume-quill" /></svg>
        <div class="relative z-10 max-w-3xl mx-auto px-4 sm:px-8 py-16 sm:py-20 text-center">
          <h2 class="reveal font-serif text-3xl sm:text-4xl font-semibold text-balance">{{ t('landing.cta.title') }}</h2>
          <p class="reveal mt-4 text-muted max-w-xl mx-auto text-pretty">{{ t('landing.cta.text') }}</p>
          <div class="reveal mt-8 flex items-center justify-center gap-3 flex-wrap">
            <UButton to="/register" size="lg" icon="i-lucide-arrow-right" trailing>{{ t('landing.hero.cta') }}</UButton>
            <UButton size="lg" color="neutral" variant="outline" icon="i-lucide-play" :loading="enteringDemo" @click="tryDemo">{{ t('landing.hero.demoCta') }}</UButton>
          </div>
        </div>
      </section>
    </main>

    <!-- Pied de page -->
    <footer class="relative z-10 border-t border-default px-4 sm:px-8 py-6 text-xs text-muted flex flex-wrap items-center gap-x-4 gap-y-2">
      <span>© {{ new Date().getFullYear() }} Plume</span>
      <div class="flex-1" />
      <NuxtLink to="/legal/terms" class="hover:text-default">{{ t('legal.terms.title') }}</NuxtLink>
      <NuxtLink to="/legal/privacy" class="hover:text-default">{{ t('legal.privacy.title') }}</NuxtLink>
      <NuxtLink to="/login" class="hover:text-default">{{ t('landing.nav.login') }}</NuxtLink>
    </footer>
  </div>
</template>

<style scoped>
/* Entrée du hero (éléments hors titre) + révélation mot à mot du titre. */
.enter { opacity: 0; animation: enter 0.7s cubic-bezier(0.2, 0.7, 0.2, 1) both; }
@keyframes enter { from { opacity: 0; transform: translateY(16px); } to { opacity: 1; transform: none; } }

/* margin-inline-end remplace l'espace : inline-block avale l'espace blanc entre les mots. */
.word { display: inline-block; opacity: 0; margin-inline-end: 0.25em; animation: wordin 0.7s cubic-bezier(0.2, 0.7, 0.2, 1) both; }
@keyframes wordin { from { opacity: 0; transform: translateY(18px); filter: blur(6px); } to { opacity: 1; transform: none; filter: none; } }

/* Révélations au scroll (classe .in posée par IntersectionObserver). */
.reveal { opacity: 0; transform: translateY(26px); transition: opacity 0.7s cubic-bezier(0.2, 0.7, 0.2, 1), transform 0.7s cubic-bezier(0.2, 0.7, 0.2, 1); }
.reveal.in { opacity: 1; transform: none; }

/* Cadre produit + carrousel. */
.frame { transition: transform 0.2s ease; }
.slide { position: absolute; inset: 0; opacity: 0; transform: translateX(20px) scale(0.99); transition: opacity 0.5s ease, transform 0.5s ease; pointer-events: none; }
.slide.active { opacity: 1; transform: none; pointer-events: auto; }
.bar { transform: scaleY(0); transform-origin: bottom; transition: transform 0.6s cubic-bezier(0.2, 0.7, 0.2, 1); }
.slide.active .bar { transform: scaleY(1); }

@media (prefers-reduced-motion: reduce) {
  .enter,
  .word { animation: none !important; opacity: 1 !important; transform: none !important; filter: none !important; }
  .reveal { opacity: 1 !important; transform: none !important; transition: none !important; }
  .slide { transition: none !important; }
  .bar { transition: none !important; transform: none !important; }
}
</style>
