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

// Clé réactive : le filtre (type + recherche) fait partie de la clé → refetch au changement.
const { data: orgsData, isPending: loading } = useQuery({
  queryKey: computed(() => ['organizations', type.value, qDebounced.value]),
  queryFn: () => directory.list({
    type: type.value !== TYPE_ALL ? type.value : undefined,
    q: qDebounced.value || undefined,
  }),
})
const organizations = computed<Organization[]>(() => orgsData.value ?? [])
</script>

<template>
  <PageContainer width="atelier">
    <PageHeader :eyebrow="t('directory.eyebrow')" :title="t('directory.title')">
      <template #actions>
        <UButton color="neutral" variant="outline" icon="i-lucide-upload" to="/organizations/import">
          {{ t('directory.list.importCsv') }}
        </UButton>
        <UButton icon="i-lucide-plus" to="/organizations/new">{{ t('directory.list.newOrganization') }}</UButton>
      </template>
    </PageHeader>

    <div class="flex gap-2 flex-wrap">
      <USelect
        v-model="type"
        :items="typeFilterItems"
        value-key="value"
        label-key="label"
        :aria-label="t('directory.list.typeFilter')"
        class="w-44"
      />
      <UInput v-model="q" icon="i-lucide-search" :placeholder="t('directory.list.searchPlaceholder')" :aria-label="t('directory.list.searchPlaceholder')" class="flex-1 min-w-48" />
    </div>

    <div class="mt-4">
      <div v-if="loading" role="status" class="flex flex-col gap-2">
        <span class="sr-only">{{ t('common.loading') }}</span>
        <USkeleton v-for="i in 6" :key="i" class="h-16 rounded-xl" />
      </div>

      <div v-else-if="!organizations.length" class="py-16 flex flex-col items-center gap-3 text-center border border-default rounded-xl">
        <p class="text-muted max-w-md">{{ t('directory.list.empty') }}</p>
        <UButton icon="i-lucide-plus" to="/organizations/new">{{ t('directory.new.title') }}</UButton>
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
  </PageContainer>
</template>
