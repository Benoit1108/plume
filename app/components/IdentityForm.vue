<script setup lang="ts">
import type { Profile } from '~/types/leads'

/** Compte — nom d'affichage (prénom / nom), matière première des signatures et du profil. */
const { t } = useI18n()
const profileApi = useProfile()
const toast = useToast()
const queryClient = useQueryClient()

const { data: profileData, isPending: loading } = useQuery({ queryKey: queryKeys.profile, queryFn: () => profileApi.get() })
const profile = computed<Profile | null>(() => profileData.value ?? null)

const firstName = ref('')
const lastName = ref('')
watch(profile, (value) => {
  if (!value) return
  firstName.value = value.firstName ?? ''
  lastName.value = value.lastName ?? ''
}, { immediate: true })

const savingIdentity = ref(false)
async function saveIdentity(): Promise<void> {
  savingIdentity.value = true
  try {
    await profileApi.update({
      firstName: firstName.value.trim() || null,
      lastName: lastName.value.trim() || null,
    })
    await queryClient.invalidateQueries({ queryKey: queryKeys.profile })
    toast.add({ title: t('account.toasts.identitySaved'), color: 'success' })
  }
  catch (error) {
    toast.add({ title: errorToastTitle(t, error), color: 'error' })
  }
  finally {
    savingIdentity.value = false
  }
}
</script>

<template>
  <USkeleton v-if="loading" class="h-40 rounded-xl" />
  <form v-else class="border border-default rounded-xl p-4 bg-elevated/40 flex flex-col gap-4" @submit.prevent="saveIdentity">
    <div>
      <p class="text-sm font-semibold">{{ t('account.identity.title') }}</p>
      <p class="text-xs text-muted mt-1">{{ t('account.identity.intro') }}</p>
    </div>
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
      <UFormField :label="t('account.identity.firstName')">
        <UInput v-model="firstName" class="w-full" maxlength="100" autocomplete="given-name" />
      </UFormField>
      <UFormField :label="t('account.identity.lastName')">
        <UInput v-model="lastName" class="w-full" maxlength="100" autocomplete="family-name" />
      </UFormField>
    </div>
    <div class="flex justify-end">
      <UButton type="submit" :loading="savingIdentity">{{ t('actions.save') }}</UButton>
    </div>
  </form>
</template>
