# Revue de santé — fin M1 (2026-07-14)

> Process acté : revue complète à la clôture de chaque jalon pour **objectiver la note**
> (cible ≥ 9/10 back et front, fixée à la revue M1.1). Méthode : 4 audits indépendants
> en parallèle (DDD/hexa back, sécurité, front, docs/process), findings vérifiés
> fichier:ligne, P0 revérifié à la main avant publication. Périmètre : M1.3 → M1.5
> (46 commits depuis la revue du 2026-07-13) + non-régression M1.1/M1.2.
> Métriques au jour de la revue : 124 tests back (31 fonctionnels Postgres),
> 42 tests front (coverage ~97 %, seuils bloquants intacts), 10 E2E, CI verte,
> phpstan max 0 erreur, deptrac 0 violation.

## 1. Verdict

| Domaine | Note | Revue M1.1 (post-remédiation) | Tendance |
|---|---|---|---|
| Back — DDD/hexa/domaine/CQRS/tests | **8,5/10** | 9+ | ↘ dette de croissance |
| Back — sécurité | **8,5/10** | 9+ | ↘ 2 P1, 0 P0 |
| Front | **8/10** | 9+ | ↘ dette de croissance |
| Docs/process | **7/10** | 9 | ↘ docs « de tête » non resynchronisées |

**Le socle a tenu : aucune régression sur les remédiations M1.1** (les 4 audits le
confirment indépendamment — exceptions→HTTP, read models fail-closed répliqués à
l'identique sur chaque nouvelle surface, outbox, rotation refresh, gitleaks, seuils
de coverage jamais baissés). La baisse de note n'est **pas** une régression : c'est
la dette normale de trois jalons livrés vite — et elle est précisément cartographiée.

**Sous la cible de 9/10** → remédiation en 3 lots (§4) avant M2.

## 2. Le point dur : 1 P0

**`Draft::complete()` et `fail()` sans garde d'état** (`api/src/Drafting/Domain/Draft/Draft.php:85-106`).
Messenger livre *at-least-once* : une redélivrance de `DraftRequested` (crash worker
entre traitement et ack) ré-exécute la génération →

- second appel API Anthropic **facturé** ;
- **écrasement silencieux** d'un brouillon potentiellement déjà relu/édité par la traductrice ;
- doublon `draft_generated` dans le journal (nouvel `eventId` : l'idempotence
  `ON CONFLICT (event_id)` ne protège pas) ;
- un `FailDraft` retardataire peut rétrograder un READY en FAILED.

Correctif : exiger `GENERATING` dans `complete()`/`fail()` (Conflict sinon) + le
consumer traite Conflict/NotFound en no-op. La machine à états `Lead`, elle, est
exhaustive — le pattern existait, il n'a pas été appliqué au nouvel agrégat.

## 3. Findings consolidés (dédupliqués entre audits)

### P1 — avant M2 (chaque item est amplifié par la passerelle email)

| # | Finding | Où |
|---|---|---|
| 1 | **Aucun rate limiting sur la génération IA** (`POST /leads/{id}/drafts`, `/drafts/{id}/regenerate`) : coût Anthropic non borné par utilisateur authentifié + monopolisation du worker unique (30 s/appel) qui retarde les projections. Promis par OVERVIEW §Sécurité. | `rate_limiter.yaml` |
| 2 | **`body` non borné** sur `draft:edit` et `template:write` (colonnes TEXT, et le gabarit part intégralement dans le prompt) ; `templateId` sans contrainte de format ; `draft.subject` VARCHAR(255) face à un sujet généré non borné (tronquer dans l'adaptateur). Régression du réflexe « bornes » acquis en M1.1 (import). | `DraftResource.php`, `TemplateResource.php`, `ClaudeMessageGenerator.php` |
| 3 | **`DraftGenerationConsumer` + `DraftPromptBuilder` : zéro test** — la re-vérification RGPD post-hoc, les 3 codes d'échec et la dégradation « gabarit supprimé » ne sont exercés nulle part ; `DraftNotFound` non rattrapé (suppression pendant génération → retries + queue failed pour un cas normal). Les fakes nécessaires existent déjà. | `DraftGenerationConsumer.php` |
| 4 | **deptrac ne vérifie que les couches, pas les frontières inter-contextes** — la règle centrale de CLAUDE.md (« contexte→contexte par ID ou port ») n'est pas outillée ; la discipline a tenu (vérifié : 3 seuls imports croisés, tous défendables), mais M2 ajoute un contexte. | `deptrac.yaml` |
| 5 | **E2E : protection anti-course illusoire** — `fullyParallel: false` ne sérialise qu'au sein d'un fichier ; les 5 fichiers tournent en parallèle sur le **même tenant** e2e (cause racine des flakes observés, masqués par `retries: 1` en CI) ; helpers dupliqués ×5 avec dérive déjà constatée (`today.spec.ts` attend encore l'URL d'avant M1.3). | `playwright.config.ts`, `app/e2e/*` |
| 6 | **`pages/leads/[id].vue` : 652 lignes, 4 responsabilités** — extraire `LeadDraftsSection` (et à terme `LeadTimeline`) avant que M2 n'étoffe encore la fiche. | `app/pages/leads/[id].vue` |
| 7 | **Calculs du dashboard non testés** (conversion, `weeklyMax`/hauteurs de barres, `segmentRate`) : logique dans la page, hors périmètre coverage, E2E de présence seulement. Extraire en composable pur + tests. | `app/pages/dashboard.vue:26-68` |
| 8 | **Docs « de tête » périmées** : README racine figé à M1.2 (récidive du P0 n°8 de la revue M1.1), DOMAIN-MODEL resté à la conception (Draft « transient », events/invariants inexacts), `api/src/README.md` figé à M0, note parapluie M1 jamais soldée (DoD §14 décochée, « décisions ouvertes » résolues de fait). | `README.md`, `docs/architecture/DOMAIN-MODEL.md`, `api/src/README.md`, `docs/design/M1-conception.md` |
| 9 | **ADR-0014 (intégration IA) promis et jamais écrit** alors que M1.4 porte la décision la plus structurante du jalon — y inclure le volet RGPD sous-traitant (le prompt envoie le **nom du contact prospect** à Anthropic ; envisager l'interpolation locale de `{{contact}}` après génération). ADR-0015 (import CSV) idem. | `docs/architecture/decisions/` |
| 10 | **Process E2E non documenté** (lancement local, user `e2e@plume.test`, prérequis stack) : ne vit que dans `ci.yml`. | `README.md` / `CLAUDE.md` |

### P2 — dette tracée (sélection, liste complète dans les audits)

- Worker sans garde tenant *mécanique* : `CompleteDraft`/`FailDraft` sans tenant, chargement
  non scopé — sûr par construction aujourd'hui ; transporter le tenant + vérifier au chargement
  (le « stamp Messenger » évoqué dès M1.1). `TenantContext::require()` pour normaliser le
  fail-closed dupliqué ×7.
- Seed des gabarits dispatché dans un **GET** (`TemplateProvider`), check `count()` TOCTOU sans
  filet unique — déplacer ou verrouiller, et tracer la décision.
- Helpers d'hydratation DBAL dupliqués ×9 avec divergences (trim, '' vs null) → helper partagé.
- `recordReply()` → 409 si déjà IN_DISCUSSION : bloquant pour les réponses automatiques M2.
- `Profile.timezone` figé (ni domaine ni API) — assumer par écrit ou exposer ; `Template` sans
  `updatedAt` ; events d'édition pauvres (IDs seuls) vs ambition « events riches ».
- Journal `interaction` append-only sans stratégie d'effacement (texte des notes = données
  perso potentielles) — décision de rétention à tracer avant M2.
- `npm audit` non bloquant sans distinction runtime/dev ; mot de passe e2e en clair dans les
  logs CI ; cookies tokens non-httpOnly (reste assumé, à re-prioriser dès M2 : contenus
  d'emails entrants = surface XSS réelle).
- Front : timers timeline non nettoyés à l'unmount, `JsonLdCollection` ×3, 4 clés i18n mortes,
  « décidées » invariable dans `conversionDetail`, labels « Copier » ambigus (a11y + E2E),
  polling brouillons qui abandonne en silence après ~12 s, `settings.vue` objectif vidé → PATCH
  invalide, erreurs 409 métier non distinguées du toast générique.
- Docs : index ADR sans 0012/0013, OVERVIEW « Nuxt 3 » + tenancy sans le volet ADR-0013,
  ROADMAP « M1 🚧 » avec 5 slices ✅, statuts des notes M1.2/M1.3, pièges (openapi après
  cache:clear, worker dans `make up`) absents de CLAUDE.md, config Claude locale non documentée.

## 4. Plan de remédiation proposé (3 lots)

- **Lot A — back critique** : P0 (gardes d'état `Draft` + no-op Conflict/NotFound consumer),
  rate limiter génération par tenant, bornes `body`/`templateId`/troncature sujet, tests
  consumer + prompt builder + parse Claude (+ test d'écriture cross-tenant), deptrac
  par contexte, tenant explicite dans `CompleteDraft`/`FailDraft`.
- **Lot B — front** : E2E `workers: 1` + helpers partagés (`e2e/helpers.ts`) + resynchroniser
  `today.spec.ts`, extraction `LeadDraftsSection` + composable `useDashboardMetrics` testé,
  signal d'abandon du polling + bouton rafraîchir, P2 rapides (timers, `types/api.ts`,
  clés mortes, labels Copier, garde de saisie settings).
- **Lot C — docs/process** : resynchroniser README/DOMAIN-MODEL/`api/src/README`/index ADR/
  ROADMAP/note parapluie, écrire **ADR-0014** (IA : ACL, canned par défaut, coûts, RGPD
  sous-traitant) + solder ADR-0015 (écart tracé), documenter les E2E et les pièges dans
  CLAUDE.md, acter par écrit les choix assumés (timezone figée, seed via GET ou son
  déplacement, rétention du journal).

Projection après remédiation : back ≥ 9 (le P0 et les 4 P1 back concentrent l'écart),
front ≥ 9 (les 3 P1 front sont mécaniques), docs ≥ 9 (une passe d'une demi-journée).

## 5. Ce qui est objectivement solide (à préserver tel quel)

- Frontières DDD réelles : zéro dépendance framework dans Domain **et** Application (vérifié
  par grep exhaustif), 3 seuls points de contact inter-contextes, tous par port/event/Link.
- Fail-closed tenant **systématique et testé** sur les 10 surfaces de lecture ; gateways à
  tenant explicite pour le worker ; aucune injection SQL (paramètres liés partout, y compris
  les fuseaux et les listes).
- Flux asynchrone M1.4 bien conçu dans l'ensemble : tenant dans l'event, re-vérification RGPD
  par le worker, codes d'échec stables i18n, ACL Anthropic étanche, clé jamais commitée
  (grep de tout l'historique).
- Pyramide de tests disciplinée et assertions exigeantes ; i18n 285 clés strictement paritaires
  FR/EN ; a11y des graphes exemplaire ; garde console/hydratation sur chaque E2E.
