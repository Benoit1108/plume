import type { Ref } from 'vue'

// Mouvement ambiant de la vitrine (niveau page) : barre de progression + parallaxe (scroll & souris)
// + révélations au scroll + particules d'encre. Entièrement neutralisé sous prefers-reduced-motion,
// nettoyé au démontage (listeners, rAF, IntersectionObserver). Client uniquement (SPA).
export function useLandingMotion(refs: {
  root: Ref<HTMLElement | undefined>
  canvas: Ref<HTMLCanvasElement | undefined>
  progress: Ref<HTMLElement | undefined>
}) {
  let cleanups: Array<() => void> = []

  onMounted(() => {
    const reduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches
    const rootEl = refs.root.value
    const parallaxEls = rootEl ? [...rootEl.querySelectorAll<HTMLElement>('[data-parallax]')] : []
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
      const p = refs.progress.value
      if (p) {
        const h = document.documentElement
        const max = h.scrollHeight - h.clientHeight
        p.style.width = `${max > 0 ? (h.scrollTop / max) * 100 : 0}%`
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
    }

    // Révélations au scroll.
    const revealEls = rootEl ? [...rootEl.querySelectorAll('.reveal')] : []
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

    // Particules d'encre (couleur selon le thème).
    if (!reduced && refs.canvas.value) {
      const canvas = refs.canvas.value
      const ctx = canvas.getContext('2d')
      if (ctx) {
        let raf = 0
        let w = 0
        let h = 0
        let dust: Array<{ x: number, y: number, r: number, s: number, a: number, d: number }> = []
        const seed = () => {
          w = canvas.width = window.innerWidth
          h = canvas.height = window.innerHeight
          dust = Array.from({ length: 90 }, () => ({
            x: Math.random() * w,
            y: Math.random() * h,
            r: Math.random() * 2.2 + 0.5,
            s: Math.random() * 0.28 + 0.05,
            a: Math.random() * 0.4 + 0.14,
            d: (Math.random() - 0.5) * 0.22,
          }))
        }
        const loop = () => {
          ctx.clearRect(0, 0, w, h)
          const rgb = document.documentElement.classList.contains('dark') ? '176, 158, 246' : '104, 80, 194'
          for (const p of dust) {
            p.y -= p.s
            p.x += p.d
            if (p.y < -5) { p.y = h + 5; p.x = Math.random() * w }
            ctx.beginPath()
            ctx.arc(p.x, p.y, p.r, 0, Math.PI * 2)
            ctx.fillStyle = `rgba(${rgb}, ${p.a})`
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
}
