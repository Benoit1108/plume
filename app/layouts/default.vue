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

// Le contenu principal reçoit le focus à chaque navigation : en SPA, sans ça, le focus reste sur
// le lien cliqué et un lecteur d'écran ne signale pas qu'on a changé d'écran (revue UX-P2d).
const mainRef = ref<HTMLElement | null>(null)
function focusMain(): void {
  void nextTick(() => mainRef.value?.focus())
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
      <main id="contenu" ref="mainRef" tabindex="-1" class="flex-1 min-w-0 outline-none">
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
