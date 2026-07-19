<script setup lang="ts">
const { t } = useI18n()
const auth = useAuthStore()
const route = useRoute()

const navOpen = ref(false)

// Repli de la barre latérale (rail d'icônes) — persisté en cookie (SSR-safe).
const navCollapsed = useCookie<boolean>('plume_nav_collapsed', { default: () => false })

// Compteur de la file de tri (badge « À trier ») — chargé une fois à l'ouverture du shell.
const sourcing = useSourcing()
onMounted(() => {
  void sourcing.refreshCount()
})

// Ferme le tiroir dès qu'on change de page.
watch(() => route.path, () => {
  navOpen.value = false
})
</script>

<template>
  <div class="min-h-screen flex bg-default text-default">
    <aside
      class="shrink-0 border-r border-default hidden md:flex flex-col transition-[width] duration-200"
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
        <LocaleSwitcher />
        <ThemeToggle />
        <UButton
          color="neutral"
          variant="ghost"
          size="sm"
          icon="i-lucide-log-out"
          :aria-label="t('auth.logout')"
          @click="auth.logout()"
        />
      </header>
      <main class="flex-1 min-w-0">
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
