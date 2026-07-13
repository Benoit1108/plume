<script setup lang="ts">
import type { Organization } from '~/types/directory'

const { t } = useI18n()
const { typeLabel, segmentLabel, typeOptions } = useDirectoryLabels()
const directory = useDirectory()

// Sentinelle « tous » : Reka UI (USelect) interdit la valeur '' (réservée au placeholder).
const TYPE_ALL = 'ALL'
const type = ref(TYPE_ALL)
const q = ref('')
const qDebounced = useDebounced(q, 300)

const typeFilterItems = computed(() => [
  { value: TYPE_ALL, label: t('directory.list.typeAll') },
  ...typeOptions.value,
])

const { data: organizations, status } = await useAsyncData<Organization[]>(
  'organizations',
  () => directory.list({
    type: type.value !== TYPE_ALL ? type.value : undefined,
    q: qDebounced.value || undefined,
  }),
  { server: false, default: () => [], watch: [type, qDebounced] },
)

// server:false → au SSR le fetch n'a pas démarré (status 'idle') : rendre le même
// état « chargement » que le client à l'hydratation, sinon mismatch de branches v-if.
const loading = computed(() => status.value === 'idle' || status.value === 'pending')
</script>

<template>
  <UContainer class="py-8">
    <div class="flex items-end gap-4 flex-wrap">
      <div>
        <p class="text-[11px] uppercase tracking-widest text-dimmed font-semibold">{{ t('directory.eyebrow') }}</p>
        <h1 class="font-serif text-3xl font-semibold mt-1">{{ t('directory.title') }}</h1>
      </div>
      <div class="ml-auto flex gap-2">
        <UButton color="neutral" variant="outline" icon="i-lucide-upload" to="/organizations/import">
          {{ t('directory.list.importCsv') }}
        </UButton>
        <UButton icon="i-lucide-plus" to="/organizations/new">{{ t('directory.list.newOrganization') }}</UButton>
      </div>
    </div>

    <div class="flex gap-2 flex-wrap mt-6">
      <USelect
        v-model="type"
        :items="typeFilterItems"
        value-key="value"
        label-key="label"
        :aria-label="t('directory.list.typeFilter')"
        class="w-44"
      />
      <UInput v-model="q" icon="i-lucide-search" :placeholder="t('directory.list.searchPlaceholder')" class="flex-1 min-w-48" />
    </div>

    <div class="mt-4">
      <div v-if="loading" class="py-12 text-center text-dimmed">{{ t('common.loading') }}</div>

      <div v-else-if="!organizations.length" class="py-12 text-center text-muted border border-default rounded-xl">
        {{ t('directory.list.empty') }}
      </div>

      <template v-else>
        <!-- Desktop : tableau (le nom est un vrai lien -> accessible clavier) -->
        <div class="hidden sm:block overflow-x-auto border border-default rounded-xl bg-elevated/40">
          <table class="w-full text-sm min-w-[640px]">
            <thead>
              <tr class="text-left text-[11px] uppercase tracking-wider text-dimmed">
                <th class="px-4 py-3 font-semibold border-b border-default">{{ t('directory.list.colOrganization') }}</th>
                <th class="px-4 py-3 font-semibold border-b border-default">{{ t('directory.list.colSegments') }}</th>
                <th class="px-4 py-3 font-semibold border-b border-default">{{ t('directory.list.colLanguages') }}</th>
                <th class="px-4 py-3 font-semibold border-b border-default">{{ t('directory.list.colContacts') }}</th>
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
                  <NuxtLink
                    :to="`/organizations/${org.id}`"
                    class="font-semibold rounded-sm focus-visible:outline-2 focus-visible:outline-primary"
                    :aria-label="t('directory.list.openOrganization', { name: org.name })"
                    @click.stop
                  >{{ org.name }}</NuxtLink>
                  <div class="text-xs text-dimmed">{{ typeLabel(org.type) }}</div>
                </td>
                <td class="px-4 py-3 border-b border-default">
                  <div class="flex gap-1.5 flex-wrap">
                    <UBadge v-for="s in org.segments" :key="s" color="neutral" variant="soft" size="sm">
                      {{ segmentLabel(s) }}
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
                  <template v-if="org.doNotContact">
                    <UIcon name="i-lucide-flag" class="text-error" aria-hidden="true" />
                    <span class="sr-only">{{ t('directory.doNotContact.flag') }}</span>
                  </template>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Mobile : cartes -->
        <ul class="sm:hidden flex flex-col gap-3">
          <li v-for="org in organizations" :key="org.id">
            <NuxtLink
              :to="`/organizations/${org.id}`"
              class="block w-full text-left border border-default rounded-xl p-4 bg-elevated/40 flex flex-col gap-2 focus-visible:outline-2 focus-visible:outline-primary"
            >
              <div class="flex items-center gap-2">
                <span class="font-semibold">{{ org.name }}</span>
                <template v-if="org.doNotContact">
                  <UIcon name="i-lucide-flag" class="text-error ml-auto shrink-0" aria-hidden="true" />
                  <span class="sr-only">{{ t('directory.doNotContact.flag') }}</span>
                </template>
              </div>
              <div class="text-xs text-dimmed">
                {{ typeLabel(org.type) }} · {{ t('directory.list.contactsCount', { count: org.contacts?.length ?? 0 }, org.contacts?.length ?? 0) }}
              </div>
              <div v-if="org.segments.length" class="flex gap-1.5 flex-wrap">
                <UBadge v-for="s in org.segments" :key="s" color="neutral" variant="soft" size="sm">
                  {{ segmentLabel(s) }}
                </UBadge>
              </div>
              <div v-if="org.workingLanguages.length" class="flex gap-1 flex-wrap">
                <LangStamp v-for="l in org.workingLanguages" :key="l" :code="l" />
              </div>
            </NuxtLink>
          </li>
        </ul>
      </template>
    </div>
  </UContainer>
</template>
