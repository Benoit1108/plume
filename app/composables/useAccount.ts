/** Compte utilisateur (authentification) : changement de mot de passe. */
export function useAccount() {
  const api = useApi()

  return {
    /** POST /account/password — 422 si l'ancien mot de passe est faux / le nouveau invalide. */
    changePassword: (currentPassword: string, newPassword: string) =>
      api<unknown>('/api/v1/account/password', {
        method: 'POST',
        body: { currentPassword, newPassword },
      }),

    /** DELETE /account — RGPD : suppression du compte (soft-delete). 422 si le mot de passe est faux. */
    deleteAccount: (currentPassword: string) =>
      api<unknown>('/api/v1/account', {
        method: 'DELETE',
        body: { currentPassword },
      }),
  }
}
