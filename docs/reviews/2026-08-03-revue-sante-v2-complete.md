# Revue de santé « V2 complète » — 2026-08-03

> **Statut : REMÉDIATION COMPLÈTE (A→G), CI verte par lot.** A sécu compte démo (ADR-0034) ·
> B durcissement webhook Stripe · C domaine pur (eventId outbox v7 + `FollowUpIds`) · D nettoyage
> (18 `getName()` DBAL 4 + `Assert\All`) · E factorisations front · F découpage des 8 grosses pages ·
> G resync docs. (+ correctif advisory brace-expansion.)


Point de contrôle : la V2 est complète côté fonctionnalités (jusqu'à la vitrine + compte démo).
Cette revue couvre le **delta non revu depuis la revue globale du 2026-07-29** (V2.2 Billing/Stripe,
garde lecture seule, compte démo, plafond IA ADR-0032, back-office v2, complétions dashboard) et
deux axes structurels demandés : **bonnes pratiques back (UUID/IDs)** et **découpage du front**.

## Méthode

Quatre audits adversariaux parallèles (sous-agents indépendants, lecture seule), un par axe :
sécurité & multi-tenant · hygiène back · architecture front · tests & docs. Synthèse ci-dessous.

## Notes par axe

| Axe | Note | Verdict |
|---|---|---|
| Sécurité & multi-tenant (delta) | **7,5 / 10** | Aucun P0. Socle d'isolation solide. 1 P1 = capacités du compte démo. |
| Hygiène back | **8,5 / 10** | Aucun P0/bug. Dérives d'outillage/cohérence autour des IDs. |
| Architecture front | **7,5 / 10** | Fondations transverses excellentes. Coût = volume des pages. |
| Tests (couverture du delta) | **8,5 / 10** | Pyramide respectée. Manque : anti-rejeu webhook + branches billing. |
| Fidélité docs | **7 / 10** | CLAUDE.md fidèle ; ROADMAP obsolète, index ADR figé, 1 décision sans ADR. |

**Aucun P0. Aucun bug de correction. Aucune faille exploitable.** Le produit est sain ; ce sont des
durcissements et de la dette d'hygiène, plus un point sécurité à traiter **avant l'ouverture publique**.

---

## Findings

### Sécurité & multi-tenant

- **S-P1 — Le compte démo public délivre une session `ROLE_USER` pleine, sans bridage de capacités.**
  `Account/Infrastructure/Http/DemoController.php` + `User::getRoles()` (ajoute toujours `ROLE_USER`) +
  `security.yaml` (pas de restriction `ROLE_DEMO`). Un visiteur anonyme obtient une session écriture
  complète : il peut déclencher la **génération IA** (consomme le budget Anthropic mensuel **partagé**,
  ADR-0032 → DoS de fonctionnalité dans la limite du plafond), **connecter une boîte OAuth et envoyer de
  vrais emails**, créer des données. Le tenant démo n'a pas de `subscription` → « grandfathered » → la
  garde lecture seule ne le bride pas. Le TTL 2 h ne suffit pas. **À corriger avant mise en prod ouverte.**
  → *Bloquer, pour `ROLE_DEMO` : génération IA forcée `canned`, connexion boîte + envoi réel refusés (voter/listener).*

- **S-P2a — Création de tenants démo bornée seulement par IP (5/h), aucun plafond global.**
  `DemoController.php` + `rate_limiter.yaml`. Store par défaut `filesystem` → plafond par-hôte en multi-instances.
  → *Ajouter un plafond global de comptes démo actifs (refus 503 au-delà).*

- **S-P2b — `applyStatusByCustomer` filtre sur `stripe_customer_id` sans contrainte d'unicité.**
  `Billing/Infrastructure/Persistence/DoctrineSubscriptions.php` + migration `Version20260803140000.php`
  (INDEX, pas UNIQUE). Invariant « 1 customer = 1 tenant » non garanti en base : un doublon ferait
  qu'un `subscription.deleted` bascule plusieurs tenants. → *`UNIQUE` partiel sur `stripe_customer_id`.*

- **S-P2c — Webhook Stripe : anti-rejeu par tolérance temporelle (300 s) seule, sans dédup d'`event.id`.**
  `StripeWebhookController.php`. Payload signé rejouable 300 s ; upserts idempotents (impact faible) mais un
  replay de `checkout.session.completed` juste après résiliation re-crédite l'accès dans la fenêtre.
  → *Mémoriser les `event.id` traités (table courte / cache TTL) et ignorer un ID déjà vu.*

**Sûrs (vérifiés)** : tables hors-RLS (`subscription`/`ai_usage`) filtrées + justifiées dans `RlsCoverageTest`,
connexion propriétaire confinée à `Admin/`, signature webhook avant traitement, minimisation PII, coupe-circuit IA
dur (config, pas base), garde lecture seule sans contournement produit, `DemoSeeder`/purge sans fuite cross-tenant.

### Hygiène back

- **H-P1 — Le Domaine génère lui-même de l'aléa (impureté / non-déterminisme).**
  `Prospecting/Domain/Lead/FollowUpId.php` (`UuidV4::generate()`, appelé dans `Lead.php`) et
  `Shared/Domain/AbstractDomainEvent.php` (`eventId = UuidV4::generate()`). Casse l'objectif « domaine testable
  sans I/O » ; visible dans `LeadTest.php` qui ne peut faire qu'un regex sur l'eventId, jamais une égalité.
  → *Soit injecter `IdGenerator`/`EventIdGenerator` (ou assigner l'eventId dans l'outbox applicatif) ; soit
  assumer le choix par ADR. **Décision requise — cf. lot C.***

- **H-P2a — Des eventId v4 alimentent des index UNIQUE B-tree (là où v7 aiderait), incohérents avec les PK v7.**
  `uniq_interaction_event` / `uniq_notification_event` indexent `event_id` (v4 aléatoire) alors que les PK sont en
  `Uuid::v7()`. (NB : n'affecte PAS `FollowUpId`, stocké en blob JSONB non indexé.) Impact perf **négligeable au
  volume Plume** → surtout de la cohérence. → *`UuidV7` pur-PHP dans `Shared/Domain/Uid/` pour les eventId.*

- **H-P2b — Le port `IdGenerator` est quasi mort** (injecté seulement dans `SeedRecetteCommand`). ~10 sites Infra
  appellent `Uuid::v7()` en direct. → *Soit l'utiliser partout, soit l'assumer inutile et le retirer.*

- **H-P2c — 18 surcharges `Type::getName()` vestigiales** (DBAL 4.4 a **retiré** la méthode ; l'enregistrement se
  fait par `const NAME`). Code mort hérité de DBAL 3, sous le radar de PHPStan/CS-Fixer.
  → *Les supprimer (via `AbstractStringIdType` qui les mutualise).*

- **H-P2d — Validation API de `notificationPreferences` plus faible que sa voisine `pipelineLabels`** (pas de
  `Assert\All` sur `{inApp, email}`). Risque atténué (le domaine normalise robustement) → défense-en-profondeur.
  → *Ajouter `Assert\All` pour homogénéiser.*

- **H-P2e (mineur) — `DemoController` contourne le port `Clock`** (`new \DateTimeImmutable('+2 hours')` + `time()`).
  → *Injecter `Clock`.*

### Architecture front

Fondations transverses **excellentes** (parité i18n 747/747 testée + garde anti-clé-manquante, coverage `perFile`
exhaustive, `queryKeys`/`invalidate` centralisés, labels unifiés, a11y soignée). Le coût = **volume des pages**.

**Découpage (Partie A)** — plans concrets par fichier :

| Fichier | Lignes | Cible | Extraction |
|---|---|---|---|
| `pages/settings.vue` ⭐ | 556 | ~130 | `BillingSettingsSection`, `MailboxSection`, `AlertFeedsSection` + `useProfileForm()` |
| `pages/admin.vue` ⭐ | 595 | ~120 | 1 composant/section (9) + `<MiniBarChart>` (3 blocs de barres identiques) |
| `pages/leads/[id].vue` | 405 | ~150 | `useLeadDetail(id)` + `LeadTimeline`, `FollowUpBlock` |
| `pages/candidates.vue` | 359 | ~180 | `TriageModal` + `useCandidateQueue()` |
| `pages/organizations/[id].vue` | 300 | ~170 | `useOrganizationDetail(id)` (+ option `OrgContactList`) |
| `components/LeadDraftsSection.vue` | 282 | ~160 | `useDraftGeneration(leadId)` |
| `pages/dashboard.vue` | 266 | ~180 | `<StatCard>`, `<WeeklyActivityChart>`, `<PipelineBar>` |
| `pages/account.vue` | 257 | ~140 | `IdentityForm`, `PasswordChangeForm`, `DangerZone` |

⭐ = prioritaire (le pire, et ce que V2.4 va encore faire grossir).

**Factorisations transverses (Partie B)** :

- **F-P1 — Rattrapage async (`setTimeout` + cleanup) réimplémenté 3× différemment** (`leads/[id].vue`,
  `candidates.vue`, `LeadDraftsSection.vue`) → risque de fuite/incohérence, et V2.4 le recopiera.
  → *Composable unique `useCatchUpRefresh(refetch, { schedule })` avec cleanup garanti.*
- **F-P2a — Déballage JSON-LD (`member ?? hydra:member`) 8× + en-têtes `Accept: ld+json` 10×** → *`apiCollection()` + constantes `LD`/`LD_WRITE` dans `useApi.ts`.*
- **F-P2b — Téléchargement de blob dupliqué 3×** (`dashboard`, `account`, `admin`) → *`utils/download.ts`.*
- **F-P2c — `formatDate`/`formatDateTime`/`formatWhen` réécrits dans 7 fichiers** → *`useDateFormat()`.*
- **F-P2d — Clé de cache admin en dur 2×** (`['admin','account',id]`) → *`queryKeys.adminAccount`.*
- **F-P2e — Extraction du détail d'erreur dupliquée** → *`errorDetail()` dans `utils/apiError.ts`.*
- **F-P2f — Code mort `useProfile().updateWeeklyGoal`** (maintenu vivant par un seul test) → *supprimer.*
- **F-P2g (mineur) — Badge « À trier » non rafraîchi en arrière-plan** (contrairement à la cloche) → *aligner sur le polling.*

### Tests & docs

- **T-P1a — ROADMAP obsolète** : V2.2 encore `- [ ]` (description « quotas » périmée) ; vitrine/démo et garde-fou IA
  totalement absents. → *Cocher V2.2, ajouter vitrine/démo + ADR-0032.*
- **T-P1b — Webhook Stripe : anti-rejeu + câblage HTTP non testés.** `BillingApiTest` instancie le contrôleur en
  direct ; jamais de POST HTTP réel sur la route publique, ni de test de l'horodatage hors tolérance (→ 400).
  Sensible (seule source de vérité pour créditer l'accès). → *1 test event signé horodaté −10 min → 400 ; 1 POST HTTP réel.*
- **T-P2a — Index ADR (`decisions/README.md`) figé à 0031** : 0032/0033 existent mais absents. → *Ajouter les lignes.*
- **T-P2b — Aucun ADR pour vitrine/compte démo** (login public sans mot de passe = décision sécurité). → *ADR dédié.*
- **T-P2c — Branches billing non couvertes** : plan annual, `BillingGatewayFailed → 502` (checkout+portal), chemin
  succès du portail. → *1-2 tests via `FakeBillingGateway`.*

**Couverture conforme (vérifiée)** : back-office v2 entièrement couvert par `AdminApiTest` (le trou E2E admin, acté,
est compensé), garde-fou IA exemplaire (`AiBudgetTest` + repli canned prouvé), compte démo (`DemoApiTest` + E2E smoke),
`RlsCoverageTest` complet, garde lecture seule testée.

---

## Thèmes transverses

1. **Le cluster « compte démo »** — S-P1 (capacités), S-P2a (plafond global), H-P2e (Clock), T-P2b (ADR). C'est ma
   feature la plus récente et la moins durcie ; à consolider d'un bloc avant ouverture publique.
2. **Le cluster « IDs »** — H-P1 (impureté domaine), H-P2a (v4 dans index uniques), H-P2b (port `IdGenerator` mort).
   Une seule décision de stratégie les résout tous (lot C).
3. **Le cluster « webhook Stripe »** — S-P2c (dédup event.id) + T-P1b (tests anti-rejeu/HTTP). À traiter ensemble.
4. **Le volume front** — 8 pages fourre-tout + 7 duplications faciles. Les factorisations (lot E) doivent précéder le
   découpage (lot F) pour que celui-ci les réutilise. À faire **avant V2.4**, qui empile sur settings/admin.

## Lots de remédiation proposés (CI verte par lot)

- **Lot A — Sécurité du compte démo (P1, avant prod ouverte)** : brider `ROLE_DEMO` (IA canned forcée, mailbox/envoi
  refusés) + plafond global démo + `Clock` dans `DemoController` + ADR démo + tests. [S-P1, S-P2a, H-P2e, T-P2b]
- **Lot B — Durcissement Billing/webhook** : `UNIQUE` partiel `stripe_customer_id` + dédup `event.id` + tests
  (anti-rejeu, POST HTTP réel, branches annual/502/portail). [S-P2b, S-P2c, T-P1b, T-P2c]
- **Lot C — Stratégie d'IDs (décision requise)** : cf. ci-dessous ; puis `UuidV7` pur-PHP et/ou injection `IdGenerator`,
  eventId cohérents, port utilisé ou retiré. [H-P1, H-P2a, H-P2b]
- **Lot D — Nettoyage hygiène back** : retirer les 18 `getName()` morts + `Assert\All` sur `notificationPreferences`. [H-P2c, H-P2d]
- **Lot E — Factorisations front transverses** : `useCatchUpRefresh`, `utils/download`, `useDateFormat`, `apiCollection`+`LD`,
  `errorDetail`, `queryKeys.adminAccount`, suppression code mort. [F-P1, F-P2a→f]
- **Lot F — Découpage des grosses pages** : settings + admin d'abord, puis leads/[id], candidates, org/[id],
  LeadDraftsSection, dashboard, account. Réutilise le lot E.
- **Lot G — Docs & ADR resync** : ROADMAP (cocher V2.2, ajouter vitrine/démo + IA), index ADR (0032/0033 + nouveaux),
  CLAUDE.md si besoin. [T-P1a, T-P2a]

### Décision requise — lot C (stratégie d'IDs)

- **Option (i) — puriste** : injecter `IdGenerator`/`EventIdGenerator` dans les méthodes d'agrégat et le constructeur
  d'event (ou assigner l'eventId dans l'outbox). Domaine 100 % déterministe/testable. Coût moyen (signatures + fixtures).
- **Option (ii) — pragmatique (recommandée)** : garder l'auto-génération dans le domaine mais via un `UuidV7` pur-PHP
  (eventId ordonnés + cohérents avec les PK), et **documenter le choix par ADR**. Coût faible. Impact perf réel négligeable
  au volume Plume ; on gagne surtout la cohérence et un ADR qui assume la légère impureté.
