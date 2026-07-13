<script setup lang="ts">
import type { Organization } from '~/types/directory'

const directory = useDirectory()

const type = ref('')
const q = ref('')

const { data: organizations, status } = await useAsyncData<Organization[]>(
  'organizations',
  () => directory.list({ type: type.value || undefined, q: q.value || undefined }),
  { server: false, default: () => [], watch: [type, q] },
)

const typeLabels: Record<string, string> = {
  PUBLISHER: 'Éditeur',
  AV_STUDIO: 'Labo A/V',
  AGENCY: 'Agence',
  OTHER: 'Autre',
}
const segmentLabels: Record<string, string> = {
  PUBLISHING: 'Édition',
  AUDIOVISUAL: 'Audiovisuel',
  TECHNICAL: 'Technique',
  OTHER: 'Autre',
}

const selectClass = 'text-sm rounded-md border border-default bg-default text-default px-3 py-2'
</script>

<template>
  <UContainer class="py-8">
    <div class="flex items-end gap-4 flex-wrap">
      <div>
        <p class="text-[11px] uppercase tracking-widest text-dimmed font-semibold">Atelier · cibles</p>
        <h1 class="font-serif text-3xl font-semibold mt-1">Répertoire</h1>
      </div>
      <div class="ml-auto flex gap-2">
        <UButton color="neutral" variant="outline" icon="i-lucide-upload" disabled>Importer CSV</UButton>
        <UButton icon="i-lucide-plus" to="/organizations/new">Organisation</UButton>
      </div>
    </div>

    <div class="flex gap-2 flex-wrap mt-6">
      <select v-model="type" :class="selectClass" aria-label="Filtrer par type">
        <option value="">Type — tous</option>
        <option value="PUBLISHER">Éditeur</option>
        <option value="AV_STUDIO">Labo A/V</option>
        <option value="AGENCY">Agence</option>
        <option value="OTHER">Autre</option>
      </select>
      <UInput v-model="q" icon="i-lucide-search" placeholder="Rechercher une organisation…" class="flex-1 min-w-48" />
    </div>

    <div class="mt-4 overflow-x-auto border border-default rounded-xl bg-elevated/40">
      <table class="w-full text-sm min-w-[640px]">
        <thead>
          <tr class="text-left text-[11px] uppercase tracking-wider text-dimmed">
            <th class="px-4 py-3 font-semibold border-b border-default">Organisation</th>
            <th class="px-4 py-3 font-semibold border-b border-default">Segments</th>
            <th class="px-4 py-3 font-semibold border-b border-default">Langues</th>
            <th class="px-4 py-3 font-semibold border-b border-default">Contacts</th>
            <th class="px-4 py-3 font-semibold border-b border-default" />
          </tr>
        </thead>
        <tbody>
          <tr
            v-for="org in organizations"
            :key="org.id"
            class="hover:bg-elevated cursor-pointer"
            @click="navigateTo(`/organizations/${org.id}`)"
          >
            <td class="px-4 py-3 border-b border-default">
              <div class="font-semibold">{{ org.name }}</div>
              <div class="text-xs text-dimmed">{{ typeLabels[org.type] ?? org.type }}</div>
            </td>
            <td class="px-4 py-3 border-b border-default">
              <div class="flex gap-1.5 flex-wrap">
                <UBadge v-for="s in org.segments" :key="s" color="neutral" variant="soft" size="sm">
                  {{ segmentLabels[s] ?? s }}
                </UBadge>
              </div>
            </td>
            <td class="px-4 py-3 border-b border-default">
              <div class="flex gap-1 flex-wrap">
                <LangStamp v-for="l in org.workingLanguages" :key="l" :code="l" />
              </div>
            </td>
            <td class="px-4 py-3 border-b border-default font-mono text-muted tabular-nums">
              {{ org.contacts?.length ?? 0 }}
            </td>
            <td class="px-4 py-3 border-b border-default text-right">
              <UIcon v-if="org.doNotContact" name="i-lucide-flag" class="text-error" />
            </td>
          </tr>

          <tr v-if="status !== 'pending' && !organizations.length">
            <td colspan="5" class="px-4 py-12 text-center text-muted">
              Aucune organisation. Créez votre première cible.
            </td>
          </tr>
          <tr v-else-if="status === 'pending'">
            <td colspan="5" class="px-4 py-12 text-center text-dimmed">Chargement…</td>
          </tr>
        </tbody>
      </table>
    </div>
  </UContainer>
</template>
