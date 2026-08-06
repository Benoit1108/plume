import type { ComputedRef, Ref } from 'vue'
import type { Profile } from '~/types/domain/leads'

type ProfilePatch = Parameters<ReturnType<typeof useProfile>['update']>[0]

/**
 * Enregistrement d'UNE section du profil (onglets Réglages). Le PATCH est partiel (merge-patch :
 * le serveur ne touche qu'aux champs envoyés), donc chaque onglet enregistre ce qu'il affiche —
 * jamais les champs d'un autre onglet, même s'ils ont été modifiés puis abandonnés.
 *
 * Factorise l'état d'envoi, l'invalidation du cache et les toasts, identiques partout.
 */
export function useProfileSave(): {
  profile: ComputedRef<Profile | null>
  loading: Ref<boolean>
  saving: Ref<boolean>
  save: (patch: ProfilePatch) => Promise<void>
} {
  const { t } = useI18n()
  const profileApi = useProfile()
  const toast = useToast()
  const queryClient = useQueryClient()

  // Même clé de cache pour tous les onglets : une seule requête réseau, partagée.
  const { data, isPending } = useQuery({ queryKey: queryKeys.profile, queryFn: () => profileApi.get() })
  const saving = ref(false)

  async function save(patch: ProfilePatch): Promise<void> {
    if (saving.value) return
    saving.value = true
    try {
      await profileApi.update(patch)
      await queryClient.invalidateQueries({ queryKey: queryKeys.profile })
      toast.add({ title: t('settings.toasts.saved'), color: 'success' })
    }
    catch (error) {
      toast.add({ title: errorToastTitle(t, error), color: 'error' })
    }
    finally {
      saving.value = false
    }
  }

  return {
    profile: computed<Profile | null>(() => data.value ?? null),
    loading: isPending,
    saving,
    save,
  }
}
