# Vue d'architecture

## Principe directeur

Le **cœur métier est le pipeline de prospection + les relances**. Tout le reste (génération IA, passerelle email, ingestion, plus tard missions et facturation) gravite autour et doit pouvoir être ajouté/retiré **sans toucher au cœur**.

Conséquence structurante : **monolithe modulaire hexagonal**, découpé par bounded context, dépendances pointant vers l'intérieur (le domaine ne dépend de rien).

## Carte des contextes

| Contexte              | Type            | Rôle |
|-----------------------|-----------------|------|
| **Prospection**       | Core            | Pistes, pipeline, interactions, relances, objectifs de régularité. |
| **Répertoire**        | Supporting      | Organisations & Contacts, annuaire. Fournit les cibles. |
| **Rédaction assistée**| Supporting      | Génération IA + Modèles. ACL → API Claude. |
| **Sourcing**          | Supporting      | Annonces candidates → file de tri → promotion en Pistes. **M3 complet** : socle + tri (M3.0), ingestion RSS (M3.1), alertes email (M3.2, plomberie). |
| **Passerelle email**  | Generic (tech.) | Envoi + lecture. ACL → Gmail/Outlook. Sert Prospection ET Sourcing. |
| **Compte & Tenancy**  | Generic         | Tenants, auth, profil. |
| **Gestion de mission**| Core n°2 (futur)| Missions décrochées. |
| **Facturation**       | Generic (futur) | Devis/factures. |

### Relations clés
- Prospection **référence** Répertoire par ID (cross-agrégat), jamais par objet.
- Prospection **dépend de** Rédaction assistée et Passerelle email **via des ports** (interfaces), jamais des implémentations.
- Passerelle email **notifie** Prospection (réponse reçue) et Sourcing (alerte reçue) via événements applicatifs.

## Couches (par contexte)

```
<Contexte>/
├─ Domain/          PHP pur. Agrégats, entités, VOs, domain events,
│                   interfaces de repository, interfaces de ports.
│                   ⛔ Aucune dépendance à Symfony/Doctrine/API Platform.
├─ Application/     Command/Query handlers, DTOs, orchestration,
│                   définition des ports applicatifs.
└─ Infrastructure/  Repositories Doctrine, projections/read models,
                    adapters (Claude, Gmail, Outlook), resources & State
                    Providers/Processors API Platform, contrôleurs.
```

**Règle de dépendance** : `Infrastructure → Application → Domain`. Jamais l'inverse.

## CQRS léger (Messenger)

- **Command bus** — synchrone, **transactionnel** (une commande = une transaction). Ex. `ContacterPiste`, `PlanifierRelance`, `GenererBrouillon`.
- **Event bus** — **asynchrone**, après commit. Les domain events alimentent :
  - le **journal d'Interactions** (projection),
  - les **KPIs** du tableau de bord,
  - la **progression d'objectif** et la **série**,
  - les **notifications**.
- **Queries** — lecture directe sur les **read models**, sans passer par les agrégats.
- **Scheduler** — déclenche les relances dues et les recalculs périodiques.
- **Appels Claude** — en async (Messenger) pour ne pas bloquer le HTTP.

### Fiabilité : transactional outbox
Les domain events sont persistés dans **la même transaction** que l'agrégat, puis relayés au bus async (transport Doctrine + dispatch après commit). Aucune perte d'event entre commit et publication.

## Multi-tenancy

- Base **unique**, schéma **partagé**, discriminant **`tenant_id`** sur les tables tenant.
- **Deux lignes de défense** (cf. ADR-0023) :
  1. **Applicative** — `TenantScope` (point unique) synchronise `TenantContext`, le **`SQLFilter`**
     Doctrine et la variable de session Postgres `app.current_tenant`. Les **read models DBAL**
     (hors ORM) portent un prédicat tenant **explicite et fail-closed** (`TenantContext::require()`,
     ADR-0013).
  2. **Base — Row-Level Security** : le runtime se connecte via un rôle **non-propriétaire**
     (`plume_app`) soumis à des policies `tenant_id = current_setting('app.current_tenant')`
     (fail-closed). Filet indépendant si le filtre applicatif est contourné. Le propriétaire
     `plume` (migrations/tests/scheduler-maintenance) la contourne.
- Le tenant est activé à l'auth JWT (HTTP), réactivé **par message** côté worker (il voyage dans
  l'event/la commande), et remis à zéro en fin de requête/message (connexion FrankenPHP réutilisée).
- Le **domaine ignore la tenancy** : c'est une préoccupation d'infrastructure.
- V1 : une seule Traductrice. Extraction schéma-par-tenant possible plus tard sans refonte du domaine.

## Exposition API

- **API Platform** en couche Infrastructure.
- Les **resources sont des DTO**, jamais les entités Doctrine ni les agrégats du domaine.
- **State Providers** (lecture) et **State Processors** (écriture) délèguent au **bus CQRS**.
- Bénéfices gratuits : pagination, filtres, validation, négociation de contenu, **doc OpenAPI**.

## Frontend

- **Nuxt 4** (Vue 3, TypeScript), **Pinia**, **SPA `ssr:false`** authentifiée (ADR-0024, serveur
  Nitro conservé pour le proxy `/api` + cookies) ; état serveur via **TanStack Query** ; types
  dérivés du contrat OpenAPI.
- Auth **JWT access + refresh**, les DEUX en cookies **httpOnly** posés/rotés/effacés par l'API (M2.0) — le JS ne voit jamais un token ; Bearer accepté en parallèle (outillage/tests) ; rotation single_use, révocation au logout.
- Vues clés : kanban pipeline, « à faire aujourd'hui », fiche Piste (timeline), éditeur de brouillon, tableau de bord.

## Sécurité (synthèse)

- Tokens OAuth chiffrés au repos (libsodium) ; secrets via Vault Symfony.
- Scopes OAuth minimaux (`gmail.send` + lecture d'un label dédié).
- Rate limiting effectif : login (throttling), endpoints token (par IP), **génération IA (30/h par tenant)** ; CSP ; validation stricte et bornée des entrées.

## Préoccupations transverses (jour-1)

Décidées dès le départ car coûteuses à rétrofitter (détail : [ADR-0011](decisions/0011-preoccupations-transverses.md)) :

- **UI** : Nuxt UI (design tokens + a11y), **thème clair/sombre**, **i18n FR+EN** (ICU).
- **Locale UI ≠ langue du contenu généré** : deux axes distincts.
- **API versionnée** `/api/v1` · erreurs **RFC 7807** (problem+json).
- **UTC** en base, affichage en TZ utilisatrice · formats via **Intl/ICU**.
- **Préférences** utilisateur (locale, thème, TZ, notifs) sur le Profil.
- **Logs structurés** avec `tenant_id` + correlation-id · champs d'**audit** (`createdAt`/`updatedAt`).

Voir les décisions détaillées dans [`decisions/`](decisions/).
