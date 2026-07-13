<script setup lang="ts">
const { t } = useI18n()
const auth = useAuthStore()
const route = useRoute()

const navOpen = ref(false)

// Ferme le tiroir dès qu'on change de page.
watch(() => route.path, () => {
  navOpen.value = false
})
</script>

<template>
  <div class="min-h-screen flex bg-default text-default">
    <aside class="w-56 shrink-0 border-r border-default p-4 hidden md:flex flex-col">
      <AppNav />
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
        <PlumeMark :size="18" class="md:hidden" />
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
