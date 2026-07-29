# ADR-0030 — Observabilité : logs JSON corrélés (tenant + requête) et error-tracking

- **Statut** : Accepté (2026-07-29, proposition V2 « observabilité »).
- **Contexte** : dès qu'il y a de vrais utilisateurs, il faut pouvoir **répondre à « que s'est-il passé
  pour CE compte / CETTE requête ? »** et être **alerté des erreurs**. Le socle existait en partie (logs
  **JSON** sur stderr en prod + `TenantLogProcessor` ajoutant `tenant_id`). Manquaient : un **identifiant
  de corrélation de requête** (relier toutes les lignes d'une requête, y compris l'async qu'elle déclenche)
  et un **error-tracking**.

## Décision

### Corrélation
- **`tenant_id`** sur chaque log : `TenantLogProcessor` (déjà en place), alimenté par `TenantContext`
  (HTTP) et le `TenantIsolationMiddleware` (worker).
- **`request_id`** sur chaque log : nouveau `CorrelationContext` + `CorrelationIdProcessor`. Posé très tôt
  par `CorrelationIdListener` (kernel.request, priorité 8000) : réutilise un en-tête `X-Request-Id`
  entrant **s'il est de forme sûre** (bornage + liste blanche de caractères → pas d'injection de log),
  sinon génère un UUID v7 ; renvoyé dans la réponse (`X-Request-Id`), remis à zéro en fin de requête.
- **Propagation à l'async** : `CorrelationStamp` + `CorrelationMiddleware` (sur `command.bus` et
  `event.bus`), **symétriques du `TenantIsolationMiddleware`** — le `request_id` est estampillé au
  dispatch, sérialisé vers le transport, ré-activé le temps du handler worker puis nettoyé. Une action
  utilisateur est ainsi traçable de bout en bout (HTTP → events/commands async).

### Error-tracking
- **Sentry** (`sentry/sentry-symfony`), chargé **prod-only** (`bundles.php`) et **inerte tant que
  `SENTRY_DSN` est vide** → le code est prêt, l'activation ne tient qu'à un DSN (compte SaaS **ou**
  instance self-hosted — le code est identique, cela n'engage pas la décision d'hébergement).
- **RGPD** : `send_default_pii: false` (jamais d'email/IP/contenu). La corrélation utile est ajoutée
  **sans PII** par `SentryScopeListener` (tags `tenant_id` + `request_id`), lui aussi **inerte** s'il n'y a
  pas de client Sentry (donc sans effet en dev/test).

## Conséquences
- ✅ Filtrage/agrégation des logs par **tenant** et par **requête** (une action = un `request_id`, même à
  travers les workers) ; l'`X-Request-Id` renvoyé permet au support de citer une requête précise.
- ✅ Error-tracking **prêt à activer** sans nouvelle écriture de code (juste le DSN), sûr par défaut
  (inerte + sans PII), agnostique SaaS/self-hosted.
- ✅ Tout est **testé hors prod** (processor, listener, middleware) et la **compilation prod** (bundle +
  config Sentry, DSN vide) est vérifiée.
- ⚠️ **À la charge de Benoit** : choisir/fournir un projet **Sentry** (SaaS ou self-hosté) et renseigner
  `SENTRY_DSN` en prod (cf. TODO-benoit + deployment-checklist).
- 🔭 Corrélation `request_id` dans Sentry côté **worker** (async) non branchée (seul le scope HTTP est
  tagué) — amélioration future ; les **logs** worker, eux, portent déjà `request_id` + `tenant_id`.
