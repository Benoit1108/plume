# CLAUDE.md — conventions de travail

Instructions pour Claude Code (et tout dev) dans ce dépôt. À lire avant toute contribution.

## Ce qu'est le projet

**Plume** : mini-CRM SaaS de démarchage pour traductrice indépendante (édition, audiovisuel, technique).
Cœur métier = **pipeline de prospection + relances**. Voir `README.md` et `docs/`.

## Avant de coder — lecture obligatoire

1. `docs/GLOSSAIRE.md` — le **langage ubiquitaire est contractuel** : métier en **français** (UI/docs), code en **anglais** via la table de correspondance FR↔EN.
2. `docs/architecture/OVERVIEW.md` — couches, CQRS, tenancy, exposition API.
3. `docs/architecture/DOMAIN-MODEL.md` — agrégats, VOs, events, machine à états.
4. `docs/architecture/decisions/` — ADRs : ne pas contredire une décision sans nouvel ADR.

## Règles d'architecture (non négociables)

- **Sens des dépendances** : `Infrastructure → Application → Domain`. Jamais l'inverse (deptrac le vérifie en CI).
- **`Domain/` est du PHP pur** : aucune dépendance à Symfony, Doctrine, API Platform. Pas d'annotation ORM sur les agrégats (mapping XML dans `api/config/doctrine/`).
- **Un contexte ne dépend d'un autre que par ID** (références cross-agrégat) ou **par port** (interface). Jamais d'accès direct à un agrégat d'un autre contexte.
- **Une commande = une transaction.** Les domain events sont dispatchés via l'outbox transactionnel (transport doctrine, même transaction que la commande).
- **Toute mutation d'agrégat émet un domain event** (projections, journal, traçabilité RGPD).
- **Les queries lisent des read models** (vues immuables via un port `…Search`, SQL direct fail-closed sur le tenant — cf. ADR-0013), jamais les agrégats.
- **API Platform expose des DTO**, jamais les entités Doctrine ni les agrégats. State Providers/Processors délèguent au bus CQRS.
- **Les erreurs métier héritent de `Shared\Domain\Exception\DomainError`** (`InvalidValue` → 422, `NotFound` → 404, `Conflict` → 409 — mapping `exception_to_status`). Jamais d'exception SPL nue dans le domaine.
- **Multi-tenancy = préoccupation d'infra**, **deux lignes de défense fail-closed** (ADR-0023) : (1) applicative — `SQLFilter` sur `tenant_id` (ORM) + prédicat explicite (read models DBAL), pilotés par `TenantScope` ; (2) base — **RLS** sous le rôle runtime non-propriétaire `plume_app`. Le domaine n'en sait rien. ⚠️ **Toute nouvelle table métier tenantée** doit porter `tenant_id` **et** recevoir sa policy RLS dans la migration (sinon le filet base saute). Créer le rôle en dev : `make provision-app-role`.
- **Pas d'horloge ni d'UUID en dur** dans Application : ports `Clock` et `IdGenerator`.
- **Secrets/tokens chiffrés au repos** ; jamais de credential en clair en base ou en log.

## Ajouter une fonctionnalité (flux type)

1. Modéliser dans `Domain/` (méthode d'agrégat + invariants + domain event).
2. Écrire un **Command** + son **Handler** dans `Application/` (le handler publie les events).
3. Implémenter le repository/adapter dans `Infrastructure/`.
4. Lecture : vue + port dans `Application/ReadModel/`, implémentation SQL en Infrastructure.
5. Exposer via **DTO + State Processor/Provider** (API Platform) avec contraintes Assert complètes **et bornées** (longueurs max — leçon M1.1/M1.4) ; régénérer `openapi.json` (`make openapi` — diff bloquant en CI). ⚠️ Après tout changement de propriétés d'une resource : `cache:clear` **avant** `make openapi` (métadonnées API Platform en cache = contrat obsolète).
6. Tests : domaine (pur) → application (repo in-memory, `tests/Support/`) → fonctionnel (`ApiTestCase` + Postgres).
7. Front Nuxt : composable/store + vue — **tout texte passe par i18n** (fr + en), toasts sur les mutations, confirmation avant action destructive.

## Conventions de code

- **PHP 8.5**, typage strict (`declare(strict_types=1)`).
- **PHPStan niveau max** et **PHP-CS-Fixer** doivent passer avant commit (`make hooks` installe le hook pre-commit).
- VOs immuables, validation dans le constructeur (échec = `InvalidValue`).
- Nommage du **code en anglais** (classes, méthodes, events, propriétés) via la table de correspondance du glossaire ; **pas d'identifiants accentués**. Le vocabulaire **métier reste français** dans l'UI et la doc.
- Front : TypeScript strict, ESLint, composants Vue en `<script setup>`, libellés métier centralisés (`useDirectoryLabels` + locales i18n).

## Tests

- Le domaine se teste **sans base de données** — c'est un objectif de conception, pas un accident.
- Pas de fonctionnalité métier sans test de domaine correspondant ; pas de handler sans test d'application ; pas d'endpoint sans test fonctionnel (l'**isolation tenant** est couverte par `tests/Functional/`).
- Front : seuils de coverage **bloquants** dans `vitest.config.ts` — ne pas les baisser pour faire passer un build.
- **E2E Playwright** (`app/e2e/`, helpers partagés dans `e2e/helpers.ts`) : build de prod contre l'API réelle, user dédié `e2e@plume.test` (tenant isolé), **workers sérialisés** (tenant partagé entre fichiers), garde console/hydratation systématique. Lancement local : voir README § Tests E2E (stopper le conteneur `app` d'abord).

## Commandes

```bash
make up             # stack dev (Postgres + API https://localhost:8443 + workers async & io — le journal en dépend)
make provision-app-role # rôle runtime plume_app (RLS) — 1re install / nouveau cluster
make migrate        # migrations Doctrine (+ messenger:setup-transports, en propriétaire)
make jwt-keys       # génère les clés JWT locales (une fois)
make test           # PHPUnit complet (crée/migre la base _test)
make phpstan        # analyse statique niveau max
make deptrac        # frontières DDD (couches + contextes, 2 configs)
make cs-fix         # PHP-CS-Fixer
make openapi        # régénère api/openapi.json (obligatoire après tout changement d'API)
make hooks          # installe le hook git pre-commit
make seed           # jeu de données de RECETTE (dev only : recette@plume.fr / recette-2026)

cd app && npm run dev          # front (http://localhost:3000, proxy /api vers l'API)
cd app && npm run test:coverage / lint / type-check
```

Créer un utilisateur local : `docker compose exec php php bin/console app:user:create <email>` (mot de passe demandé).

## Git

- **Trunk-based assumé** : commits atomiques sur `main`, CI verte obligatoire, jamais de force-push (protection de branche active). Une branche courte reste bienvenue pour une exploration risquée.
- Messages conventionnels (`feat(scope): …`, `fix: …`, `docs: …`), descriptions en français.
- Ne jamais committer de secrets (`.env.local`, tokens OAuth, clés JWT — gitignorés).

## État actuel

**M1 complet (revue de santé fin M1 appliquée) et M2 — passerelle email — complet** :
contexte `Mailbox` (OAuth **Gmail + Outlook** derrière des ports routés par fournisseur,
tokens chiffrés ADR-0016, envoi asynchrone draft-first, captation des réponses par polling
ADR-0017, relances dans le fil). Auth en cookies httpOnly (M2.0).

**M3.0 — socle Sourcing + file de tri livré** : contexte `Sourcing` (agrégat `CandidateLead`,
ADR-0020/0021), écran « À trier » (accepter/fusionner/rejeter), promotion cross-contexte par
gateway, dédoublonnage à l'ingestion, `LeadSource` enrichi. **M3.1 — ingestion RSS complète livrée** :
port `AlertSource` + `RssAlertSource`/`FakeAlertSource`, brut conservé (`RawAlert` + `rawRef`),
flux configurables par tenant (`AlertFeed`, écran Réglages « Sources »), relève manuelle
(`POST /sources/poll` + bouton) et auto (Scheduler 30 min), purge du brut (D6). **M3.2 — alertes
email** (plomberie) : la Passerelle lit un label dédié (« Plume/Alertes », ADR-0017 amendé) et
publie `AlertEmailReceived` ; le Sourcing parse (par domaine expéditeur) et ingère. **Jalon M3
complet** (suivi : adaptateurs email réels + parsers fins par fournisseur, avec de vrais emails).
Une passe d'harmonisation visuelle + une page **Compte** (mot de passe, nom d'affichage) ont
aussi été livrées.

**Phase pré-V2 TERMINÉE** (cf. `docs/design/PRE-V2-cadrage.md` + revue `docs/reviews/2026-07-24-revue-sante-pre-v2.md`) :
- **Chantier 1 — durcissement back multi-tenant** : rôle runtime non-propriétaire `plume_app` +
  **Row-Level Security** fail-closed (ADR-0023), var de session `app.current_tenant` propagée
  HTTP + worker, scheduler propriétaire pour la maintenance cross-tenant.
- **Chantier 2 — mail réel** : `RssAlertSource` via laminas-feed (RSS 2.0 **+ Atom**),
  `GmailAlertEmailFetcher` réel (lecture du label), `LinkedInAlertEmailParser` fin (1 offre =
  1 candidat), relève manuelle des alertes (bouton). Gmail validé en réel (relève + envoi + réponse).
  **Outlook réel LIVRÉ** : les 3 adaptateurs Microsoft Graph (envoi `OutlookMailSender`, réponses
  `OutlookReplyFetcher`, alertes `OutlookAlertEmailFetcher` sur le dossier `Plume/Alertes`) sont réels,
  câblés et testés (MockHttpClient), à parité avec Gmail — routés vers le réel dès `MICROSOFT_CLIENT_ID`
  présent. *Reste : parsers fins ProZ/TranslatorsCafe (échantillons) ; 🟦 identifiants Azure + recette Outlook.*
- **Chantier 3 — front** : **SPA `ssr:false`** (ADR-0024), **TanStack Query** (cache + invalidation),
  **types générés depuis OpenAPI** (drift CI), parité i18n testée.
- **Chantier 4 + divers** : `AbstractStringIdType` (dédup id-VO), **poll manuel async**, durcissement
  OpenAPI (enums de statut). Revue de santé fin pré-V2 remédiée (lots A→D).

**V2.0 — prérequis & bascule multi-tenant : LIVRÉ** (cadrage `docs/design/V2-cadrage.md`, conception
`docs/design/V2.0-conception.md`, revue `docs/reviews/2026-07-27-revue-sante-v2.0.md`) :
- **V2.0-a RGPD** ([ADR-0025](docs/architecture/decisions/0025-rgpd-suppression-export.md)) : suppression
  de compte en **soft-delete + purge après 30 j** (fan-out, une transaction par compte) + **export** ZIP
  JSON/CSV (scopé tenant, secrets exclus par motif). `app_user` reste **hors RLS** (décidé, pas une dette).
- **V2.0-b isolation de charge** (ADR-0022 §5) : transport Messenger **`io`** dédié aux relèves
  (RSS/IMAP/Graph) + **worker `worker_io`** séparé du worker `async` (events/projections légers).
- **V2.0-c déploiement agnostique** : store rate-limiters **Redis-ready** (paramètre `app.cache_adapter`),
  reset tenant `kernel.request`, **fail-fast secrets prod** (`ProductionConfigGuard`), **le conteneur prod
  compile** ; checklist `docs/ops/deployment-checklist.md`.

**V2.1 — ouverture des comptes : LIVRÉ** (plan directeur `docs/design/V2-plan-directeur.md`) :
- **Socle** : mot de passe oublié (jeton **avec état**, hachage sha256), emails transactionnels, **vérif
  email sans état** (HMAC `kernel.secret`, [ADR-0029](docs/architecture/decisions/0029-verification-email-sans-etat.md)),
  sonde `/health`, pages légales.
- **Inscription publique** : compte **non vérifié** (login refusé avant confirmation), provider **insensible
  à la casse** (`AppUserProvider`), `email_verified` défaut `true` (zéro-ripple CLI/seed/tests) + **onboarding**.
- **2FA TOTP + sessions** ([ADR-0027](docs/architecture/decisions/0027-2fa-totp.md)) : lib `spomky-labs/otphp`,
  enrôlement 2 temps, anti-rejeu `totp_last_used_step`, codes de secours sha256, codes stables
  `2fa_required`/`2fa_invalid`. Sessions actives (liste/révocation) ; activation/désactivation révoque les sessions.

**Back-office / Admin : LIVRÉ** — contexte `Admin` **hors tenant** (`ROLE_ADMIN`, créé par CLI), **connexion
propriétaire cross-tenant** ([ADR-0026](docs/architecture/decisions/0026-connexion-admin-proprietaire.md), amende
0023), **2FA obligatoire** (`AdminTwoFactorRequiredListener`) ; vue d'ensemble (comptages), comptes (recherche),
suppression RGPD support + reset 2FA, **journal d'audit hors-tenant** (`AuditLogger`, survit à la purge).

**Centre de notifications : LIVRÉ** (in-app) — projection DBAL sur domain events
([ADR-0028](docs/architecture/decisions/0028-centre-notifications-projection.md)) : `reply_received`,
`email_send_failed`, relances dues (fenêtre de rattrapage 7 j) ; badge cloche, purge 90 j.

**Revue de santé GLOBALE (7 axes) + remédiation A→G** (`docs/reviews/2026-07-29-revue-sante-globale.md`) :
lots A→D (lifecycle compte, sécu, front) + E (tests : chaîne email, TOTP/signer/health, 3 plafonds
rate-limit, coverage `perFile`, E2E compte + garde admin) + F (resync docs) + G (polish UX/a11y).

**V2.0 CLÔTURÉE** : révocation OAuth côté fournisseur à la purge (port `MailboxRevoker`, ADR-0025 amendé) ;
**trames RGPD** fournies (`docs/legal/` registre Art.30 + DPA Art.28, reste validation Benoit).

**Observabilité : LIVRÉE** ([ADR-0030](docs/architecture/decisions/0030-observabilite.md)) : logs JSON
corrélés **tenant + request_id** (HTTP via `CorrelationIdListener` + async via `CorrelationMiddleware`/
`CorrelationStamp`) + **error-tracking Sentry** prod-only, env-gated (`SENTRY_DSN` vide = inerte), sans PII.

**Reprise de boîte OAuth LIVRÉE** : `invalid_grant` → `MailboxReauthRequired` → notif `mailbox_disconnected`
(cas actionnable uniquement) → reconnexion guidée (Réglages). **Digests email LIVRÉS** : préférence
`profile.digest_frequency` (NONE/DAILY/WEEKLY, défaut DAILY, réglable), tick quotidien, email bilingue sans PII.

**Back-office étendu : LIVRÉ** — **statut système** (`/admin/status` : files Messenger, âge du backlog,
`failed`, boîtes en erreur ; distinct de `/health`) + **métriques produit** (`/admin/metrics` : actifs 30 j,
inscriptions/semaine via `app_user.created_at`, pistes par statut ; sans PII).

**V2.3 en cours** — **Séquences de relance configurables : LIVRÉ** : cadence par tenant
(`profile.follow_up_cadence`, JSON, défaut J+7/21/45) lue par le domaine via port `FollowUpCadenceProvider`
(adaptateur SQL profil, comme le tick lit `timezone`) ; `Lead::contact/recordFollowUp` reçoivent une
`FollowUpCadence` (param optionnel → défaut) ; réglable dans Réglages. Vide = pas de relance auto.

**Annuaire pré-rempli : LIVRÉ** : catalogue de cibles de référence (`data/directory-catalog.json`,
échantillons fictifs — Benoit met les vraies données sans toucher au code) via port `DirectoryCatalog` ;
`GET /directory/catalog` + `POST /directory/catalog/import` (crée l'Organisation, dédup par nom).

**Pipeline personnalisable : LIVRÉ** ([ADR-0031](docs/architecture/decisions/0031-pipeline-labels-configurables.md),
amende 0008) : libellés d'étapes configurables par tenant (`profile.pipeline_labels` JSON) résolus par la
source unique `useLeadLabels.statusLabel` (custom → sinon i18n) ; réglables dans Réglages. **La machine à
états reste FIGÉE** (la logique métier — cadence, terminaux, métriques — en dépend) : états arbitraires reportés.

**Dashboard enrichi : LIVRÉ (partiel)** : **délai moyen de 1re réponse** (lu sur le journal : MIN contact
→ MIN reply, moyenné) exposé en KPI, + **export CSV** (`GET /dashboard/export`, contrôleur simple, BOM UTF-8).
Différés : **filtres de période** (change la sémantique des taux) et **valeur estimée** (champ `estimatedValue`
sur la piste = changement de domaine).

**Valeur estimée des pistes : LIVRÉE** : champ `Lead.estimatedValue` (euros, nullable) + event
`LeadEstimatedValueChanged` ; fixé par action dédiée `PATCH /leads/{id}/estimated-value` (contrôleur simple)
+ saisie sur la fiche ; sommes au dashboard (`pipelineValue` actif / `wonValue`) + KPI + export CSV.

**Outlook réel : LIVRÉ** (envoi/réponse/alertes Microsoft Graph à parité Gmail — cf. Chantier 2 mail réel).

**Filtres de période dashboard : LIVRÉS** : enum `DashboardPeriod` (all/30d/90d/12m, fenêtres glissantes) ;
ne fenêtrent QUE les métriques du JOURNAL (taux contacté/répondu/gagné/perdu, segments, délai 1re réponse —
`occurred_on >= since`), les instantanés (pistes actives, pipeline, valeur) restent l'état ACTUEL ; passé en
`?period=` (provider lit `$context['filters']`, export CSV idem). **→ dashboard enrichi COMPLET.**

**Durcissement 2FA : COMPLET** — (1) **QR code d'enrôlement** : rendu client (`useQrCode` → `qrcode` npm) du
`otpauthUri` déjà exposé par `setup()`, clé en repli. (2) **Secret TOTP chiffré au repos** (ADR-0027 amendé) :
port `App\Account\Application\Crypto\SecretCipher` + `SodiumSecretCipher` (secretbox, même primitive qu'ADR-0016),
clé DÉDIÉE `TOTP_ENCRYPTION_KEY` fail-fast prod / dérivée APP_SECRET (préfixe `totp:`) hors prod ; déchiffré en
mémoire au setup/confirm/login (échec de déchiffrement = sûr → 2fa_invalid) ; colonnes `totp_*` élargies 128→255.
**Sources d'alertes (décision Benoit 2026-07-30)** : LinkedIn primaire (parser fin livré), ProZ secondaire
(parser à faire sur vraie annonce), **TranslatorsCafe abandonné** (site inutilisable) ; sources robustes
candidates : Indeed, Welcome to the Jungle, APEC, France Travail, Malt (relève email générique déjà OK).

**Notif « nouveau candidat à trier » : LIVRÉE** : `NotificationProjector::onCandidateLeadIngested`
(type `candidate_to_triage`, payload candidateLeadId+source ; enum resource étendu) → cloche + cible `/candidates`.

**Préférences de notif fines : LIVRÉES** : `profile.notification_preferences` (map JSON `type → {inApp, email}`,
ne stocke que les COUPURES, défaut = tout activé). Enforcement par **prédicat JSONB** à la lecture : la cloche
(`DoctrineNotificationFeed`, canal `inApp`) et le digest (`SendNotificationDigestsHandler`, canal `email`)
excluent les types coupés (`COALESCE(... ->> 'inApp'/'email', true)`). Matrice type×canal dans Réglages.
Pattern de préférence Profile mirroré (event `NotificationPreferencesChanged`, migration, resource+processor).

**Dédoublonnage suggéré au tri : LIVRÉ** : au tri en mode « accepter », si le nom saisi ressemble à une
organisation existante (util pure `suggestDuplicateOrganizations`, égalité/inclusion normalisée), une bannière
propose de basculer en « fusionner » sur cette organisation (réutilise recherche + merge déjà en place ; 100 %
front, aucun backend). **→ toutes les dettes techniques planifiées sont soldées.**

**Plafond de budget IA global : LIVRÉ** (ADR-0032) : 3 lignes de défense — défaut gratuit (canned) + plafond
par tenant/h (rate-limiter) + **garde-fou GLOBAL** : coupe-circuit `AI_GENERATION_ENABLED` + plafond mensuel
`AI_MONTHLY_TOKEN_BUDGET` (jetons, 0 = illimité). Port `AiBudget`/`DoctrineAiBudget`, compteur `ai_usage`
(mensuel, hors tenant/hors RLS, upsert atomique). Le sélecteur consulte `allowsGeneration()` avant Claude
(sinon repli canned gratuit, jamais d'échec) ; le générateur `record()` les jetons `usage`. Exposé au
back-office via `GET /admin/status` → `aiUsage` (widget). Lecture fail-open (coupe-circuit = garantie dure).

**Back-office v2 EN COURS** (4 blocs cadrés avec Benoit ; impersonation reportée). **Slice 1/4 LIVRÉE —
audit consultable + gestion comptes** : `GET /admin/audit` (journal hors-tenant filtrable), lecteur partagé
`AccountDirectory` (recherche + filtre statut all/verified/unverified/deleting + tri email/leads/created) →
liste `GET /admin/accounts` + export `GET /admin/accounts/export` (CSV) ; UI admin (filtres/tri/export +
section journal d'audit). Reste : 2/ santé & alertes · 3/ courbes & entonnoir · 4/ fiche compte détaillée.

**Prochaine grande étape** : finir back-office v2 (slices 2-4) ; puis V2.2 abonnement Stripe (attend décisions
paiement + compte Stripe de Benoit) ; site vitrine/démo.
enfin « à concevoir » (back-office v2 widgets, plafond budget IA global, site vitrine/démo).
