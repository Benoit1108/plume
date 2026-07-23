<script setup lang="ts">
import type { Profile } from '~/types/leads'

const { t } = useI18n()
const auth = useAuthStore()
const profileApi = useProfile()
const accountApi = useAccount()
const toast = useToast()

const queryClient = useQueryClient()
const { data: profileData, isPending: loading } = useQuery({ queryKey: queryKeys.profile, queryFn: () => profileApi.get() })
const profile = computed<Profile | null>(() => profileData.value ?? null)
async function refresh(): Promise<void> { await queryClient.invalidateQueries({ queryKey: queryKeys.profile }) }

// --- Nom d'affichage ---
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
    await refresh()
    toast.add({ title: t('account.toasts.identitySaved'), color: 'success' })
  }
  catch (error) {
    toast.add({ title: errorToastTitle(t, error), color: 'error' })
  }
  finally {
    savingIdentity.value = false
  }
}

// --- Mot de passe ---
const currentPassword = ref('')
const newPassword = ref('')
const confirmPassword = ref('')
const changingPassword = ref(false)

const passwordValid = computed(() =>
  currentPassword.value !== ''
  && newPassword.value.length >= 8
  && newPassword.value === confirmPassword.value,
)

async function changePassword(): Promise<void> {
  if (newPassword.value.length < 8) {
    toast.add({ title: t('account.errors.tooShort'), color: 'error' })
    return
  }
  if (newPassword.value !== confirmPassword.value) {
    toast.add({ title: t('account.errors.mismatch'), color: 'error' })
    return
  }
  changingPassword.value = true
  try {
    await accountApi.changePassword(currentPassword.value, newPassword.value)
    currentPassword.value = ''
    newPassword.value = ''
    confirmPassword.value = ''
    toast.add({ title: t('account.toasts.passwordChanged'), color: 'success' })
  }
  catch (error) {
    const detail = (error as { data?: { detail?: string } }).data?.detail
    const key = detail === 'invalid_current_password'
      ? 'account.errors.invalidCurrent'
      : detail === 'invalid_new_password'
        ? 'account.errors.tooShort'
        : 'account.errors.generic'
    toast.add({ title: t(key), color: 'error' })
  }
  finally {
    changingPassword.value = false
  }
}
</script>

<template>
  <PageContainer width="atelier">
    <PageHeader :eyebrow="t('account.eyebrow')" :title="t('account.title')" />

    <div v-if="loading" role="status" class="mt-6 flex flex-col gap-4 max-w-2xl">
      <span class="sr-only">{{ t('common.loading') }}</span>
      <USkeleton class="h-40 rounded-xl" />
      <USkeleton class="h-56 rounded-xl" />
    </div>

    <div v-else class="mt-6 flex flex-col gap-8 max-w-2xl">
      <!-- Nom d'affichage -->
      <form class="border border-default rounded-xl p-4 bg-elevated/40 flex flex-col gap-4" @submit.prevent="saveIdentity">
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

      <!-- Connexion + mot de passe -->
      <section class="border border-default rounded-xl p-4 bg-elevated/40 flex flex-col gap-4">
        <p class="text-sm font-semibold">{{ t('account.login.title') }}</p>
        <UFormField :label="t('account.login.email')" :hint="t('account.login.emailHint')">
          <UInput :model-value="auth.email ?? ''" disabled readonly class="w-full" />
        </UFormField>

        <form class="flex flex-col gap-4 border-t border-default pt-4" @submit.prevent="changePassword">
          <p class="text-sm font-semibold">{{ t('account.password.title') }}</p>
          <UFormField :label="t('account.password.current')">
            <UInput v-model="currentPassword" type="password" autocomplete="current-password" class="w-full" />
          </UFormField>
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <UFormField :label="t('account.password.new')" :hint="t('account.password.hint')">
              <UInput v-model="newPassword" type="password" autocomplete="new-password" class="w-full" />
            </UFormField>
            <UFormField :label="t('account.password.confirm')">
              <UInput v-model="confirmPassword" type="password" autocomplete="new-password" class="w-full" />
            </UFormField>
          </div>
          <div class="flex justify-end">
            <UButton type="submit" :loading="changingPassword" :disabled="!passwordValid">
              {{ t('account.password.submit') }}
            </UButton>
          </div>
        </form>
      </section>
    </div>
  </PageContainer>
</template>
