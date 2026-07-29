# ADR-0025 — RGPD : suppression de compte (soft-delete + purge) et export des données

- **Statut** : Accepté (2026-07-28, jalon V2.0-a)
- **Contexte** : l'ouverture publique multi-comptes (V2) impose le **droit à l'effacement** et le
  **droit à la portabilité**. Modèle acté : **1 compte = 1 traductrice = 1 tenant** (cf.
  [cadrage V2](../../design/V2-cadrage.md)). Décisions produit prises avec Benoit : suppression en
  **soft-delete + purge différée 30 j** ; export **JSON + CSV**.

## Décision

### Suppression de compte — soft-delete puis purge après délai de grâce
- `DELETE /api/v1/account` (ré-authentification par mot de passe, débit limité) pose
  `app_user.deletion_requested_at`. Le compte est **désactivé immédiatement** : `AccountStatusChecker`
  (UserChecker sur les firewalls login/refresh/api) refuse toute authentification, les refresh tokens
  sont révoqués, les cookies effacés. Les trois ticks de relève (réponses, alertes, sources)
  **cessent d'énumérer** un tenant en cours de suppression (garantie « on ne relève plus rien »).
- **Délai de grâce de 30 j** puis purge PHYSIQUE : filet contre l'erreur/le regret, cohérent avec des
  sauvegardes. Il n'existe **aucune UI de restauration self-service** → du point de vue de
  l'utilisatrice, l'action est irréversible (une restauration ne peut être qu'exceptionnelle, support).
- **Purge = fan-out, une transaction par compte** (revue V2.0, P1) : le tick énumère les comptes
  expirés (scheduler propriétaire, `app_user` hors RLS) et émet un message `PurgeAccount` par compte
  sur `async` ; `PurgeAccountHandler` efface UN compte sur le `command.bus` (donc `doctrine_transaction`
  → atomicité et isolation de panne par compte). Tables tenantées **découvertes dynamiquement**
  (`pg_class`, aucune oubliée), puis `refresh_tokens`, les messages en échec du tenant, et `app_user`.

### Export des données — archive ZIP (portabilité)
- `GET /api/v1/account/export` → ZIP : `export.json` (dump complet) + `organisations.csv` /
  `pistes.csv` (lisibles dans un tableur, BOM UTF-8). Synchrone (volume borné en 1=1).
- **Deux lignes de défense anti-fuite cross-tenant** : on ne dumpe que les tables à **RLS activée**
  (fail-closed : `app_user` en est exclu) **et** on filtre explicitement `WHERE tenant_id`.
- **Secrets jamais exportés** : détection **par motif** (`SensitiveColumns` : token/secret/password/
  credential/api_key/`encrypted_*`/`_cursor`) — robuste aux futures colonnes sensibles.

### Rétention & minimisation
- `raw_alert` (brut d'annonces, contenu de tiers) **exclu de l'export** et purgé à 30 j de son côté (D6).
- `app_user` reste **hors RLS** (lu par email avant le tenant, cf. [ADR-0023 §4](0023-rls-multi-tenant.md)) —
  confirmé en V2.0, ce n'est pas une dette.

## Conséquences
- ✅ Droits d'effacement et de portabilité couverts, testés (endpoint + purge via bus + anti-fuite export).
- ✅ Isolation renforcée : la purge par compte s'exécute sous le rôle runtime, tenant activé → RLS appliquée.
- ✅ **Révocation OAuth CÔTÉ FOURNISSEUR (faite — clôture V2.0)** : à la purge, avant d'effacer les
  tables, on révoque le consentement chez Google/Microsoft via le port `MailboxRevoker`
  (Account/Application) implémenté par `MailboxTokenRevoker` (Mailbox/Infrastructure) — best-effort
  total (une boîte absente, un token indéchiffrable ou une panne réseau ne bloque jamais l'effacement).
  Choix de timing : à la **purge** (pas au soft-delete) car c'est le seul point qui couvre uniformément
  les deux voies (self-service ET support) avec le bon tenant activé, et c'est cohérent avec le modèle
  de délai de grâce (données ET consentement effacés à l'expiration). Microsoft n'expose pas de
  révocation par refresh token côté app (documenté dans `OutlookConnector`).
- ⚠️ **Limites connues (backlog, à tracer au registre RGPD)** :
  - **Piste d'audit** : la purge efface le journal `interaction` (intra-tenant) ; seule une ligne de log
    applicative (identifiant technique du tenant) subsiste. Un journal d'audit hors-tenant reste à concevoir.
  - **`messenger_messages`** hors file `failed` (messages en régime nominal) n'est pas balayé (consommé).
- Le **registre de traitement + le DPA** (sous-traitants Anthropic, Google/Microsoft, hébergeur) restent
  à produire (volet documentaire de V2.0-a).
