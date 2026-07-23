# Chantier 3 — Front : SPA + TanStack Query + types OpenAPI (note de cadrage)

- **Statut : RÉALISÉ** (2026-07-23, CI verte à chaque lot) — A `cf97d76`, B `7dea1a3`, C `10c4890`,
  D `3398f3e`/`f07321f`/`1c3fddb`/`3ebb38c`/`600c455`. Écart assumé sur A : la sortie OpenAPI
  d'API Platform étant imprécise (statuts en `string`, nullabilité permissive), on a livré l'infra
  (contrat généré + alarme de drift) sans dériver les types applicatifs — reste possible après un
  durcissement OpenAPI back. Le **poll manuel async** (dette ADR-0022 §1) n'est PAS fait ici (report).
- **Contexte** : dettes front actées en [ADR-0022](../architecture/decisions/0022-dettes-architecture-v2.md) et
  [note PRE-V2](./PRE-V2-cadrage.md) — SSR subi, état serveur géré à la main, types dupliqués.
  Arbitrages déjà pris par Benoit : **TanStack Query**, **SPA `ssr:false`**, **types générés depuis OpenAPI**.

## Objectifs

1. **SPA assumée** (`ssr: false`) : supprimer le SSR subi (aujourd'hui neutralisé à la main par
   `server: false` sur **18** `useAsyncData`). Plume est un outil **privé authentifié** → aucun besoin SEO.
2. **État serveur = TanStack Query** : cache + invalidation déclarative à la place des **18**
   `useAsyncData` + `refresh()` manuels dispersés dans 12 pages/composants.
3. **Types générés depuis `api/openapi.json`** : fin des **7 fichiers de types écrits main**
   (~15 interfaces, `Segment` défini 2×) qui doublent le contrat.
4. **Parité i18n outillée** : `fr.json`/`en.json` (544 lignes chacun) n'ont **aucun test de parité** → en ajouter un, bloquant.

## Ce qui NE change pas (garde-fous)

- **Auth cookies httpOnly même-origine** (ADR-0018) : inchangée. Le store `auth.ts` (témoin
  `plume_email`, refresh mutex single-use, middleware global) et le refresh-on-401 de `useApi`
  restent tels quels — TanStack **enveloppe** `useApi`, il ne le remplace pas.
- **Le proxy `/api` reste** : `ssr:false` **n'est PAS** `nuxt generate` (statique). On garde le
  serveur **Nitro** (`node .output/server/index.mjs`) → `nitro.devProxy` (dev) et
  `routeRules['/api/**'].proxy` (prod) fonctionnent identiquement → cookies même-origine préservés.
  **⚠️ Ne jamais basculer en full-static** (sinon plus de proxy → CORS + cookies cassés).
- Nuxt UI, Pinia, i18n, colorMode, l'endpoint icônes `/_nuxt_icon` : inchangés.

## Lots (ordre = risque croissant + fondations d'abord)

### Lot A — Types générés depuis OpenAPI *(additif, risque faible)*
- `openapi-typescript` (devDep) → `app/types/api-generated.ts` via un script `npm run gen:types`.
- **Contrôle CI « types à jour »** : régénérer + `git diff` bloquant (jumeau du diff `openapi.json` back).
- Migration **incrémentale** des 7 fichiers `types/*.ts` : les formes DTO viennent du généré ; on
  garde de **fins alias applicatifs** + les utilitaires `Input` (`Pick`/`Omit`). ⚠️ La sortie
  API Platform (JSON-LD/hydra) est verbeuse → on n'impose **pas** un 1:1, on extrait les DTO utiles.

### Lot B — Test de parité i18n *(additif, risque nul)*
- Test Vitest : mêmes ensembles de clés (récursif) entre `fr.json` et `en.json`. Bloquant en CI.

### Lot C — Bascule SPA `ssr: false` *(fondation, risque E2E)*
- `ssr: false` dans `nuxt.config`. Retrait progressif des `server: false` devenus inutiles.
- **Points de vigilance** :
  - **E2E** : `waitForHydration()` attend `#__nuxt` hydraté — en SPA l'app **monte** côté client
    (pas d'hydratation SSR). À adapter (attendre un marqueur d'app montée) + rejouer les 11 specs.
  - **1er rendu** : shell vide tant que le bundle JS charge → prévoir un **écran de chargement** minimal.
  - `login.vue` : le fallback formulaire natif « pré-hydratation » devient sans objet (inoffensif).
- Fait **avant** TanStack : valide la fondation SPA isolément **et** simplifie TanStack (pas de
  déshydratation/hydratation SSR du cache).

### Lot D — Migration TanStack Query *(le gros œuvre, incrémental par ressource)*
- Plugin Nuxt `VueQueryPlugin` + `QueryClient` (défauts : staleTime raisonnable, retry aligné sur
  le 401-refresh de `useApi`). `queryFn`/`mutationFn` appellent **`useApi`** (le refresh-on-401 mutex reste).
- Chaque ressource : le composable expose `useXxxQuery()` / `useXxxMutation()` (clés de cache
  stables), les pages consomment ; **invalidation** remplace `refresh()`.
- **Ordre** (pattern d'abord sur du simple, puis le lourd) : `dashboard`/`today` (lecture seule) →
  `mailbox`/`account`/`profile` → `sourcing` → `directory` → `drafts` → `leads` (+ timeline, polling).
- Cas à préserver : dépagination JSON-LD, **polling** des brouillons `GENERATING` (→ `refetchInterval`
  conditionnel), rafraîchissements conjoints (`lead` + `timeline`), compteur partagé `sourcing-pending`.

## Risques & parades (synthèse)

| # | Risque | Lot | Parade |
|---|--------|-----|--------|
| 1 | Proxy/cookies cassés en SPA | C | Garder Nitro (pas de static) ; `routeRules` proxy inchangé ; valider `/api` + login en E2E |
| 2 | `waitForHydration` E2E inadapté | C | Adapter le helper au montage SPA ; rejouer les 11 specs |
| 3 | Types OpenAPI verbeux (hydra) | A | Alias applicatifs fins au-dessus du généré ; pas de 1:1 forcé |
| 4 | Dérive types ↔ contrat | A | Contrôle CI « régénérer + diff » |
| 5 | Changement de comportement (refresh→invalidate) | D | Migration par ressource, tests verts à chaque étape |
| 6 | Seuils de couverture Vitest (85/80/75/85) | D | Adapter les tests des composables migrés (QueryClient de test) |
| 7 | Timing TanStack (staleness/refetch) vs E2E | D | Défauts prudents ; valider E2E après chaque ressource |

## Impact tests / CI

- **Vitest** : la couverture porte sur `composables/**`,`stores/**`,`utils/**` (bloquante). Les
  composables migrés changent de forme → tests à adapter (montage avec un `QueryClient`). Les helpers
  de libellés + `useDashboardMetrics` ne bougent pas.
- **E2E** : transparent côté UX (invalidation = même résultat que `refresh`), mais rejeu complet à
  chaque lot C et D. Le `webServer` Playwright reste `build && node .output/server/index.mjs` (Nitro).
- **CI** : +1 job/étape « types à jour » (Lot A) et le test de parité i18n (Lot B) rejoignent le job front.

## Definition of Done

- `ssr:false`, plus aucun `server:false` résiduel ; E2E (11 specs) verts.
- 0 `useAsyncData`/`refresh()` manuel résiduel ; toutes les lectures/mutations via TanStack (cache + invalidation).
- Types API générés, plus de duplication ; contrôle CI de synchro en place.
- Test de parité i18n vert et bloquant.
- CI 100% verte, commits atomiques par lot, docs (OVERVIEW/ADR le cas échéant) à jour.

## Questions ouvertes (à trancher avec Benoit)

- **Découpage** : je livre **A → B → C → D** en commits/lots séparés (CI verte à chaque lot). D peut
  être scindé en plusieurs commits par ressource. OK ?
- **Écran de chargement SPA** : minimal (logo + spinner) suffisant, ou on soigne dès maintenant ?
