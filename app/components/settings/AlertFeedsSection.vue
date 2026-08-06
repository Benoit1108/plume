<script setup lang="ts">
import type { AlertFeed } from '~/types/domain/sourcing'

/** Réglages — sources d'annonces (M3.1b) : flux RSS relevés dans « À trier ». */
const { t } = useI18n()
const sourcingApi = useSourcing()
const toast = useToast()
const queryClient = useQueryClient()

const { data: feedsData, isPending: feedsLoading, isError: feedsFailed, refetch: refetchFeeds } = useQuery({ queryKey: queryKeys.feeds, queryFn: () => sourcingApi.feeds() })
const feeds = computed<AlertFeed[]>(() => feedsData.value ?? [])
async function refreshFeeds(): Promise<void> { await queryClient.invalidateQueries({ queryKey: queryKeys.feeds }) }

const newFeedUrl = ref('')
const newFeedLabel = ref('')
const addingFeed = ref(false)
const feedUrlValid = computed(() => /^https?:\/\/.+/i.test(newFeedUrl.value.trim()))
// Focus ramené en tête de section après un retrait (l'élément traité disparaît).
const sourcesRef = ref<HTMLElement | null>(null)
function focusSources(): void {
  void nextTick(() => sourcesRef.value?.focus())
}

async function addFeed(): Promise<void> {
  if (!feedUrlValid.value) return
  addingFeed.value = true
  try {
    await sourcingApi.addFeed({ source: 'RSS', url: newFeedUrl.value.trim(), label: newFeedLabel.value.trim() || null })
    newFeedUrl.value = ''
    newFeedLabel.value = ''
    await refreshFeeds()
    toast.add({ title: t('sourcing.feeds.added'), color: 'success' })
  }
  catch (error) {
    toast.add({ title: isConflict(error) ? t('sourcing.feeds.errorConflict') : errorToastTitle(t, error), color: 'error' })
  }
  finally {
    addingFeed.value = false
  }
}

async function toggleFeed(feed: AlertFeed): Promise<void> {
  try {
    await sourcingApi.setFeedActive(feed.id, !feed.active)
    await refreshFeeds()
    toast.add({ title: feed.active ? t('sourcing.feeds.deactivated') : t('sourcing.feeds.activated'), color: 'success' })
  }
  catch (error) {
    toast.add({ title: errorToastTitle(t, error), color: 'error' })
  }
}

// Retrait d'un flux = action destructive → confirmation, puis focus en tête de section.
const feedToRemove = ref<AlertFeed | null>(null)
const confirmRemoveFeed = computed({
  get: () => feedToRemove.value !== null,
  set: (open: boolean) => {
    if (!open) feedToRemove.value = null
  },
})

async function doRemoveFeed(): Promise<void> {
  const feed = feedToRemove.value
  if (!feed) return
  try {
    await sourcingApi.removeFeed(feed.id)
    feedToRemove.value = null
    await refreshFeeds()
    focusSources()
    toast.add({ title: t('sourcing.feeds.removed'), color: 'success' })
  }
  catch (error) {
    toast.add({ title: errorToastTitle(t, error), color: 'error' })
  }
}
</script>

<template>
  <section class="border border-default rounded-xl p-4 bg-elevated/40 max-w-2xl">
    <h2 ref="sourcesRef" tabindex="-1" class="text-sm font-semibold outline-none">{{ t('sourcing.feeds.title') }}</h2>
    <p class="text-xs text-muted mt-1">{{ t('sourcing.feeds.intro') }}</p>

    <div v-if="feedsLoading" role="status" class="mt-3 text-sm text-dimmed">
      <span class="sr-only">{{ t('common.loading') }}</span>
      {{ t('common.loading') }}
    </div>

    <!-- Une panne de chargement n'est pas « aucun flux configuré » (revue UX-P2a). -->
    <QueryError v-else-if="feedsFailed" class="mt-3" @retry="() => { void refetchFeeds() }" />

    <ul v-else-if="feeds.length" class="mt-3 flex flex-col gap-2">
      <li
        v-for="feed in feeds"
        :key="feed.id"
        class="flex items-center gap-3 flex-wrap border border-default rounded-lg p-3 bg-default"
      >
        <div class="min-w-0 flex-1">
          <div class="flex items-center gap-2">
            <span class="font-medium text-sm truncate">{{ feed.label }}</span>
            <UBadge :color="feed.active ? 'success' : 'neutral'" variant="soft" size="sm">
              {{ feed.active ? t('sourcing.feeds.active') : t('sourcing.feeds.inactive') }}
            </UBadge>
          </div>
          <p class="text-xs text-dimmed truncate">{{ feed.url }}</p>
        </div>
        <div class="flex gap-2 shrink-0">
          <UButton size="xs" variant="outline" @click="() => toggleFeed(feed)">
            {{ feed.active ? t('sourcing.feeds.deactivate') : t('sourcing.feeds.activate') }}
          </UButton>
          <UButton
            size="xs"
            variant="ghost"
            color="error"
            icon="i-lucide-trash-2"
            :aria-label="t('sourcing.feeds.remove')"
            @click="() => { feedToRemove = feed }"
          />
        </div>
      </li>
    </ul>
    <p v-else class="mt-3 text-sm text-muted">{{ t('sourcing.feeds.empty') }}</p>

    <form class="mt-4 flex flex-col gap-3" @submit.prevent="addFeed">
      <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
        <UFormField :label="t('sourcing.feeds.urlLabel')">
          <UInput v-model="newFeedUrl" class="w-full" type="url" placeholder="https://…/rss" />
        </UFormField>
        <UFormField :label="t('sourcing.feeds.labelLabel')" :hint="t('sourcing.feeds.labelHint')">
          <UInput v-model="newFeedLabel" class="w-full" maxlength="120" />
        </UFormField>
      </div>
      <div class="flex justify-end">
        <UButton type="submit" icon="i-lucide-plus" :loading="addingFeed" :disabled="!feedUrlValid">
          {{ t('sourcing.feeds.add') }}
        </UButton>
      </div>
    </form>

    <ConfirmDialog
      v-model:open="confirmRemoveFeed"
      :title="t('sourcing.feeds.confirmRemoveTitle')"
      :description="t('sourcing.feeds.confirmRemoveBody', { label: feedToRemove?.label ?? '' })"
      :confirm-label="t('sourcing.feeds.remove')"
      danger
      @confirm="doRemoveFeed"
    />
  </section>
</template>
