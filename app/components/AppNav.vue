<script setup lang="ts">
const { t } = useI18n()
const auth = useAuthStore()
const route = useRoute()
const { pendingCount } = useSourcing()

withDefaults(defineProps<{ collapsed?: boolean, collapsible?: boolean }>(), {
  collapsed: false,
  collapsible: false,
})
defineEmits<{ navigate: [], toggleCollapse: [] }>()

const nav = computed((): { label: string, to: string, icon: string, badge?: number }[] => [
  { label: t('nav.today'), to: '/today', icon: 'i-lucide-calendar-check' },
  { label: t('nav.dashboard'), to: '/dashboard', icon: 'i-lucide-layout-dashboard' },
  { label: t('nav.leads'), to: '/leads', icon: 'i-lucide-square-kanban' },
  { label: t('nav.triage'), to: '/candidates', icon: 'i-lucide-inbox', badge: pendingCount.value || undefined },
  { label: t('nav.directory'), to: '/organizations', icon: 'i-lucide-building-2' },
  { label: t('nav.templates'), to: '/templates', icon: 'i-lucide-file-text' },
  { label: t('nav.settings'), to: '/settings', icon: 'i-lucide-settings' },
  { label: t('nav.account'), to: '/account', icon: 'i-lucide-circle-user' },
])
</script>

<template>
  <div class="flex flex-col gap-1 flex-1 min-h-0">
    <NuxtLink
      to="/today"
      class="pb-4 inline-flex rounded-md focus-visible:outline-2 focus-visible:outline-primary"
      :class="collapsed ? 'justify-center px-0' : 'px-2'"
      :aria-label="t('nav.home')"
      @click="$emit('navigate')"
    >
      <PlumeMark :size="22" :wordmark="!collapsed" />
    </NuxtLink>

    <NuxtLink
      v-for="item in nav"
      :key="item.to"
      :to="item.to"
      class="relative flex items-center gap-3 py-2.5 rounded-md text-[15px]"
      :class="[
        collapsed ? 'justify-center px-0' : 'px-3',
        route.path.startsWith(item.to) ? 'bg-elevated text-highlighted font-semibold' : 'text-muted hover:bg-elevated',
      ]"
      :title="collapsed ? item.label : undefined"
      :aria-label="collapsed ? item.label : undefined"
      @click="$emit('navigate')"
    >
      <UIcon :name="item.icon" class="size-5 shrink-0" aria-hidden="true" />
      <span v-if="!collapsed" class="truncate">{{ item.label }}</span>
      <UBadge v-if="item.badge && !collapsed" color="primary" variant="solid" size="sm" class="ml-auto">
        {{ item.badge }}
      </UBadge>
      <span
        v-else-if="item.badge && collapsed"
        class="absolute top-1 right-1 size-2 rounded-full bg-primary"
        aria-hidden="true"
      />
    </NuxtLink>

    <div class="mt-auto pt-3 border-t border-default flex flex-col gap-2">
      <button
        v-if="collapsible"
        type="button"
        class="flex items-center gap-3 py-2 rounded-md text-sm text-muted hover:bg-elevated"
        :class="collapsed ? 'justify-center px-0' : 'px-3'"
        :aria-label="collapsed ? t('nav.expand') : t('nav.collapse')"
        :title="collapsed ? t('nav.expand') : t('nav.collapse')"
        @click="$emit('toggleCollapse')"
      >
        <UIcon
          :name="collapsed ? 'i-lucide-chevrons-right' : 'i-lucide-chevrons-left'"
          class="size-5 shrink-0"
          aria-hidden="true"
        />
        <span v-if="!collapsed">{{ t('nav.collapse') }}</span>
      </button>
      <div v-if="!collapsed" class="text-xs text-dimmed font-mono truncate px-3">
        {{ auth.email }}
      </div>
    </div>
  </div>
</template>
