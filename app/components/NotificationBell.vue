<script setup lang="ts">
import type { AppNotification } from '~/types/notifications'

/**
 * Cloche du centre de notifications (header). Rafraîchi en douceur par polling (60 s) — le badge
 * se dérive de la liste (pas d'endpoint compteur). Clic sur une notification : marquée lue puis
 * navigation vers la piste concernée.
 */
const { t, locale } = useI18n()
const notificationsApi = useNotifications()
const queryClient = useQueryClient()

const open = ref(false)

const { data } = useQuery({
  queryKey: queryKeys.notifications,
  queryFn: () => notificationsApi.list(),
  refetchInterval: 60_000,
})
const notifications = computed<AppNotification[]>(() => data.value ?? [])
const unread = computed(() => unreadCount(notifications.value))

async function refresh(): Promise<void> {
  await queryClient.invalidateQueries({ queryKey: queryKeys.notifications })
}

async function openNotification(notification: AppNotification): Promise<void> {
  open.value = false
  if (!notification.readAt) {
    // Marquage optimiste : l'UI n'attend pas le réseau pour refléter la lecture.
    queryClient.setQueryData<AppNotification[]>(
      queryKeys.notifications,
      current => (current ?? []).map(n => (n.id === notification.id ? { ...n, readAt: new Date().toISOString() } : n)),
    )
    notificationsApi.markRead(notification.id).catch(() => { void refresh() })
  }
  await navigateTo(notificationTarget(notification))
}

const markingAll = ref(false)
async function markAllRead(): Promise<void> {
  if (markingAll.value) return
  markingAll.value = true
  try {
    await notificationsApi.markAllRead()
    await refresh()
  }
  finally {
    markingAll.value = false
  }
}

/** Texte de la notification — libellé i18n par type, nourri du payload. */
function label(notification: AppNotification): string {
  const p = notification.payload
  switch (notification.type) {
    case 'reply_received':
      return t('notifications.types.replyReceived')
    case 'email_send_failed':
      return t('notifications.types.emailSendFailed')
    case 'followup_due':
      return t('notifications.types.followupDue', { org: typeof p.orgName === 'string' ? p.orgName : '?' })
    default:
      return notification.type
  }
}

function detail(notification: AppNotification): string {
  const p = notification.payload
  if (notification.type === 'reply_received' && typeof p.preview === 'string') return p.preview
  if (notification.type === 'followup_due' && typeof p.label === 'string') return p.label
  return ''
}

function formatWhen(iso: string): string {
  return new Date(iso).toLocaleString(locale.value, { day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit' })
}
</script>

<template>
  <UPopover v-model:open="open">
    <UButton
      color="neutral"
      variant="ghost"
      size="sm"
      icon="i-lucide-bell"
      :aria-label="unread > 0 ? t('notifications.ariaUnread', { count: unread }) : t('notifications.aria')"
      class="relative"
    >
      <template #trailing>
        <UBadge
          v-if="unread > 0"
          color="primary"
          size="sm"
          class="absolute -top-1 -right-1 pointer-events-none"
        >
          {{ unreadBadge(unread) }}
        </UBadge>
      </template>
    </UButton>

    <template #content>
      <div class="w-80 max-h-96 overflow-y-auto p-2">
        <div class="flex items-center justify-between px-2 py-1">
          <p class="text-sm font-semibold">{{ t('notifications.title') }}</p>
          <UButton
            v-if="unread > 0"
            size="xs"
            variant="ghost"
            :loading="markingAll"
            @click="markAllRead"
          >
            {{ t('notifications.markAllRead') }}
          </UButton>
        </div>

        <p v-if="notifications.length === 0" class="px-2 py-6 text-sm text-muted text-center">
          {{ t('notifications.empty') }}
        </p>

        <ul v-else class="flex flex-col">
          <li v-for="notification in notifications" :key="notification.id">
            <button
              type="button"
              class="w-full text-left rounded-lg px-2 py-2 hover:bg-elevated/60 flex gap-2.5 items-start"
              @click="openNotification(notification)"
            >
              <span
                class="mt-1.5 size-2 rounded-full shrink-0"
                :class="notification.readAt ? 'bg-transparent' : 'bg-primary'"
                aria-hidden="true"
              />
              <span class="min-w-0">
                <span class="block text-sm" :class="notification.readAt ? 'text-muted' : 'font-medium'">
                  {{ label(notification) }}
                </span>
                <span v-if="detail(notification)" class="block text-xs text-muted truncate">
                  {{ detail(notification) }}
                </span>
                <span class="block text-xs text-dimmed mt-0.5">{{ formatWhen(notification.occurredOn) }}</span>
              </span>
            </button>
          </li>
        </ul>
      </div>
    </template>
  </UPopover>
</template>
