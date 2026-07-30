import QRCode from 'qrcode'

/**
 * Rendu d'un QR code en data-URL PNG, côté client (SPA). Utilisé pour l'enrôlement 2FA : on encode
 * l'URI `otpauth://` pour que l'app d'authentification l'ajoute d'un scan, sans saisie manuelle.
 * Le rendu est 100 % local (aucun service tiers) — la donnée sensible ne quitte pas le navigateur.
 */
export function useQrCode() {
  return {
    toDataUrl: (text: string): Promise<string> =>
      QRCode.toDataURL(text, { margin: 1, width: 200, errorCorrectionLevel: 'M' }),
  }
}
