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
- Event `AlertEmailReceived` (M3.2) : un email d'alerte lu sous le label dédié — **langage publié**
  vers le Sourcing (émis par le handler `FetchAlertEmails`).

## Application (`Application/`)
- Ports : `MailboxConnector` (OAuth), `MailSender` (envoi), `ReplyFetcher` (relève) — chacun via
  un **registre** (`…Registry`) qui route selon le fournisseur de la boîte ; `AlertEmailFetcher`
  (relève des alertes sous un label dédié, M3.2) **aussi via `AlertEmailFetcherRegistry`** ;
  `TokenCipher` (chiffrement) ;
  `DraftGateway`/`RecipientResolver`/`OpenThreads` (frontières vers Drafting/Prospection, tenant explicite).
- Commandes : `ConnectMailbox`, `RevokeMailbox`, `SendDraft`, `MarkEmailSent`/`MarkEmailFailed`,
  `FetchReplies`, `FetchAlertEmails` (M3.2).

## Relève des alertes (M3.2)
- `FetchAlertEmails` lit **uniquement le label dédié « Plume/Alertes »** (minimisation, ADR-0017
  amendé) via le port `AlertEmailFetcher` et publie un `AlertEmailReceived` par email — le Sourcing
  décide de l'ingestion (jamais d'appel direct inter-contextes). Échec = no-op silencieux (canal
  secondaire), le Scheduler repasse.
- Registre par fournisseur : **`GmailAlertEmailFetcher` réel** (API Gmail HTTP fine — résout le
  label, liste ses messages, extrait From/Subject/corps texte, base64url ; ACL au patron des
  autres adaptateurs Gmail) dès que `GOOGLE_CLIENT_ID` est présent, sinon `FakeAlertEmailFetcher`
  (démo sans réseau). Adaptateur Outlook réel de lecture du label = **suivi** (compte de test Gmail).
- Scheduler `FetchAllAlertEmailsTick` : fan-out **asynchrone par boîte connectée** (isolation de panne).

## Infrastructure (`Infrastructure/`)
- OAuth : `GmailConnector`/`OutlookConnector` + `FakeMailboxConnector` (défaut sans identifiants),
  `OAuthStateCodec` (state anti-CSRF signé, lié tenant + provider).
- Envoi/relève : `GmailMailSender`/`OutlookMailSender`, `GmailReplyFetcher`/`OutlookReplyFetcher`,
  variantes `Fake*` ; `OAuthAccessTokenMinter` (frappe d'access token, une instance par fournisseur).
- Crypto : `SodiumTokenCipher` (secretbox, clé `MAILBOX_ENCRYPTION_KEY`).
- Les réponses captées deviennent `ReplyCaptured` → la politique Prospection `RecordReplyOnReplyCaptured`
  applique `Lead::recordReply()` (idempotent) ; l'envoi abouti (`EmailSent`) fait avancer la piste (D3).
