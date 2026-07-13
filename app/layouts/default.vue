<script setup lang="ts">
const { t } = useI18n()
const auth = useAuthStore()
const route = useRoute()

const nav = [
  { label: 'Aujourd\'hui', to: null },
  { label: 'Pistes', to: null },
  { label: 'Répertoire', to: '/organizations' },
  { label: 'Modèles', to: null },
]
</script>

<template>
  <div class="min-h-screen flex bg-default text-default">
    <aside class="w-56 shrink-0 border-r border-default p-4 hidden md:flex flex-col gap-1">
      <PlumeMark :size="20" class="px-2 pb-4" />
      <template v-for="item in nav" :key="item.label">
        <NuxtLink
          v-if="item.to"
          :to="item.to"
          class="px-3 py-2 rounded-md text-sm"
          :class="route.path.startsWith(item.to) ? 'bg-elevated text-highlighted font-semibold' : 'text-muted hover:bg-elevated'"
        >{{ item.label }}</NuxtLink>
        <span v-else class="px-3 py-2 rounded-md text-sm text-dimmed cursor-default select-none">{{ item.label }}</span>
      </template>
      <div class="mt-auto pt-3 border-t border-default text-xs text-dimmed font-mono truncate">
        {{ auth.email }}
      </div>
    </aside>

    <div class="flex-1 min-w-0 flex flex-col">
      <header class="h-14 border-b border-default flex items-center gap-2 px-4">
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
  </div>
</template>
