<script setup lang="ts">
const { t } = useI18n()
const auth = useAuthStore()
const route = useRoute()

const navOpen = ref(false)

// Ferme le tiroir dès qu'on change de page.
watch(() => route.path, () => { navOpen.value = false })
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
          aria-label="Ouvrir le menu"
          @click="() => { navOpen = true }"
        />
        <PlumeMark :size="18" class="md:hidden" />
        <div class="flex-1" />
        <ThemeToggle />
        <UButton
          color="neutral"
          variant="ghost"
          size="sm"
          icon="i-lucide-log-out"
          :aria-label="t('home.logout')"
          @click="auth.logout()"
        />
      </header>
      <main class="flex-1 min-w-0">
        <slot />
      </main>
    </div>

    <!-- Tiroir de navigation mobile -->
    <ClientOnly>
      <Teleport to="body">
        <Transition
          enter-active-class="transition-opacity duration-200"
          leave-active-class="transition-opacity duration-200"
          enter-from-class="opacity-0"
          leave-to-class="opacity-0"
        >
          <div
            v-if="navOpen"
            class="fixed inset-0 z-50 bg-black/50 md:hidden"
            @click="() => { navOpen = false }"
          />
        </Transition>
        <Transition
          enter-active-class="transition-transform duration-200 ease-out"
          leave-active-class="transition-transform duration-200 ease-in"
          enter-from-class="-translate-x-full"
          leave-to-class="-translate-x-full"
        >
          <aside
            v-if="navOpen"
            class="fixed inset-y-0 left-0 z-50 w-64 max-w-[80vw] bg-default border-r border-default p-4 flex flex-col md:hidden"
          >
            <AppNav @navigate="() => { navOpen = false }" />
          </aside>
        </Transition>
      </Teleport>
    </ClientOnly>
  </div>
</template>
