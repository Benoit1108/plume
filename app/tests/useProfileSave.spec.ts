import { beforeEach, describe, expect, it, vi } from 'vitest'
import { computed, ref } from 'vue'

const update = vi.fn()
const toastAdd = vi.fn()
const invalidateQueries = vi.fn()
const profileData = ref<{ weeklyGoal: number } | undefined>({ weeklyGoal: 5 })

vi.stubGlobal('ref', ref)
vi.stubGlobal('computed', computed)
vi.stubGlobal('useI18n', () => ({ t: (key: string) => key }))
vi.stubGlobal('useProfile', () => ({ get: vi.fn(), update }))
vi.stubGlobal('useToast', () => ({ add: toastAdd }))
vi.stubGlobal('useQueryClient', () => ({ invalidateQueries }))
vi.stubGlobal('useQuery', () => ({ data: profileData, isPending: ref(false) }))
vi.stubGlobal('queryKeys', { profile: ['profile'] })
vi.stubGlobal('errorToastTitle', () => 'erreur')

const { useProfileSave } = await import('../composables/account/useProfileSave')

describe('useProfileSave', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    profileData.value = { weeklyGoal: 5 }
  })

  it('n\'envoie QUE les champs de la section (PATCH partiel — les autres onglets ne bougent pas)', async () => {
    const { save } = useProfileSave()

    await save({ bio: 'Traductrice EN>FR' })

    expect(update).toHaveBeenCalledWith({ bio: 'Traductrice EN>FR' })
    expect(invalidateQueries).toHaveBeenCalledWith({ queryKey: ['profile'] })
    expect(toastAdd).toHaveBeenCalledWith(expect.objectContaining({ color: 'success' }))
  })

  it('signale l\'échec sans laisser le bouton en chargement', async () => {
    update.mockRejectedValueOnce(new Error('422'))
    const { save, saving } = useProfileSave()

    await save({ weeklyGoal: 7 })

    expect(toastAdd).toHaveBeenCalledWith(expect.objectContaining({ color: 'error' }))
    expect(saving.value).toBe(false)
  })

  it('ignore un second envoi tant que le premier est en cours', async () => {
    let resolve = (): void => {}
    update.mockImplementationOnce(() => new Promise<void>((r) => { resolve = r }))
    const { save } = useProfileSave()

    const first = save({ weeklyGoal: 7 })
    await save({ weeklyGoal: 8 }) // ignoré : un envoi est déjà en vol
    resolve()
    await first

    expect(update).toHaveBeenCalledTimes(1)
  })

  it('expose le profil chargé, ou null tant qu\'il ne l\'est pas', () => {
    const { profile } = useProfileSave()
    expect(profile.value).toEqual({ weeklyGoal: 5 })

    profileData.value = undefined
    expect(profile.value).toBeNull()
  })
})
