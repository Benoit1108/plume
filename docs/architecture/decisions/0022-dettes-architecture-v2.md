# ADR-0022 — Dettes d'architecture à trancher en V2 (revue fin M3)

- **Statut : Partiellement soldé** (mis à jour 2026-07-24 — issu de la [revue de santé fin M3](../../reviews/2026-07-20-revue-sante-fin-m3.md), volet « critique d'architecture »)

> **Mise à jour 2026-07-24 (phase pré-V2).** Plusieurs dettes listées ici ont été **soldées** avant
> le cadrage V2 — à ne PAS rouvrir comme prérequis V2 :
> - **§1 I/O synchrone** → soldé : relève manuelle des sources ET des réponses en async (worker,
>   `TransportNamesStamp`), commits `dd1dc2e` / lot A revue pré-V2.
> - **§2 cérémonie de mapping id-VO** → soldé : `AbstractStringIdType` + interface `StringId` (`163405a`).
> - **§6 état serveur front** → soldé : **TanStack Query** (chantier 3 lot D).
> - **§7 types dupliqués** → soldé : `openapi-typescript` + `gen:types` + contrôle CI de drift ;
>   enums dérivés du contrat après durcissement OpenAPI (`5a64025`).
> - **§8 SSR subi** → soldé : **SPA `ssr:false`** ([ADR-0024](0024-spa-ssr-false.md)).
>
> **Mise à jour 2026-07-24 (V2.0-b).** La **charge inter-contextes** (relève RSS/email lente
> retardant les projections/policies de TOUS les tenants — évoquée au cadrage V2 sous l'étiquette
> approximative « §5 ») est **soldée** : transport Messenger **`io` dédié** aux commandes à I/O
> réseau lourde (relève RSS/IMAP/Graph) + **worker `worker_io` séparé**, distinct de la file `async`
> (events/projections légers) et de son worker. Le découpage retenu est **par poids d'I/O** (et non
> « par contexte » comme esquissé) : c'est le vrai axe du problème — un event léger de Sourcing ne
> doit pas être pénalisé parce que Sourcing fait aussi de l'I/O RSS lourd.
>
> **Restent ouverts** (assumés V1, à rouvrir si besoin) : §3 (patrons d'adaptateurs — la source de
> démo est neutralisée en prod, mais l'hétérogénéité Selector/registres subsiste), §4 (tables hors
> ORM), §5 (`RawAlert`/`rawRef`). *(La note antérieure glosait §5 en « charge inter-contextes » : c'était
> une erreur d'étiquette — le §5 numéroté ci-dessous est bien `RawAlert`/`rawRef`.)*
- **Contexte** : la revue fin M3 a mené deux audits dédiés aux **choix d'architecture** (back et
  front). Plusieurs sont **assumables en V1 mono-utilisatrice** mais deviendront coûteux à
  l'**ouverture SaaS multi-utilisateurs (V2)**. Cet ADR **trace** ces décisions pour qu'elles
  soient prises consciemment le moment venu — il ne les tranche pas encore. Ce qui était
  **actionnable et peu coûteux a déjà été corrigé** (voir « Déjà corrigé » ci-dessous).

## Décision

**Reporter** les refontes ci-dessous à la V2, en les gardant explicites et datées. Les rouvrir
au cadrage V2 ; chacune deviendra son propre ADR « Accepté » quand elle sera tranchée.

### Back

1. **Modèle synchrone des commandes vs I/O réseau.** La relève **manuelle** (`POST /sources/poll`,
   `fetch-replies`) fait son I/O réseau dans la requête HTTP (bornée par le timeout du client).
   Le fan-out automatique est déjà **asynchrone par tenant** (corrigé, lot B). À la V2 : évaluer un
   `PollAlertSource` async aussi pour le geste manuel (vrai 202 + UI de suivi), et sortir l'I/O de
   la transaction de commande.
2. **Cérémonie de mapping.** ~18 types DBAL, dont ~11 id-VO quasi identiques ; schéma écrit deux
   fois (XML + SQL de migration à la main, `migrations:diff` bruité). À la V2 : `AbstractStringIdType`
   générique + un contrôle CI `schema:validate` / diff « zéro delta sur les tables mappées ».
3. **Patrons d'adaptateurs hétérogènes.** Selector (Drafting) vs 3 registres (Mailbox) vs binding
   direct (`AlertEmailFetcher`) vs branche démo en couche Application (`PollAlertSource`). À la V2 :
   harmoniser (un `ConfiguredAlertSource` interne ; registre-par-env pour l'AlertEmailFetcher).
4. **Tables hors ORM + `schema_filter` en denylist** (`interaction`, `raw_alert`, `messenger_messages`).
   Idiomatique pour des projections, mais denylist croissante + double écriture. À surveiller ;
   documenter le schéma de ces tables en un lieu unique.
5. **`RawAlert` / `rawRef`** : cérémonie d'agrégat pour un blob (incohérent avec `interaction`) ;
   `rawRef` = référence lâche sans FK. À la V2 : aligner sur `interaction` (store infra) **ou**
   mapper ORM + FK `ON DELETE SET NULL`.

### Front

6. **Absence de couche « server-state ».** État réparti (Pinia auth + `useState` + `useAsyncData`
   rafraîchi à la main ; badge mis à jour en effet de bord de `queue()`). À la V2 : adopter une vraie
   couche (Pinia Colada / TanStack Query — cache + invalidation par clé) + endpoint `count` dédié.
7. **Types back/front dupliqués à la main** malgré `openapi.json` (diff bloquant en CI). À la V2 :
   génération `openapi-typescript` + test de non-dérive ; ne garder à la main que les view-models.
8. **SSR activé mais `server: false` partout.** On paie le coût SSR (proxy, gardes d'hydratation)
   pour un HTML serveur sans données. À la V2 : **trancher** — assumer le SPA (`ssr: false`) **ou**
   faire du vrai SSR (cookie transmis au rendu). Décision structurante, à prendre explicitement.

## Déjà corrigé (revue fin M3, lots A/B/E — ne pas reporter)

- **Lectures cross-contexte des gateways** : plus de SQL brut sur les tables d'autres contextes ;
  `organizationExists` / `activeLeadId` passent par les **queries** `OrganizationExists` (Directory)
  et `FindActiveLead` (Prospection) — chaque contexte possède son SQL. *(lot E)*
- **Angle mort deptrac Infra** : un **test de contrat** (`PublishedEventContractTest`) fige la forme
  des events « langage publié » cross-consommés (`ReplyCaptured`, `EmailSent`, `EmailSendFailed`,
  `DraftGenerated`, `AlertEmailReceived`) — un renommage casse le test. *(lot E)*
- Isolation de panne du fan-out (async par tenant), plafonds/rate-limit, SSRF/XSS/rétention RGPD,
  P0 débordement de titre. *(lots A/B)*

## Conséquences

- ✅ La dette d'architecture est **explicite, datée et priorisée** — plus de « surprise » à la V2.
- ✅ Le couplage inter-contextes réel (lectures par ports de query + contrat d'events) est **réduit
  et outillé** dès la V1.
- ⚠️ Les points 1–8 restent des choix V1 **assumés** ; ne pas les traiter au coup par coup sans
  rouvrir cet ADR (cohérence d'ensemble).
