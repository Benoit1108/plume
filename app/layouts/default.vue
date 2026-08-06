<script setup lang="ts">
const { t } = useI18n()
const route = useRoute()

const navOpen = ref(false)

// Repli de la barre latérale (rail d'icônes) — persisté en cookie (SSR-safe).
const navCollapsed = useCookie<boolean>('plume_nav_collapsed', { default: () => false })

// Compteur de la file de tri (badge « À trier ») — chargé une fois à l'ouverture du shell.
const sourcing = useSourcing()
onMounted(() => {
  void sourcing.refreshCount()
})

/**
 * Le contenu principal reçoit le focus à chaque navigation : en SPA, sans ça, le focus reste sur
 * le lien cliqué et un lecteur d'écran ne signale pas qu'on a changé d'écran (revue UX-P2d).
 *
 * Mais ce focus doit être TRANSITOIRE. S'il reste sur `<main>`, l'ouverture d'une boîte de dialogue
 * (qui masque l'arrière-plan par `aria-hidden`) porterait sur un conteneur qui détient le focus —
 * ce que le navigateur refuse à juste titre : « Blocked aria-hidden on an element because its
 * descendant retained focus ». On relâche donc dès la première interaction de l'utilisatrice.
 */
const mainRef = ref<HTMLElement | null>(null)
function focusMain(): void {
  void nextTick(() => {
    const main = mainRef.value
    if (!main) return
    // `tabindex` posé puis RETIRÉ : tant qu'il est là, un clic dans une zone non focusable du
    // contenu renvoie le focus sur `<main>` (le navigateur remonte au premier ancêtre focusable).
    // Le retirer suffit à rendre le focus vraiment transitoire.
    main.setAttribute('tabindex', '-1')
    main.focus({ preventScroll: true })

    const release = (): void => {
      main.removeAttribute('tabindex')
      if (document.activeElement === main) main.blur()
      document.removeEventListener('pointerdown', release, true)
      document.removeEventListener('keydown', release, true)
    }
    document.addEventListener('pointerdown', release, true)
    document.addEventListener('keydown', release, true)
  })
}

// Ferme le tiroir dès qu'on change de page, et repositionne le focus sur le contenu.
watch(() => route.path, () => {
  navOpen.value = false
  focusMain()
})
</script>

<template>
  <div class="min-h-screen flex bg-default text-default">
    <!-- Lien d'évitement : au clavier, la barre latérale (7 liens) était retraversée à CHAQUE page
         avant d'atteindre le contenu (revue UX-P2d). Invisible tant qu'il n'a pas le focus. -->
    <a
      href="#contenu"
      class="sr-only focus:not-sr-only focus:fixed focus:z-50 focus:top-3 focus:left-3 focus:px-3 focus:py-2 focus:rounded-lg focus:bg-elevated focus:text-default focus:outline-2 focus:outline-primary"
      @click="focusMain"
    >{{ t('nav.skipToContent') }}</a>

    <aside
      class="shrink-0 border-r border-default hidden md:flex flex-col motion-safe:transition-[width] motion-safe:duration-200"
      :class="navCollapsed ? 'w-16 px-2 py-4' : 'w-56 p-4'"
    >
      <AppNav :collapsed="navCollapsed" collapsible @toggle-collapse="navCollapsed = !navCollapsed" />
    </aside>

    <div class="flex-1 min-w-0 flex flex-col">
      <header class="h-14 border-b border-default flex items-center gap-2 px-4">
        <UButton
          class="md:hidden"
          color="neutral"
          variant="ghost"
          size="sm"
          icon="i-lucide-menu"
          :aria-label="t('nav.openMenu')"
          @click="() => { navOpen = true }"
        />
        <NuxtLink
          to="/today"
          class="md:hidden inline-flex rounded-md focus-visible:outline-2 focus-visible:outline-primary"
          :aria-label="t('nav.home')"
        >
          <PlumeMark :size="18" />
        </NuxtLink>
        <div class="flex-1" />
        <NotificationBell />
        <LocaleSwitcher />
        <ThemeToggle />
        <AccountMenu />
      </header>
      <!-- `overflow-x-clip` : le halo d'« Aujourd'hui » déborde volontairement sur les côtés ;
           on le coupe ici plutôt que de laisser la PAGE défiler (clip, pas hidden : aucun
           conteneur de défilement créé, les éléments collants restent collants). -->
      <main id="contenu" ref="mainRef" class="flex-1 min-w-0 overflow-x-clip outline-none">
        <DemoBanner />
        <SubscriptionBanner />
        <slot />
      </main>
    </div>

    <!-- Tiroir de navigation mobile — USlideover gère focus trap, Échap et aria. -->
    <USlideover v-model:open="navOpen" side="left" :title="t('nav.menu')">
      <template #body>
        <AppNav @navigate="() => { navOpen = false }" />
      </template>
    </USlideover>
  </div>
</template>
