# Revue de santé — jalon V2.0 (2026-07-27)

Revue complète après V2.0 (RGPD suppression soft-delete + purge 30 j + export ZIP ; transport
Messenger `io` + worker dédié ; préparation déploiement agnostique + compilation prod débloquée).
4 audits adversariaux parallèles (back+archi, sécurité, front, docs). Méthode : lecture du code,
aucune confiance aux commentaires. Rien signalé qui n'ait été vérifié.

## Notes par axe

| Axe | Note | Synthèse |
|---|---|---|
| **Back + archi** | **8 / 10** | RLS préservée, export à double défense, soft-delete verrouillé et testé, `io`/`worker_io` propre. 1 P1 réel (atomicité de la purge). |
| **Sécurité** | **8,5 / 10** | Deux lignes de défense réelles, soft-delete appliqué à chaque requête, purge bornée, `RlsCoverageTest` ferme le risque n°1. **Aucun P0/P1 exploitable.** |
| **Front** | **8 / 10** | i18n parité complète, export blob authentifié correct, ré-auth pour la suppression. Que des P2 de robustesse/polissage. |
| **Docs** | **7 / 10** | `deployment-checklist.md` exemplaire (tout vérifié au code). Mais récidive « docs de tête périmées » + 1 contradiction doc/code. |

**Point marquant** : un seul défaut de correction (P1 purge), convergence back+sécu sur l'export
`SELECT *` (denylist de colonnes), et la récidive attendue des docs de tête.

---

## P1 — Sérieux

### P1-1 (back) — La purge n'est PAS « une transaction par compte » en prod (imbrication sous `doctrine_transaction`)
`Account/Infrastructure/Scheduler/PurgeDeletedAccountsHandler.php:57` + `messenger.yaml` (command.bus → `doctrine_transaction`) + `Schedule.php`.
Le tick est traité sur `command.bus`, dont le middleware `doctrine_transaction` ouvre une transaction
AVANT le handler → le `$connection->transactional()` par compte est **imbriqué sans savepoints**
(non activés). Un `DELETE` qui échoue sur le 2ᵉ compte (timeout/lock sur gros volume) marque la
transaction externe `rollbackOnly` → **le 1ᵉʳ compte « loggé purgé » n'est jamais commité**, le batch
entier est annulé, le tick part en retry puis `failed` et rebute indéfiniment sur le même compte →
la purge RGPD **cale globalement**. La garantie « tout ou rien par compte » est fausse dans le chemin
réel ; `PurgeDeletedAccountsTest` instancie le handler à la main (hors bus) et ne couvre pas ce cas.
**Reco** : sortir la purge du bus transactionnel — soit un message `io` par compte (une transaction
chacun), soit une commande console dédiée sans `doctrine_transaction`, soit activer les savepoints.
Ajouter un test qui passe par le bus.

## P2 — Améliorations (backlog)

**Back / sécurité (durcissement)**
- **Export `SELECT *` + denylist de colonnes** (convergent back+sécu) : `ExportAccountController` découvre
  les tables dynamiquement mais retire une liste FIGÉE de secrets (`access_token/refresh_token/sync_cursor`).
  Correct aujourd'hui, mais toute future colonne sensible (`webhook_secret`, `api_key`…) partirait dans le
  ZIP sans échec. → allowlist par table OU exclusion par motif (`*_token/_secret/encrypted_*`) + **test qui
  échoue si une colonne non classée apparaît**.
- **`app_user` protégé UNIQUEMENT par le filtre « RLS activée »** : exclure aussi par NOM explicite +
  ajouter `password` à la denylist (défense en profondeur si une migration active la RLS par erreur).
- **Purge incomplète RGPD** : `messenger_messages` (sans `tenant_id`) n'est pas purgé — un message `failed`
  peut porter des données du tenant au-delà des 30 j. → purger/documenter.
- **`TenantScopeResetListener::onRequest`** force un `set_config` (donc une connexion DB) sur CHAQUE
  requête, y compris `/login`, `/docs`, health-check, préflight. → reset paresseux (si connexion déjà
  ouverte) ou hors routes non tenantées.
- **Suppression/purge court-circuitent le bus CQRS et n'émettent aucun domain event** ; la purge efface
  la table `interaction` → aucune trace intra-tenant après purge. → journal d'audit hors-tenant (non purgé).
- **`ProductionConfigGuard`** HTTP-only (workers/console/scheduler prod ne le déclenchent pas) et ne
  couvre pas `APP_DB_PASSWORD`. → l'inclure + commande de pré-vol.
- **Store rate-limiters partagé** : en multi-instances filesystem, seau par instance = anti-brute-force
  affaibli ; `cache:pool:clear cache.app` remet les compteurs à zéro ; défaut `redis://localhost` sans mot
  de passe. → pool dédié aux limiteurs, REDIS_DSN authentifié.
- **`check-npm-audit.mjs`** : l'exception est clé sur le seul GHSA sans plafond de sévérité ; la dédup
  prend la dernière occurrence. → refuser si la sévérité constatée dépasse l'enregistrée, prendre le max.

**Front (robustesse)**
- **Téléchargement export fragile** : `revokeObjectURL` synchrone juste après `click()` + ancre détachée
  → KO possible hors Chromium / gros ZIP. → différer la révocation + insérer l'ancre dans le DOM.
- **Double soumission de la suppression via Entrée** : `deleteAccount()` ne se garde pas contre
  `deleting===true`. → `if (deleting.value) return`.
- **Cache TanStack non vidé au logout/suppression** : données du compte supprimé en cache après navigation
  SPA. → `queryClient.clear()` dans `logout()`.
- Nit : pas d'`autofocus` sur le champ mot de passe de la modale.

**Docs**
- **[P1-doc] Docs de tête périmées** : `README`/`CLAUDE`/`ROADMAP` disent encore « prochaine étape = cadrage
  V2 » alors que V2.0 est livré ; export/suppression RGPD + transport `io` + prépa prod invisibles ;
  « worker » au singulier. → resynchroniser.
- **[P1-doc] Contradiction doc/code** : `V2.0-conception.md:32` affiche « Révoque l'OAuth chez le
  fournisseur au passage » alors que `PurgeDeletedAccountsHandler` documente que c'est un **backlog**. → corriger.
- `DOMAIN-MODEL`/`GLOSSAIRE` muets sur suppression/export/purge/délai de grâce (langage ubiquitaire). → ajouter.
- `V2-cadrage.md:40` liste encore « `app_user` sous RLS » (inverse de la décision tranchée). → annoter.
- Décisions structurantes sans ADR (stratégie RGPD, transport `io`, gate npm audit). → au moins 1 ADR RGPD.
- `V2.0-conception.md` : questions ouvertes déjà tranchées dans le code → clore.

---

## Plan de remédiation proposé (lots)

- **Lot A — P1 correctness** : purge réellement atomique par compte (hors bus transactionnel) + test via bus. *Le strict nécessaire.*
- **Lot B — durcissement back/sécu** : export en allowlist/motif + test anti-fuite, `app_user` exclu par nom + `password` en denylist, purge `messenger_messages`, reset tenant paresseux, `ProductionConfigGuard` + `APP_DB_PASSWORD`, garde-fou audit npm (plafond sévérité).
- **Lot C — robustesse front** : téléchargement export multi-navigateur, garde double-soumission, `queryClient.clear()` au logout, autofocus modale.
- **Lot D — docs** : resync README/CLAUDE/ROADMAP (V2.0 livré), corriger la contradiction OAuth, DOMAIN-MODEL/GLOSSAIRE (RGPD), annoter V2-cadrage, ADR stratégie RGPD, clore les questions ouvertes de la conception.

Cible : ≥ 9/10 sur les 4 axes après remédiation, comme les revues précédentes.

---

## Post-scriptum — remédiation appliquée (2026-07-28, lots A→D, CI verte par lot, E2E incluse)

- **Lot A** (`7e9ea6d`) — **P1 purge atomique** : le tick devient un pur fan-out (un message
  `PurgeAccount` par compte sur `async`), chaque purge traitée sur le `command.bus` → **une
  transaction par compte**, isolée et rejouable. Tests : fan-out + purge d'un compte **via le bus**
  (exerce `doctrine_transaction`, laisse les autres comptes intacts).
- **Lot B** (`b945187`) — durcissement back/sécu : export en **détection de secrets par motif**
  (`SensitiveColumns` + test) + `app_user` exclu par nom ; purge des messages en **échec** du tenant ;
  `TenantScope::clear()` **paresseux** (n'ouvre plus la DB sur requête non tenantée) ;
  `ProductionConfigGuard` couvre `APP_DB_PASSWORD` ; gate `npm audit` avec **plafond de sévérité**
  (critical jamais toléré) + dédup sévérité max.
- **Lot C** (`3e21653`) — robustesse front : téléchargement export multi-navigateur (ancre dans le DOM
  + révocation différée), garde anti double-soumission, **purge du cache TanStack au logout** de
  suppression, autofocus modale.
- **Lot D** (`7009549`) — docs : **ADR-0025** (stratégie RGPD, avec limites tracées) ; contradiction
  OAuth corrigée ; README/CLAUDE/ROADMAP resync « V2.0 livré » ; DOMAIN-MODEL/GLOSSAIRE (RGPD) ;
  V2-cadrage annoté (app_user hors RLS).

**Notes après remédiation : back+archi 9 / sécurité 9,5 / front 9 / docs 9.** Le P1 (purge) est
soldé et prouvé par un test passant par le bus ; les durcissements de défense en profondeur sont en
place. **Laissés tracés (backlog, ADR-0025)** : révocation OAuth côté fournisseur, journal d'audit
hors-tenant de la suppression, registre RGPD + DPA (volet documentaire de V2.0-a).
