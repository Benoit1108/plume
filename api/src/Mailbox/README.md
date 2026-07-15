# Mailbox — Passerelle email (Supporting technique)

Envoi + captation des réponses via OAuth **Gmail** (API Gmail) et **Outlook** (Microsoft
Graph), derrière un même jeu de ports. Cf. ADR-0007 (passerelle), ADR-0016 (chiffrement des
tokens), ADR-0017 (captation des réponses).

## Domaine (`Domain/`)
- **`ConnectedMailbox`** (agrégat) : `MailboxId`, `provider` (`MailProviderName` : `GMAIL` | `OUTLOOK`),
  `emailAddress`, tokens `EncryptedToken` (jamais en clair), `status` (`CONNECTED` | `ERROR` | `REVOKED`),
  curseur de relève. Une par tenant en V1 (invariant levable). Events `MailboxConnected`/`MailboxRevoked`/`MailboxSyncFailed`.
- **`OutboundMessage`** (agrégat) : envoi (`SENDING` → `SENT` | `FAILED`, gardes anti-redélivrance),
  `threadKey` (fil provider). Events `EmailSendRequested`/`EmailSent`/`EmailSendFailed`/`ReplyCaptured`.

## Application (`Application/`)
- Ports : `MailboxConnector` (OAuth), `MailSender` (envoi), `ReplyFetcher` (relève) — chacun via
  un **registre** (`…Registry`) qui route selon le fournisseur de la boîte ; `TokenCipher` (chiffrement) ;
  `DraftGateway`/`RecipientResolver`/`OpenThreads` (frontières vers Drafting/Prospection, tenant explicite).
- Commandes : `ConnectMailbox`, `RevokeMailbox`, `SendDraft`, `MarkEmailSent`/`MarkEmailFailed`, `FetchReplies`.

## Infrastructure (`Infrastructure/`)
- OAuth : `GmailConnector`/`OutlookConnector` + `FakeMailboxConnector` (défaut sans identifiants),
  `OAuthStateCodec` (state anti-CSRF signé, lié tenant + provider).
- Envoi/relève : `GmailMailSender`/`OutlookMailSender`, `GmailReplyFetcher`/`OutlookReplyFetcher`,
  variantes `Fake*` ; `OAuthAccessTokenMinter` (frappe d'access token, une instance par fournisseur).
- Crypto : `SodiumTokenCipher` (secretbox, clé `MAILBOX_ENCRYPTION_KEY`).
- Les réponses captées deviennent `ReplyCaptured` → la politique Prospection `RecordReplyOnReplyCaptured`
  applique `Lead::recordReply()` (idempotent) ; l'envoi abouti (`EmailSent`) fait avancer la piste (D3).
