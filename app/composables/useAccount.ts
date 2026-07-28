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

    /** GET /account/export — RGPD : archive ZIP (JSON + CSV) de toutes les données du compte. */
    exportData: () =>
      api<Blob>('/api/v1/account/export', {
        responseType: 'blob',
        headers: { Accept: 'application/zip' },
      }),

    /** POST /account/password/forgot (PUBLIC) — demande un lien de réinitialisation. Toujours 204. */
    requestPasswordReset: (email: string) =>
      api<unknown>('/api/v1/account/password/forgot', { method: 'POST', body: { email } }),

    /** POST /account/password/reset (PUBLIC) — réinitialise via jeton. 422 si jeton/mot de passe invalide. */
    resetPassword: (token: string, newPassword: string) =>
      api<unknown>('/api/v1/account/password/reset', { method: 'POST', body: { token, newPassword } }),

    /** POST /register (PUBLIC) — inscription. 409 si email pris, 422 si entrée invalide. */
    register: (email: string, password: string, acceptTerms: boolean) =>
      api<unknown>('/api/v1/register', { method: 'POST', body: { email, password, acceptTerms } }),

    /** POST /account/verify-email (PUBLIC) — confirme l'email via jeton. 422 si invalide/expiré. */
    verifyEmail: (token: string) =>
      api<unknown>('/api/v1/account/verify-email', { method: 'POST', body: { token } }),
  }
}
