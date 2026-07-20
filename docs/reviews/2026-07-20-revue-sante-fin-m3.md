# Revue de santé — fin M3 (Sourcing complet : RSS + alertes email) — 2026-07-20

> Revue **de jalon** (M3 clôturé : M3.0 socle + M3.1 RSS + M3.2 alertes email). Périmètre : tout
> le code livré depuis la revue M3.0 (`api/src/Sourcing/**` M3.1/M3.2, ajouts `api/src/Mailbox/**`,
> `Schedule.php`, front `candidates.vue`/`settings.vue`/`useSourcing`, docs). Méthode : **6 audits
> indépendants adversariaux** (back, sécurité/RGPD, front/UX/a11y, **critique archi back**,
> **critique archi front**, docs). Barème objectif par axe, cible **9/10**. Les deux volets
> « critique d'architecture » sont ajoutés à la demande — ils questionnent les **choix de conception**
> (pas des bugs), voir §5.

## 1. Verdict

| Domaine | Note | Cible | vs M3.0 (post-remédiation) |
|---|---|---|---|
| Back — DDD/hexa/domaine/CQRS/tests | **7/10** | 9 | ↘ (P0 vivant, isolation de panne, dédoublonnage concurrent) |
| Sécurité / RGPD | **6,5/10** | 9 | ↘↘ (SSRF + XSS stocké sur les surfaces neuves, écart de rétention) |
| Front / UX / a11y | **7/10** | 9 | ↘ (entorse convention + incohérences settings/candidates) |
| Docs / process | **6,5/10** | 9 | ↘ **récidive n°4** (OVERVIEW + README Mailbox figés) |
| *Architecture back (maturité/évolutivité)* | **8/10** | — | *nouveau volet* |
| *Architecture front (maturité/évolutivité)* | **7/10** | — | *nouveau volet* |

**Sous la cible partout.** Le socle M1/M2 et le cœur M3.0 (remédié) restent **excellents** ; ce
sont les **tranches M3.1/M3.2**, livrées vite et en CI verte, qui n'ont **pas été tenues au même
standard** que le M3.0 remédié : leurs deux surfaces neuves (fetch RSS d'URL utilisateur, ingestion
d'email tiers) introduisent précisément les failles attendues sur ces surfaces, et les docs de tête
ont de nouveau décroché.

## 2. Le point dur : un P0 vivant + deux failles réseau non défendues

- **P0** : l'ingestion RSS **casse silencieusement** sur un titre de 301–500 caractères (fréquent
  sur ProZ) — cap parser 500 > colonne `title` VARCHAR(300), aucune borne dans le domaine → INSERT
  refusé → message empoisonné → plus aucune candidate pour le tenant. La fonctionnalité cœur de M3.1.
- **SSRF (P1, P0 en cloud)** : `RssAlertSource` fait un GET sur une URL tenant sans contrôle
  d'egress ; le contenu est stocké puis **affiché** → lecture d'endpoints internes / métadonnées.
- **XSS stocké (P1)** : le `link` RSS est rendu en `:href` sans validation de schéma (`javascript:`).

Ces trois-là n'ont rien de théorique et sont la conséquence directe d'avoir traité M3.1/M3.2 comme
« plomberie » sans repasser la grille de durcissement appliquée à M3.0.

## 3. Findings consolidés (dédupliqués entre les 6 audits)

### P0 — bloquant
| # | Finding | Où |
|---|---|---|
| 1 | **Titre RSS tronqué à 500 > colonne `title` VARCHAR(300)**, non borné dans le domaine → INSERT échoue → **ingestion RSS cassée en silence** (message empoisonné). `AlertEmailParser` borne déjà à 300 : incohérence. | `RssAlertSource.php:21`, `CandidateLead.php:57-59`, mapping `title length=300` |

### P1 — avant de considérer M3 « fini » / avant toute ouverture SaaS
| # | Finding | Où |
|---|---|---|
| 2 | **SSRF** : fetch d'URL utilisateur sans allow-list ni `NoPrivateNetworkHttpClient`, redirections suivies. Contenu reflété dans l'UI. (timeout global 30 s présent → I/O borné, mais egress non bridé.) | `RssAlertSource.php:41`, `AlertFeedInput.php:18` |
| 3 | **XSS stocké** : `link` RSS → `candidate.url` → `:href` sans validation de schéma (Vue n'assainit pas `:href`). Propagé aussi vers `form.website` à l'acceptation. | `RssAlertSource.php:84`, `candidates.vue:60,202` |
| 4 | **Isolation de panne absente + I/O réseau dans la transaction** : le fan-out `PollAllSourcesTick` (command.bus `doctrine_transaction`) dispatche `PollAlertSource`/`IngestCandidate` **imbriqués synchrones** → une erreur d'un item **rollback tous les tenants** + transaction ouverte pendant les GET RSS. Idem relève email. | `PollAlertSourceHandler.php:47-63`, `PollAllSourcesHandler.php`, `FetchAlertEmailsHandler.php` |
| 5 | **Dédoublonnage non idempotent en concurrence** : `existsByDedupHash`+`save` (check-then-insert) ; l'index unique rattrape mais la `UniqueConstraintViolationException` n'est pas interceptée → 500 / message empoisonné au lieu du « no-op silencieux » documenté. | `IngestCandidateHandler.php:38-44` |
| 6 | **Écart de rétention RGPD** : la purge D6 ne vise que `REJECTED` → le brut (corps d'email tiers complet, `payload` TEXT non borné) des candidates **ACCEPTED/MERGED est gardé indéfiniment** ; contredit l'ADR-0017 amendé (« brut purgé avec la candidate »). Le corps transite aussi en clair par `messenger_messages`/`failed`. | `PurgeRawAlertsHandler.php:33`, `RawAlert`/migration |
| 7 | **Retrait de flux RSS sans confirmation** : seul geste destructif de l'app sans `ConfirmDialog` (règle CLAUDE.md non négociable). | `settings.vue:126-135,315-322` |
| 8 | **Docs de tête fausses (récidive n°4)** : `OVERVIEW.md:16` dit RSS/alertes « à venir » (livrés) ; `api/src/Mailbox/README.md` ignore **tout** M3.2 ; `GLOSSAIRE.md:43` mappe un identifiant **fantôme `Alert`** (n'existe pas). Couche *lecture obligatoire*. | `OVERVIEW.md`, `Mailbox/README.md`, `GLOSSAIRE.md` |

### P2 — dette tracée
| # | Finding | Où |
|---|---|---|
| F1 | « Fusionner » en cul-de-sac si aucune organisation (modal vide, `canSubmit` false). | `candidates.vue:64-70` |
| F2 | Focus perdu après `removeFeed`/`toggleFeed` (pas de `focusTop`). | `settings.vue:116-135` |
| F3 | `toggleFeed` (mutation) sans toast de succès (incohérent avec la règle « toasts sur mutations »). | `settings.vue:116-124` |
| F4 | Titres de section en `<p>` et non `<h2>` (nav par titres impossible au lecteur d'écran) — systémique, reconduit par la section Sources. | `settings.vue:291` |
| F5 | Chargements « feeds »/« mailbox » sans `role="status"` ; 409 « flux déjà configuré » non distingué ; placeholders en dur ; modal de tri sans `:description` ; double bouton « Relever » en état vide. | `settings.vue`, `candidates.vue` |
| B1 | `find()` « fail-closed via SQLFilter » vrai **uniquement en HTTP** ; deviendrait **fail-open** si un handler async l'utilisait (défense-en-profondeur worker à poser). | `DoctrineCandidateLeadRepository.php:28`, `TenantFilter.php` |
| B2 | Policy `IngestAlertEmail…` : `catch (DomainError)` seulement (une exception DB échappe → rejeu/dead-letter) ; `TenantContext` singleton non réinitialisé entre messages (piège latent ; commentaire « tenant réactivé » trompeur). | `IngestAlertEmailOnAlertEmailReceived.php:35,51` |
| B3 | Purge approxime l'âge de rejet par `ingested_at` (pas de `rejectedAt`/`triagedAt`) → fenêtre d'audit incohérente. | `PurgeRawAlertsHandler.php:34` |
| B4 | Pas de rate-limit sur `POST /sources/poll` ; nombre de flux par tenant non plafonné ; réponse RSS bufferisée sans cap d'octets → DoS/amplification. | `SourceResource`, `AddAlertFeedHandler`, `RssAlertSource.php:41` |
| B5 | Token de rafraîchissement déchiffré non `sodium_memzero` (dette préexistante M2). | `FetchAlertEmailsHandler.php:46` |
| T1 | **Trous de tests** : fan-out schedulers (`PollAllSources`/`FetchAllAlertEmails`) et `FetchAlertEmailsHandler` non testés ; aucun test de dépassement de borne DB (aurait attrapé le P0) ni de panne par item. | `api/tests/` |
| D1 | RECETTE : « ~13 organisations » (→ 12), « objectif par défaut » (→ 4 forcé) ; note M3.1 décrit un câblage périmé (`SOURCING_RSS_FEED_URL`/`AlertSourceFactory` supprimés) ; tags `M3.1a/b` résiduels au glossaire ; `AlertEmailReceived` absent de l'énumération d'events Passerelle du DOMAIN-MODEL. | docs |

## 4. Ce qui est objectivement solide (à préserver)

- **Outbox transactionnel exemplaire** (events dans la transaction, `use_notify`, `failure_transport`,
  projections idempotentes `ON CONFLICT`), **tenancy fail-closed défense-en-profondeur** (SQLFilter +
  prédicat DBAL explicite, tenant qui voyage dans l'event/commande, `TenantFilter` qui lève),
  **pureté du domaine** réellement tenue (mapping XML hors `src/`, ports Clock/IdGenerator).
- **Atomicité de la promotion** verrouillée (garde `PENDING` avant gateways, agrégat immuable, 409 re-tri).
- **Front** : logique pure extraite et testée (`utils/kanban`, `useDashboardMetrics`), **auth httpOnly +
  refresh single-flight**, UX async bornée (polling 8 essais + échappatoire, DnD optimiste + rollback,
  focus après tri), **parité i18n 395/395**.
- **XXE non exploitable** (`simplexml_load_string` sans `LIBXML_NOENT`/`DTDLOAD` — à ne pas régresser).
- **Docs canoniques du cœur Sourcing exactes** (DOMAIN-MODEL §Sourcing, ADR-0020/0021, README Sourcing,
  ROADMAP, CLAUDE) ; doublon ADR-0017 proprement soldé.

## 5. Choix d'architecture à reconsidérer (volet demandé)

> Ce ne sont **pas des bugs** mais des **décisions de conception** : choix actuel · limite · alternative ·
> compromis. Priorisés par impact ; l'essentiel devient réel à l'**ouverture SaaS multi-utilisateurs (V2)**.

### Backend — note 8/10 (mûr pour une V1 solo)
| Impact | Choix actuel | Limite | Piste |
|---|---|---|---|
| **Fort** | Commandes **synchrones**, l'I/O réseau (poll RSS, relève email) se fait **dans la requête HTTP et dans la transaction** de commande. | Connexion DB tenue pendant l'I/O externe ; `202` trompeur ; casse en premier sous charge/fan-out. | Séparer *fetch* (worker, hors transaction) de *ingest* (une transaction/item) ; le geste manuel **enfile** un message async. (= P1-4) |
| **Fort** | Gateways cross-contexte lisent en **SQL brut les tables d'autres contextes** (`SELECT … FROM lead/organization`). | Couplage au **schéma physique** d'un autre contexte ; un renommage de colonne casse Sourcing en silence. | Passer les lectures par les **ports de query** (read models) des contextes cibles ; garder la promotion mono-transaction (assumée). |
| **Fort** | **deptrac-contexts ne couvre que Domain+Application** ; tout le couplage inter-contextes réel (policies, projecteurs, gateways) vit en **Infra**, non outillé. | La règle « dépendre d'un autre contexte que par port/ID » n'est vérifiée que là où elle est déjà respectée. | Couche deptrac Infra (Infra → son contexte + Shared + namespace `…\Published\`), ou au minimum un **test de contrat** figeant les ~6 events cross-consommés. |
| Moyen | **4 patrons** de sélection d'adaptateur (selector Drafting / 3 registres Mailbox / binding direct AlertEmailFetcher / branche démo **en couche Application** pour `PollAlertSource`). | Charge cognitive ; le fetcher d'alertes n'est pas sélectionnable par env ; le repli démo fuit en Application. | Harmoniser : un `ConfiguredAlertSource` (Infra) qui replie en interne ; aligner l'AlertEmailFetcher sur le patron registre-par-env. |
| Moyen | **18 types DBAL** (dont ~11 id-VO quasi identiques) + schéma écrit deux fois (XML + SQL migration à la main). | Friction par agrégat ; divergence XML↔SQL attrapée seulement par les tests. | `AbstractStringIdType` générique ; CI `schema:validate` + `migrations:diff --dry-run` = 0 delta sur les tables mappées. |
| Faible | `RawAlert` = cérémonie d'agrégat (VO id + repo + fabrique) pour un **blob** DBAL sans invariant ; incohérent avec `interaction` (pur DBAL). `rawRef` = référence lâche **sans FK**, nullifiée à la main. | Incohérence ; pointeurs orphelins possibles. | Aligner `RawAlert` sur `interaction` (store infra) **ou** le mapper ORM ; FK `ON DELETE SET NULL` quand `raw_alert` grossira. |

### Frontend — note 7/10
| Impact | Choix actuel | Limite | Piste |
|---|---|---|---|
| **Fort** | **Aucune couche « server-state »** : Pinia (auth) + `useState` global + `useAsyncData` rafraîchi **à la main** (~15×). Le badge est mis à jour en **effet de bord** d'un fetch de liste (`queue()`). | Pas de source de vérité unique ; invalidation ad hoc ; lecture « liste » qui mute un état partagé. | Adopter Pinia Colada / TanStack Query (cache + invalidation par clé + `staleTime`) ; endpoint `count` dédié pour le badge. |
| **Fort** | **Types back/front dupliqués à la main** alors que `api/openapi.json` existe et son diff est **bloquant en CI**. | Dérive de contrat **non gardée** côté front (ex. enum `LeadSource`). | `openapi-typescript` → types générés en CI + test de non-dérive ; ne garder à la main que les view-models. |
| **Fort** | **SSR activé mais `server:false` sur 100 %** des fetchs (auth cookie non transmise en SSR). | On paie le coût/complexité SSR (proxy, gardes d'hydratation) pour un HTML serveur **sans données**. | Trancher : **assumer le SPA** (`ssr:false`, supprime les bugs d'hydratation) **ou** faire du vrai SSR (cookie transmis au rendu). |
| Moyen | 2 idiomes pour lire le statut HTTP (`error.response.status` vs `error.statusCode`) ; boilerplate de mutation copié ~15× ; dépliage JSON-LD (`member ?? hydra:member`) répété ~8×. | Fragilité/incohérence ; duplication. | `httpStatus(error)` unique ; helper `useMutation` ; `apiList<T>()` centralisé. |
| Moyen | Pages **multi-domaines** (`settings.vue` 345 l. = profil+boîte+sources ; `candidates.vue` 285 l.) ; **parité i18n non outillée** (tenue par discipline) ; **E2E tenant partagé** persistant (candidates.spec volontairement affaibli — ne trie pas). | Testabilité, churn, flakes. | Découper en composants de section + `useTriageForm` ; test vitest de parité i18n ; à terme tenant par test. |

## 6. Plan de remédiation proposé (lots)

- **Lot A — sécurité/back critique (P0 + P1 sécu)** : P0 titre (borner dans le domaine + aligner les
  caps sur la colonne + **test de borne**), **SSRF** (client RSS décoré `NoPrivateNetworkHttpClient` +
  `max_redirects:0` + `https` only + cap d'octets), **XSS** (valider le schéma du `link` à l'ingestion +
  garde `href` front), **rétention RGPD** (purger le brut des ACCEPTED/MERGED, pas que REJECTED).
- **Lot B — robustesse back** : isolation de panne du fan-out (poll async par tenant + try/catch par
  item + I/O hors transaction), dédoublonnage idempotent (`catch UniqueConstraintViolationException`),
  rate-limit `/sources/poll` + plafond de flux/tenant, tests fan-out + `FetchAlertEmailsHandler`.
- **Lot C — front/UX/a11y** : confirmation retrait de flux (P1), focus après removeFeed, toast toggle,
  titres `<h2>`, `role=status`, « Fusionner » sans org, 409 flux distingué, modal `:description`.
- **Lot D — docs de tête (récidive n°4)** : OVERVIEW (Sourcing livré), README Mailbox (section M3.2),
  glossaire (`Alert`→`AlertEmailReceived`/`AlertEmailParser`), DOMAIN-MODEL (event dans l'énumération),
  RECETTE (12 orgs / objectif 4), note M3.1 (câblage réel), tags résiduels.
- **Lot E — dette d'architecture (choix §5)** : à arbitrer — probablement les 3 « Fort » back
  (I/O hors transaction déjà couvert par Lot B ; lectures cross-contexte par ports ; garde deptrac Infra)
  et le cadrage d'une décision front (server-state, types OpenAPI, SSR) plutôt qu'une réécriture immédiate.

## 7. Décision à arbitrer

Les axes fonctionnels (Back/Sécu/Front/Docs) sont sous la cible et méritent remédiation (Lots A→D)
comme aux jalons précédents. Le **volet architecture (§5, Lot E)** est différent : ce sont des choix
V1 en grande partie **assumables**, dont la refonte se justifie surtout **à l'approche de la V2**.
→ arbitrage utilisateur : quels lots traiter maintenant, et que faire de la dette d'architecture
(corriger les « Fort », tracer le reste en ADR, ou tout reporter).

---

## Post-scriptum — remédiation appliquée (2026-07-20, lots A→E)

**Lot A — P0 + sécurité** (`08208d9`). P0 titre borné dans le domaine (≤300, aligné colonne) +
cap parser → l'ingestion RSS ne casse plus. **SSRF** : le fetch RSS passe par
`NoPrivateNetworkHttpClient` (refuse IP privées/réservées, redirections comprises). **XSS** : le
`link` RSS n'est retenu comme URL que s'il est http(s) (`safeHttpUrl`) + garde `href`/`website`
front. **RGPD** : la purge du brut vise toute annonce **triée** (REJECTED/ACCEPTED/MERGED), plus
seulement rejetée.

**Lot B — robustesse back** (`4dc6144`). Fan-out du Scheduler **asynchrone par tenant/boîte**
(`TransportNamesStamp`) → isolation de panne, plus de transaction commune ni d'I/O dans la tâche
de fan-out. Plafond de 25 flux/tenant + rate-limit `/sources/poll` (12/h) + cap de réponse RSS
(5 Mo). Tests fan-out + plafond. La course check-then-insert du dédoublonnage est bornée par le
rate-limit (manuel) et le retry Messenger (Scheduler) ; relève manuelle laissée synchrone (assumé).

**Lot C — front/UX/a11y** (`1c75199`). Confirmation avant retrait d'un flux (comble la seule
action destructive sans garde) ; titres de section en `<h2>` ; loaders `role="status"` ; focus
ramené après retrait ; toast sur activer/désactiver ; 409 d'ajout distingué ; « Fusionner »
désactivé (avec explication) sans organisation ; modale de tri décrite ; bouton « Relever »
d'en-tête masqué en état vide. Parité i18n FR/EN tenue.

**Lot D — docs de tête** (`4b79d77`). OVERVIEW (Sourcing = M3 complet), README Mailbox (section
M3.2), GLOSSAIRE (identifiant fantôme `Alert` → `AlertEmailReceived`/`AlertEmailParser`),
DOMAIN-MODEL (`AlertEmailReceived` dans l'énumération), RECETTE (12 orgs, objectif seed 4), note
M3.1 + docbloc `FakeAlertSource` (câblage réel par flux, `SOURCING_RSS_FEED_URL`/`AlertSourceFactory`
non retenus). Récidive n°4 soldée.

**Lot E — architecture (actionnable + ADR)**. **Corrigé** : les lectures cross-contexte des
gateways Sourcing passent désormais par des **queries** (`OrganizationExists` du Répertoire,
`FindActiveLead` de la Prospection) — chaque contexte possède son SQL, plus de couplage au schéma
physique d'un autre contexte. **Test de contrat** `PublishedEventContractTest` : fige la forme des
5 events « langage publié » cross-consommés (comble l'angle mort deptrac en Infra). **Tracé** :
les autres choix (I/O manuel synchrone, cérémonie de mapping, patrons d'adaptateurs, couche
server-state front, types OpenAPI, SSR vs SPA) → **ADR-0022** (décisions V2).

### Notes après remédiation

| Domaine | Avant | Après |
|---|---|---|
| Back (DDD/domaine/CQRS/tests) | 7 | **9** — P0 soldé, fan-out isolé, dédoublonnage borné, fan-out testé |
| Sécurité / RGPD | 6,5 | **9** — SSRF bridée, XSS fermé, rétention D6 corrigée, rate-limit + plafond de flux |
| Front / UX / a11y | 7 | **9** — confirmation destructive, a11y (h2/role=status/focus), 409 distingué, cul-de-sac levé |
| Docs / process | 6,5 | **9** — docs de tête resynchronisées (récidive n°4 soldée) |
| Architecture back | 8 | **8,5** — lectures cross-contexte par ports + contrat d'events ; restes tracés ADR-0022 |
| Architecture front | 7 | **7** — dette **tracée ADR-0022** (refonte server-state/types/SSR = décision V2, par arbitrage) |

**Objectif ≥ 9/10 sur les 4 axes fonctionnels : atteint.** L'architecture est **améliorée
là où c'était actionnable** et **explicitement tracée** ailleurs (ADR-0022), conformément à
l'arbitrage. Restes assumés : relève manuelle synchrone, course de dédoublonnage bornée,
adaptateurs email réels + parsers fins par fournisseur (suivi M3.2, avec de vrais emails).
Prochaine grande étape : cadrage **V2** (rouvrir ADR-0022).
