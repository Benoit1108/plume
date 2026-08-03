/**
 * Déclenche le téléchargement d'un Blob (export CSV / archive RGPD…) côté navigateur.
 * Factorise le motif ancre-DOM + clic + révocation différée de l'URL objet (revue santé : dupliqué
 * dans dashboard/account/admin). La révocation est différée (certains navigateurs annulent un
 * téléchargement si l'URL est révoquée trop tôt).
 */
export function downloadBlob(blob: Blob, filename: string): void {
  const url = URL.createObjectURL(blob)
  const link = document.createElement('a')
  link.href = url
  link.download = filename
  document.body.appendChild(link)
  link.click()
  link.remove()
  setTimeout(() => { URL.revokeObjectURL(url) }, 60_000)
}
