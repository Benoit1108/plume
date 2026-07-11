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
| **Sourcing**          | Supporting (M3) | Ingestion d'alertes/RSS → pistes candidates. |
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
- Isolation via **Doctrine `SQLFilter`** activé à chaque requête, `TenantId` extrait du token JWT.
- Le **domaine ignore la tenancy** : c'est une préoccupation d'infrastructure.
- V1 : une seule Traductrice. Extraction schéma-par-tenant possible plus tard sans refonte du domaine.

## Exposition API

- **API Platform** en couche Infrastructure.
- Les **resources sont des DTO**, jamais les entités Doctrine ni les agrégats du domaine.
- **State Providers** (lecture) et **State Processors** (écriture) délèguent au **bus CQRS**.
- Bénéfices gratuits : pagination, filtres, validation, négociation de contenu, **doc OpenAPI**.

## Frontend

- **Nuxt 3** (Vue 3, TypeScript), **Pinia**, SPA authentifiée.
- Auth **JWT access + refresh** ; refresh en cookie httpOnly, rotation des tokens.
- Vues clés : kanban pipeline, « à faire aujourd'hui », fiche Piste (timeline), éditeur de brouillon, tableau de bord.

## Sécurité (synthèse)

- Tokens OAuth chiffrés au repos (libsodium) ; secrets via Vault Symfony.
- Scopes OAuth minimaux (`gmail.send` + lecture d'un label dédié).
- Rate limiting (login, génération IA), CSP, validation stricte des entrées.

## Préoccupations transverses (jour-1)

Décidées dès le départ car coûteuses à rétrofitter (détail : [ADR-0011](decisions/0011-preoccupations-transverses.md)) :

- **UI** : Nuxt UI (design tokens + a11y), **thème clair/sombre**, **i18n FR+EN** (ICU).
- **Locale UI ≠ langue du contenu généré** : deux axes distincts.
- **API versionnée** `/api/v1` · erreurs **RFC 7807** (problem+json).
- **UTC** en base, affichage en TZ utilisatrice · formats via **Intl/ICU**.
- **Préférences** utilisateur (locale, thème, TZ, notifs) sur le Profil.
- **Logs structurés** avec `tenant_id` + correlation-id · champs d'**audit** (`createdAt`/`updatedAt`).

Voir les décisions détaillées dans [`decisions/`](decisions/).
