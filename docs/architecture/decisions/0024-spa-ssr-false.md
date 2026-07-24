# ADR-0024 — Front en SPA (`ssr: false`), serveur Nitro conservé pour le proxy

- **Statut : Accepté** (2026-07-23 — chantier 3 pré-V2, cf. [note de cadrage](../../design/chantier-3-front-cadrage.md))
- **Contexte** : le front Nuxt tournait en SSR par défaut, mais le SSR n'était **pas exploité** —
  chaque `useAsyncData` était forcé en `server: false` (fetch client-only), et Plume est un
  **outil privé authentifié** (aucun besoin SEO ni de premier rendu serveur). L'ADR-0022 §8
  (« SSR subi ») demandait de **trancher explicitement** cette posture. Décision prise au cadrage
  PRE-V2 (D3, arbitrée par Benoit) et exécutée au chantier 3.

## Décision

Passer le front en **SPA (`ssr: false`)** : l'application se monte côté client, plus de rendu
serveur ni d'hydratation. On **CONSERVE le serveur Nitro** (`nuxt build` + `node .output/server/index.mjs`)
pour :
- le **proxy `/api`** same-origin (`nitro.devProxy` en dev, `routeRules['/api/**'].proxy` en prod) —
  indispensable aux **cookies httpOnly** de l'auth (ADR-0018) ;
- servir le shell SPA + les assets.

**On ne passe PAS en full-statique (`nuxt generate`)** : sans Nitro, plus de proxy → cookies
same-origin cassés (CORS). C'est un invariant.

Un écran de chargement SPA (`app/spa-loading-template.html`, autonome, deux thèmes,
`prefers-reduced-motion`) couvre le temps de montage.

## Conséquences

- **+** Modèle mental simplifié : plus de `server: false` sur chaque fetch (supprimés au chantier 3
  lot D avec la migration TanStack Query) ; plus de risque de mismatch d'hydratation.
- **+** Intégration TanStack Query simplifiée : cache purement client, pas de déshydratation SSR.
- **+** Auth cookies + refresh-on-401 inchangés (le proxy Nitro subsiste).
- **−** Premier rendu = shell vide jusqu'au chargement du bundle JS (couvert par l'écran de
  chargement) ; acceptable pour un outil interne. Pas de SEO (non requis).
- **−** L'invariant « pas de full-statique » doit rester connu (sinon régression cookies/proxy).
- Garde de route : le middleware global lit le cookie témoin `plume_email` côté client — la
  redirection `/login` fonctionne dès le montage.

Remplace la posture implicite « SSR + `server:false` partout ». N'affecte pas l'ADR-0009
(Nuxt 3→4) ni l'ADR-0018 (cookies), qui restent valides.
