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

- **Sens des dépendances** : `Infrastructure → Application → Domain`. Jamais l'inverse.
- **`Domain/` est du PHP pur** : aucune dépendance à Symfony, Doctrine, API Platform. Pas d'annotation ORM sur les agrégats (mapping en Infrastructure).
- **Un contexte ne dépend d'un autre que par ID** (références cross-agrégat) ou **par port** (interface). Jamais d'accès direct à un agrégat d'un autre contexte.
- **Une commande = une transaction.** Les domain events sont dispatchés **après commit** (transactional outbox).
- **Les queries lisent des read models**, pas les agrégats.
- **API Platform expose des DTO**, jamais les entités Doctrine ni les agrégats. State Providers/Processors délèguent au bus CQRS.
- **Multi-tenancy = préoccupation d'infra** : `SQLFilter` sur `tenant_id`, le domaine n'en sait rien.
- **Secrets/tokens chiffrés au repos** ; jamais de credential en clair en base ou en log.

## Ajouter une fonctionnalité (flux type)

1. Modéliser dans `Domain/` (méthode d'agrégat + invariants + domain event).
2. Écrire un **Command** + son **Handler** dans `Application/`.
3. Implémenter le repository/adapter dans `Infrastructure/`.
4. Réagir à l'event : **projection** (read model) et/ou notification.
5. Exposer via **DTO + State Processor/Provider** (API Platform).
6. Tests : domaine (unitaire, sans DB) → application (repo in-memory) → intégration.
7. Front Nuxt : store Pinia + vue.

## Conventions de code

- **PHP 8.5**, typage strict (`declare(strict_types=1)`).
- **PHPStan niveau max** et **PHP-CS-Fixer** doivent passer avant commit.
- VOs immuables, validation dans le constructeur (échec = exception domaine).
- Nommage du **code en anglais** (classes, méthodes, events, propriétés) via la table de correspondance du glossaire ; **pas d'identifiants accentués**. Le vocabulaire **métier reste français** dans l'UI et la doc.
- Front : TypeScript strict, ESLint/Prettier, composants Vue en `<script setup>`.

## Tests

- Le domaine se teste **sans base de données** — c'est un objectif de conception, pas un accident.
- Pas de fonctionnalité métier sans test de domaine correspondant.
- `test-runner` / commandes de test : à compléter une fois M0 scaffoldé.

## Commandes (à compléter au jalon M0)

```bash
docker compose up -d          # stack locale
# composer test / phpstan / cs-fixer  → TBD après scaffolding
# npm run dev / test / lint (app/)     → TBD après scaffolding
```

## Git

- Le dépôt n'est pas encore initialisé. À l'init : brancher par fonctionnalité, messages de commit clairs.
- Ne jamais committer de secrets (`.env.local`, tokens OAuth, clés JWT).

## État actuel

Conception terminée, docs posées, **code non initialisé**. Prochaine étape : jalon **M0** (voir `docs/ROADMAP.md`).
