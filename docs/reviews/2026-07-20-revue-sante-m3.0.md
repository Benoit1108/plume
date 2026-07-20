# Revue de santé — M3.0 (socle Sourcing) + intermédiaire depuis fin M2 (2026-07-20)

> Revue **intermédiaire** (M3 pas fini : reste M3.1 RSS + M3.2 alertes email). Périmètre :
> tout ce qui a atterri depuis la revue fin M2 (`9a1cfa7..HEAD`, 17 commits, ~4600 lignes) —
> harmonisation visuelle (lots D/E/F), tranche Compte, **nouveau contexte `Sourcing`** (M3.0).
> Méthode : 3 audits indépendants adversariaux (back/sécu, front/a11y, docs/tests). Barème
> objectif par axe, cible **9/10**. La revue « fin M3 » complète se refera après M3.2.

## 1. Verdict

| Domaine | Note | Cible | vs fin M2 (post-remédiation) |
|---|---|---|---|
| Back — DDD/hexa/domaine/CQRS/tests | **7,5/10** | 9 | ↘ (trous de tests, dérive ADR) |
| Sécurité | **7/10** | 9 | ↘ (révocation tokens au changement de mdp) |
| Front / UX / a11y | **7,5/10** | 9 | ↘ (copie 409 trompeuse, finitions a11y) |
| Docs / process | **4/10** | 9 | ↘↘ **récidive n°3** |

**Sous la cible partout** → remédiation avant M3.1. Aucun P0. Le socle Sourcing est
**architecturalement sain** (frontières hexagonales nettes, isolation tenant fail-closed, leçon
P0 fin M2 intégrée) ; les retenues sont des **trous de tests**, une **faille de révocation de
session**, une **tension de conception non résolue** (fusion), des **finitions a11y**, et
surtout une **3e récidive des docs de tête**.

## 2. Le point dur : docs/process 4/10 (récidive n°3)

Les revues fin M1 **et** fin M2 avaient déjà pointé les « docs de tête périmées ». 3e fois, et
la plus grave : la couche **référence canonique** (lecture obligatoire par CLAUDE.md) est
factuellement **fausse** sur le livré, alors que les *nouveaux* artefacts (ADR-0020/0021,
M3-conception, ROADMAP, CLAUDE) sont, eux, impeccables. C'est l'étape « resynchroniser les
références » qui saute systématiquement.

- **DOMAIN-MODEL.md** nomme l'agrégat **`PisteCandidate`** — le code et le glossaire disent
  **`CandidateLead`** (nom faux). Muet sur la machine à états, les 4 events, `dedupHash`,
  l'enum `Source`, la promotion par gateways. Liste un `ParserAlerte` non livré.
- **GLOSSAIRE.md** : `LeadSource` toujours à 4 valeurs alors qu'il a été enrichi
  (`PROZ/LINKEDIN/TRANSLATORSCAFE/RSS`, ADR-0020, table **contractuelle**). Vocabulaire
  Sourcing (Source, CandidateStatus, « À trier », dedupHash) absent ; valeurs résiduelles
  fausses (`DEMARCHAGE_DIRECT/RECOMMANDATION/IMPORT`).
- **api/src/Sourcing/README.md** et **api/src/README.md** déclarent Sourcing « à peupler (M3) »
  alors que le contexte est **vivant**. **api/src/Account/README.md** périmé (ni weeklyGoal, ni
  présentation, ni firstName/mdp).
- **OVERVIEW.md** : carte des contextes au futur (« Sourcing (M3) ») au lieu de « livré M3.0 ».

## 3. Findings consolidés (dédupliqués entre les 3 audits)

### P1 — avant M3.1

| # | Finding | Où |
|---|---|---|
| 1 | **Changement de mot de passe ne révoque AUCUN refresh token.** Une victime qui change son mot de passe pour expulser un attaquant ne l'expulse pas (toutes les autres sessions restent valides). Le docblock revendique à tort une « défense contre session détournée ». | `ChangePasswordController.php:63-65` |
| 2 | **Fusion vers une org qui a déjà une piste active = impossible** (`CreateLead` lève `ActiveLeadAlreadyExists` 409, invariant « 1 piste active/org »). Or c'est le cas **nominal** du dédoublonnage. Tension non résolue avec ADR-0021. **Décision produit requise** (§5). | `MergeCandidateHandler.php:41-50` |
| 3 | **Copie d'erreur 409 du tri générique/trompeuse** : tout 409 → `common.conflict` (« la page a peut-être changé, rechargez ») alors que le vrai remède est « choisir une autre org / un autre nom ». Sur le flux M3.0 le plus utilisé. | `candidates.vue:99-101`, `apiError.ts` |
| 4 | **Docs de tête (récidive n°3)** — cf. §2 : DOMAIN-MODEL (nom faux + muet), GLOSSAIRE (`LeadSource`), READMEs Sourcing/`api/src`, OVERVIEW. | `DOMAIN-MODEL.md`, `GLOSSAIRE.md`, `api/src/{Sourcing,}/README.md`, `OVERVIEW.md` |
| 5 | **Isolation tenant testée en LECTURE seulement.** Les mutations accept/merge/reject ne sont pas testées cross-tenant (le test 404 utilise un id inexistant, pas un id d'un autre tenant) → la régression exacte du P0 fin M2 passerait. CLAUDE.md impose cette couverture. | `CandidateLeadApiTest.php:176,189` |
| 6 | **Flux de tri sans E2E.** Pas de `candidates.spec.ts` alors que chaque autre vertical en a un ; le glisser-déposer kanban n'a **aucun** test (ni unité ni E2E). | `app/e2e/`, `leads/index.vue`, `candidates.vue` |

### P2 — dette tracée

| # | Finding | Où |
|---|---|---|
| 1 | **ADR-0020 ↔ code : dérive.** L'ADR décrit « transactions séparées, échec partiel ». En réalité le dispatch imbriqué sur `command.bus` (doctrine_transaction) est **atomique** ; la sûreté est *émergente* (nesting + index uniques), pas la garde de domaine (`ensurePending()` est appelée **après** les gateways). Footgun : scinder en transactions async casserait l'atomicité → org+piste orphelines. → corriger l'ADR **et** garder PENDING avant les gateways. | `AcceptCandidateHandler.php:62`, `MergeCandidateHandler.php:52`, ADR-0020 |
| 2 | **i18n `pipeline.sources.MANUAL` absent** (FR+EN) → badge « MANUAL » brut. | `i18n/locales/*.json` |
| 3 | **Perte de focus après tri** : l'élément traité disparaît, le focus retombe sur `<body>` (utilisateur clavier perd sa place). | `candidates.vue:95-96,119-121` |
| 4 | **Fuite `prefers-reduced-motion`** : `transition-[width]` de l'aside non gardée. | `layouts/default.vue:26` |
| 5 | **Badge « À trier » invisible au lecteur d'écran en mode replié** (pastille `aria-hidden`, compteur perdu). | `AppNav.vue:55-59` |
| 6 | **Bruit de landmarks** : 8 `<section aria-label>` (colonnes kanban) = 8 régions nommées. | `leads/index.vue:159-167` |
| 7 | **Garde de re-tri non couverte en HTTP** (2e accept → 409 attendu, non testé). | `CandidateLeadApiTest.php` |
| 8 | **Migration : pas de `schema_filter`** → `migrations:diff` bruite (tables de projection non mappées) ; un futur diff proposera de les DROP. | `config/packages/doctrine.yaml` |
| 9 | Nits : `candidate_lead.id` VARCHAR sans borne ; `CandidateAcceptInput.website` sans `Assert\Url` ; `account.vue` couplé à `error.data.detail` (repli générique si l'API renvoie `violations`) ; `Dedup` MANUAL sans externalId → no-op silencieux sans retour ; `Source::toLeadSource()` : seul `PROZ` testé (`MANUAL→JOB_BOARD` non). | divers |

## 4. Plan de remédiation proposé (4 lots)

- **Lot A — sécurité/back critique** : #1 (révocation des refresh tokens au changement de mdp),
  #2 (fusion sur org à piste active — selon décision §5), #5 (test isolation tenant sur les
  mutations), P2-1 (corriger ADR-0020 + garder PENDING avant gateways).
- **Lot B — tests** : #6 (E2E `candidates.spec.ts` + test DnD), P2-7 (409 re-tri en HTTP),
  test d'atomicité de la promotion, `Source::toLeadSource(MANUAL)`.
- **Lot C — front/UX/a11y** : #3 (messages 409 dédiés du tri), P2-2 (i18n MANUAL), P2-3 (focus
  après tri), P2-4 (reduced-motion sidebar), P2-5 (badge replié SR), P2-6 (landmarks).
- **Lot D — docs de tête (priorité vu 4/10)** : #4 — DOMAIN-MODEL (nom + machine à états/events/
  dedup/promotion + Profile identity + LeadSource), GLOSSAIRE (LeadSource enrichi + vocab
  Sourcing), READMEs Sourcing/`api/src`/Account, OVERVIEW (Sourcing livré). + P2-8 (`schema_filter`).

## 5. Décision produit à arbitrer — fusion sur organisation à piste active

Le dédoublonnage amène souvent une candidate vers une **organisation déjà dans le pipeline**
(donc avec une piste active). Or « 1 piste active par org » (M1.2) interdit d'en créer une 2e.
Options :
- **(A)** *Fusionner = rattacher à la piste active existante* (statut `MERGED`, **sans** nouvelle
  piste ; journaliser « annonce rattachée » sur la piste). Le plus cohérent métier.
- **(B)** *Refus explicite* avec message clair (« une piste active existe déjà — ouvrez-la »)
  + lien vers la piste. Simple, mais laisse l'annonce en attente.
- **(C)** Autoriser une 2e piste (lèverait l'invariant M1.2 — non recommandé).

## 6. Ce qui est objectivement solide (à préserver)

- **Isolation tenant fail-closed** cohérente : `find` en QB (pas `em->find`) pour forcer le
  SQLFilter, DBAL avec tenant explicite partout (leçon P0 fin M2 **intégrée**).
- **Défense en profondeur** : garde de domaine + index uniques → double-submit sûr même en
  concurrence.
- **Frontières hexagonales nettes** (cross-contexte uniquement par gateways+commandes, domaine
  pur, deptrac 0 violation) ; **erreurs typées** + **inputs bornés**.
- **Front** : DnD optimiste piloté par `allowedActions` (autorité serveur), motion
  `reduced-motion` exhaustif, parité i18n FR/EN, skeletons `role=status`, modales Nuxt UI
  (focus-trap/Échap/aria).
- **Pyramide back Sourcing** (domaine/appli/fonctionnel) + ADR-0020/0021 excellents et indexés.
