<script setup lang="ts">
import type { CatalogEntry } from '~/types/domain/directory'

/** Annuaire suggéré : cibles de référence à ajouter au Répertoire en un clic. */
const { t } = useI18n()
const catalogApi = useDirectoryCatalog()
const { typeLabel, segmentLabel } = useDirectoryLabels()
const toast = useToast()
const queryClient = useQueryClient()

const q = ref('')
const debouncedQ = useDebounced(q, 300)
const { data, isPending, isError } = useQuery({
  queryKey: computed(() => [...queryKeys.directoryCatalog, debouncedQ.value] as const),
  queryFn: () => catalogApi.list(debouncedQ.value),
})
const entries = computed<CatalogEntry[]>(() => data.value ?? [])

const adding = ref<string | null>(null)
async function add(entry: CatalogEntry): Promise<void> {
  if (adding.value !== null) return
  adding.value = entry.id
  try {
    await catalogApi.add(entry.id)
    toast.add({ title: t('directory.catalog.added', { name: entry.name }), color: 'success' })
    await queryClient.invalidateQueries({ queryKey: queryKeys.directoryCatalog })
    await queryClient.invalidateQueries({ queryKey: queryKeys.organizations })
  }
  catch (error) {
    const conflict = (error as { statusCode?: number, response?: { status?: number } }).statusCode === 409
      || (error as { response?: { status?: number } }).response?.status === 409
    toast.add({ title: conflict ? t('directory.catalog.alreadyImported') : t('common.error'), color: conflict ? 'warning' : 'error' })
    if (conflict) await queryClient.invalidateQueries({ queryKey: queryKeys.directoryCatalog })
  }
  finally {
    adding.value = null
  }
}
</script>

<template>
  <!-- `atelier` comme le Répertoire dont on vient : la prop oubliée retombait sur `reading`
       (880 px au lieu de 1120), et la page semblait désalignée à l'arrivée. -->
  <PageContainer width="atelier">
    <PageHeader :eyebrow="t('directory.eyebrow')" :title="t('directory.catalog.title')">
      <UButton color="neutral" variant="outline" icon="i-lucide-arrow-left" to="/organizations">
        {{ t('directory.catalog.backToDirectory') }}
      </UButton>
    </PageHeader>

    <p class="mt-4 text-sm text-muted max-w-2xl">{{ t('directory.catalog.intro') }}</p>

    <UInput
      v-model="q"
      icon="i-lucide-search"
      :placeholder="t('directory.catalog.searchPlaceholder')"
      :aria-label="t('directory.catalog.searchPlaceholder')"
      class="mt-6 max-w-md"
    />

    <div v-if="isPending" role="status" class="mt-6 text-sm text-dimmed">
      <span class="sr-only">{{ t('common.loading') }}</span>
      <USkeleton class="h-24 rounded-xl" />
    </div>

    <QueryError v-else-if="isError" class="mt-6" />

    <p v-else-if="entries.length === 0" class="mt-6 text-sm text-muted">{{ t('directory.catalog.empty') }}</p>

    <ul v-else class="mt-6 border border-default rounded-xl divide-y divide-[var(--ui-border)]">
      <li v-for="entry in entries" :key="entry.id" class="p-4 flex flex-col gap-2 sm:flex-row sm:items-center">
        <div class="min-w-0 flex-1">
          <div class="flex items-center gap-2 flex-wrap">
            <span class="font-medium">{{ entry.name }}</span>
            <UBadge color="neutral" variant="soft" size="sm">{{ typeLabel(entry.type) }}</UBadge>
            <span v-if="entry.country" class="text-xs text-dimmed font-mono">{{ entry.country }}</span>
          </div>
          <div class="text-xs text-muted mt-1 flex gap-2 items-center flex-wrap">
            <span v-for="segment in entry.segments" :key="segment">{{ segmentLabel(segment) }}</span>
            <a v-if="entry.website" :href="entry.website" target="_blank" rel="noopener noreferrer" class="hover:text-primary underline">{{ t('directory.catalog.website') }}</a>
          </div>
          <p v-if="entry.note" class="text-xs text-dimmed mt-1">{{ entry.note }}</p>
        </div>
        <div class="shrink-0">
          <UBadge v-if="entry.alreadyImported" color="success" variant="soft">{{ t('directory.catalog.inDirectory') }}</UBadge>
          <UButton
            v-else
            size="sm"
            icon="i-lucide-plus"
            :loading="adding === entry.id"
            @click="() => add(entry)"
          >
            {{ t('directory.catalog.add') }}
          </UButton>
        </div>
      </li>
    </ul>
  </PageContainer>
</template>
