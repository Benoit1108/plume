# Mailbox — Passerelle email (Generic technique)

Envoi + lecture via OAuth Gmail / Microsoft Graph (cf. ADR-0007).

À peupler (M2) :
- `Domain/` : agrégat `ConnectedMailbox` (provider, tokens chiffrés, statut).
- `Application/` : ports `OutboundMailer` (envoi) et `MailboxReader` (lecture).
- `Infrastructure/` : adapters Gmail/Outlook, matching des réponses (`Message-ID`/`References`) → `Lead::recordReply()`.
