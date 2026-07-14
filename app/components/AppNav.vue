<script setup lang="ts">
const { t } = useI18n()
const auth = useAuthStore()
const route = useRoute()

defineEmits<{ navigate: [] }>()

const nav = computed((): { label: string, to: string | null }[] => [
  { label: t('nav.today'), to: '/today' },
  { label: t('nav.dashboard'), to: '/dashboard' },
  { label: t('nav.leads'), to: '/leads' },
  { label: t('nav.directory'), to: '/organizations' },
  { label: t('nav.templates'), to: '/templates' },
  { label: t('nav.settings'), to: '/settings' },
])
</script>

<template>
  <div class="flex flex-col gap-1 flex-1 min-h-0">
    <PlumeMark :size="20" class="px-2 pb-4" />
    <template v-for="item in nav" :key="item.label">
      <NuxtLink
        v-if="item.to"
        :to="item.to"
        class="px-3 py-2 rounded-md text-sm"
        :class="route.path.startsWith(item.to) ? 'bg-elevated text-highlighted font-semibold' : 'text-muted hover:bg-elevated'"
        @click="$emit('navigate')"
      >{{ item.label }}</NuxtLink>
      <span v-else class="px-3 py-2 rounded-md text-sm text-dimmed cursor-default select-none">{{ item.label }}</span>
    </template>
    <div class="mt-auto pt-3 border-t border-default text-xs text-dimmed font-mono truncate">
      {{ auth.email }}
    </div>
  </div>
</template>
