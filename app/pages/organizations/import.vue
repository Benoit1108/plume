<script setup lang="ts">
import type { ImportResult } from '~/types/directory'

const { t } = useI18n()
const directory = useDirectory()
const queryClient = useQueryClient()

const fileName = ref('')
const content = ref('')
const loading = ref(false)
const error = ref('')
const result = ref<ImportResult | null>(null)

async function onFile(event: Event): Promise<void> {
  const input = event.target as HTMLInputElement
  const file = input.files?.[0]
  if (!file) return
  fileName.value = file.name
  content.value = await file.text()
  result.value = null
  error.value = ''
}

async function submit(): Promise<void> {
  if (!content.value) return
  loading.value = true
  error.value = ''
  result.value = null
  try {
    result.value = await directory.importCsv(content.value)
    // Sinon la liste (et le sélecteur de leads/new) restent en cache → l'import paraît sans effet.
    await queryClient.invalidateQueries({ queryKey: queryKeys.organizations })
  }
  catch (e) {
    const err = e as { data?: { detail?: string, 'hydra:description'?: string } }
    error.value = err.data?.detail ?? err.data?.['hydra:description'] ?? t('directory.import.error')
  }
  finally {
    loading.value = false
  }
}
</script>

<template>
  <PageContainer width="form">
    <PageHeader back-to="/organizations" :back-label="t('directory.title')" :title="t('directory.import.title')">
      <template #subtitle>
        <p class="mt-1 text-sm text-muted">{{ t('directory.import.intro') }}</p>
      </template>
    </PageHeader>

    <div class="border border-default rounded-xl p-4 bg-elevated/40 text-sm">
      <p class="font-medium mb-2">{{ t('directory.import.columnsTitle') }}</p>
      <ul class="text-muted space-y-1">
        <li>
          <span class="text-highlighted font-medium">{{ t('directory.import.columnName') }}</span>
          <span class="text-dimmed"> {{ t('directory.import.columnNameRequired') }}</span>
        </li>
        <li>
          <span class="text-highlighted font-medium">{{ t('directory.import.columnType') }}</span>
          — {{ t('directory.import.columnTypeHint') }}
        </li>
        <li>{{ t('directory.import.columnOthers') }}</li>
        <li>{{ t('directory.import.columnContact') }}</li>
      </ul>
    </div>

    <div class="flex items-center gap-3 flex-wrap mt-6">
      <label class="inline-flex items-center gap-2 px-4 py-2 rounded-md border border-default bg-default text-sm font-medium cursor-pointer hover:bg-elevated">
        <UIcon name="i-lucide-file-up" aria-hidden="true" />
        {{ t('directory.import.chooseFile') }}
        <input type="file" accept=".csv,text/csv,text/plain" class="hidden" @change="onFile">
      </label>
      <span v-if="fileName" class="text-sm text-muted font-mono truncate">{{ fileName }}</span>
    </div>

    <UAlert v-if="error" color="error" variant="subtle" :description="error" class="mt-4" />

    <div class="mt-6">
      <UButton :loading="loading" :disabled="!content" icon="i-lucide-upload" @click="submit">
        {{ t('directory.import.run') }}
      </UButton>
    </div>

    <!-- Récapitulatif -->
    <div v-if="result" class="mt-8">
      <div class="grid grid-cols-3 gap-3">
        <div class="border border-default rounded-lg p-4 text-center">
          <div class="text-2xl font-semibold text-primary tabular-nums">{{ result.imported }}</div>
          <div class="text-xs text-dimmed uppercase tracking-wide mt-1">{{ t('directory.import.imported') }}</div>
        </div>
        <div class="border border-default rounded-lg p-4 text-center">
          <div class="text-2xl font-semibold text-muted tabular-nums">{{ result.skipped }}</div>
          <div class="text-xs text-dimmed uppercase tracking-wide mt-1">{{ t('directory.import.skipped') }}</div>
        </div>
        <div class="border border-default rounded-lg p-4 text-center">
          <div class="text-2xl font-semibold tabular-nums" :class="result.failed ? 'text-error' : 'text-muted'">{{ result.failed }}</div>
          <div class="text-xs text-dimmed uppercase tracking-wide mt-1">{{ t('directory.import.failed') }}</div>
        </div>
      </div>

      <p v-if="result.skipped" class="text-xs text-dimmed mt-2">{{ t('directory.import.skippedHint') }}</p>

      <div v-if="result.errors.length" class="mt-4 border border-default rounded-lg divide-y divide-[var(--ui-border)]">
        <div v-for="(err, i) in result.errors" :key="i" class="px-4 py-2 text-sm flex gap-3">
          <span class="font-mono text-dimmed shrink-0">{{ t('directory.import.lineAbbr', { line: err.line }) }}</span>
          <span class="text-muted">{{ err.message }}</span>
        </div>
      </div>

      <div class="mt-6 flex gap-2">
        <UButton to="/organizations" icon="i-lucide-arrow-right" trailing>{{ t('directory.import.seeDirectory') }}</UButton>
      </div>
    </div>
  </PageContainer>
</template>
