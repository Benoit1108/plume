# ADR-0019 — Adaptateurs email par fournisseur (registres) plutôt qu'un port unique

- **Statut : Accepté** (2026-07-14 — M2 ; précise la note de cadrage M2 §3)
- **Contexte** : la note M2 esquissait un port unique `MailProvider` couvrant connexion +
  envoi + relève. À l'implémentation (Gmail puis Outlook), ces trois capacités ont des cycles
  de vie et des points d'injection distincts (la connexion est HTTP/OAuth au moment du
  consentement ; l'envoi et la relève tournent dans le worker, par boîte).

## Décision

1. **Trois ports séparés** (ISP) : `MailboxConnector` (OAuth), `MailSender` (envoi),
   `ReplyFetcher` (relève) — un consommateur qui envoie n'a pas à connaître l'OAuth.
2. **Un registre par port** (`MailboxConnectorRegistry`/`MailSenderRegistry`/
   `ReplyFetcherRegistry`) qui route vers l'adaptateur **du fournisseur de la boîte connectée**
   (`mailbox->provider()`), et retombe sur l'adaptateur **factice** sans identifiants d'env
   (dev/CI/E2E sans compte réel — pattern `MessageGeneratorSelector` de M1.4).
3. **Le fournisseur voyage signé dans le `state` OAuth** (`OAuthStateCodec` porte
   tenant + provider) : le callback le relit sans entrée libre.
4. **Frappe d'access token factorisée** : `OAuthAccessTokenMinter` (une instance par
   fournisseur) partagée entre sender et relève — pas de `mintAccessToken` dupliqué.

## Conséquences

- ✅ Ajouter un 3ᵉ fournisseur = 3 adaptateurs + 2 lignes d'env, sans toucher aux consommateurs.
- ✅ Coût zéro en test (adaptateurs factices par fournisseur).
- ⚠️ Trois registres au routage quasi identique (léger `match`) — duplication acceptée
  (un helper commun serait sur-abstrait pour trois cas).
