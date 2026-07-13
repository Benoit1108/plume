<script setup lang="ts">
import type { ImportResult } from '~/types/directory'

const directory = useDirectory()

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
  }
  catch (e) {
    const err = e as { data?: { detail?: string, 'hydra:description'?: string } }
    error.value = err.data?.detail ?? err.data?.['hydra:description'] ?? 'Import impossible — vérifie le format du fichier.'
  }
  finally {
    loading.value = false
  }
}
</script>

<template>
  <UContainer class="py-8 max-w-2xl">
    <UButton variant="link" icon="i-lucide-arrow-left" to="/organizations" class="px-0 mb-2">Répertoire</UButton>
    <h1 class="font-serif text-3xl font-semibold mb-2">Importer des organisations</h1>
    <p class="text-sm text-muted mb-6">
      Déposez un fichier CSV. Le séparateur (<code>,</code> ou <code>;</code>) est détecté automatiquement.
    </p>

    <div class="border border-default rounded-xl p-4 bg-elevated/40 text-sm">
      <p class="font-medium mb-2">Colonnes reconnues</p>
      <ul class="text-muted space-y-1">
        <li><span class="text-highlighted font-medium">nom</span> <span class="text-dimmed">(obligatoire)</span></li>
        <li><span class="text-highlighted font-medium">type</span> — éditeur, labo A/V, agence, autre</li>
        <li><span class="text-highlighted font-medium">pays</span> (code ISO, ex. FR), <span class="text-highlighted font-medium">langues</span> (ex. « en fr »), <span class="text-highlighted font-medium">segments</span>, <span class="text-highlighted font-medium">site</span>, <span class="text-highlighted font-medium">notes</span></li>
        <li><span class="text-highlighted font-medium">contact</span>, <span class="text-highlighted font-medium">email</span>, <span class="text-highlighted font-medium">rôle</span>, <span class="text-highlighted font-medium">téléphone</span> — contact principal, optionnel</li>
      </ul>
    </div>

    <div class="flex items-center gap-3 flex-wrap mt-6">
      <label class="inline-flex items-center gap-2 px-4 py-2 rounded-md border border-default bg-default text-sm font-medium cursor-pointer hover:bg-elevated">
        <UIcon name="i-lucide-file-up" />
        Choisir un fichier CSV
        <input type="file" accept=".csv,text/csv,text/plain" class="hidden" @change="onFile">
      </label>
      <span v-if="fileName" class="text-sm text-muted font-mono truncate">{{ fileName }}</span>
    </div>

    <UAlert v-if="error" color="error" variant="subtle" :description="error" class="mt-4" />

    <div class="mt-6">
      <UButton :loading="loading" :disabled="!content" icon="i-lucide-upload" @click="submit">
        Lancer l'import
      </UButton>
    </div>

    <!-- Récapitulatif -->
    <div v-if="result" class="mt-8">
      <div class="grid grid-cols-3 gap-3">
        <div class="border border-default rounded-lg p-4 text-center">
          <div class="text-2xl font-semibold text-primary tabular-nums">{{ result.imported }}</div>
          <div class="text-xs text-dimmed uppercase tracking-wide mt-1">Importées</div>
        </div>
        <div class="border border-default rounded-lg p-4 text-center">
          <div class="text-2xl font-semibold text-muted tabular-nums">{{ result.skipped }}</div>
          <div class="text-xs text-dimmed uppercase tracking-wide mt-1">Ignorées</div>
        </div>
        <div class="border border-default rounded-lg p-4 text-center">
          <div class="text-2xl font-semibold tabular-nums" :class="result.failed ? 'text-error' : 'text-muted'">{{ result.failed }}</div>
          <div class="text-xs text-dimmed uppercase tracking-wide mt-1">En échec</div>
        </div>
      </div>

      <p v-if="result.skipped" class="text-xs text-dimmed mt-2">
        Les organisations dont le nom existe déjà sont ignorées (pas de doublon).
      </p>

      <div v-if="result.errors.length" class="mt-4 border border-default rounded-lg divide-y divide-[var(--ui-border)]">
        <div v-for="(err, i) in result.errors" :key="i" class="px-4 py-2 text-sm flex gap-3">
          <span class="font-mono text-dimmed shrink-0">L.{{ err.line }}</span>
          <span class="text-muted">{{ err.message }}</span>
        </div>
      </div>

      <div class="mt-6 flex gap-2">
        <UButton to="/organizations" icon="i-lucide-arrow-right" trailing>Voir le répertoire</UButton>
      </div>
    </div>
  </UContainer>
</template>
