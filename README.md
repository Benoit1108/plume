# Plume

> Assistant de **démarchage et de suivi commercial** pour traductrice indépendante.
> Transforme un temps rare et fragmenté en une prospection **régulière, personnalisée et traçable** — cœur : démarchage direct (édition, audiovisuel) + relance automatique + génération assistée par IA.

**Nom de code provisoire.** Renommable trivialement (aucune logique ne dépend du nom).

## En une phrase

Un mini-CRM SaaS (multi-tenant dès l'architecture, mono-utilisatrice en V1) qui aide une traductrice à :
1. **cibler** des maisons d'édition / labos audiovisuels / agences,
2. **générer** mails de candidature et lettres de motivation personnalisés (FR / EN / ES),
3. **suivre** chaque piste dans un pipeline,
4. **relancer** automatiquement, sans rien laisser filer,
5. plus tard **ingérer** des annonces (ProZ, LinkedIn, RSS) et **gérer les missions** décrochées.

## État du projet

Phase de **conception terminée**, documentation posée. Le code n'est pas encore initialisé.
Prochaine étape possible : scaffolding du squelette monorepo (voir `docs/ROADMAP.md`, jalon **M0**).

## Stack

| Brique     | Choix                                                         |
|------------|---------------------------------------------------------------|
| Backend    | Symfony 7.4 LTS (PHP 8.5), API Platform, Messenger, Scheduler  |
| Domaine    | DDD, architecture hexagonale, monolithe modulaire par contexte|
| ORM / BDD  | Doctrine ORM + `symfony/uid` / PostgreSQL 17                  |
| Frontend   | Nuxt (Vue 3, TS) + Nuxt UI + Pinia + i18n (FR/EN)             |
| Auth       | JWT access + refresh (Lexik)                                  |
| IA         | API Claude (derrière une couche anti-corruption)             |
| Email      | Gmail API + Microsoft Graph (OAuth), tokens chiffrés          |
| Runtime    | FrankenPHP + worker Messenger + Scheduler, via Docker Compose |

## Structure du dépôt (monorepo, cible)

```
plume/
├─ api/              # application Symfony (backend)
│  └─ src/
│     ├─ Prospection/        # Core domain
│     ├─ Repertoire/
│     ├─ RedactionAssistee/
│     ├─ PasserelleEmail/
│     ├─ Sourcing/
│     ├─ Compte/             # tenancy, auth, profil
│     └─ Shared/             # Kernel: VOs communs, bus, TenantId, SQLFilter
├─ app/              # application Nuxt 3 (frontend)
└─ docs/             # documentation (voir ci-dessous)
```

## Documentation

- [`docs/GLOSSAIRE.md`](docs/GLOSSAIRE.md) — langage ubiquitaire (à connaître avant de coder).
- [`docs/architecture/OVERVIEW.md`](docs/architecture/OVERVIEW.md) — carte des contextes, couches, CQRS, tenancy.
- [`docs/architecture/DOMAIN-MODEL.md`](docs/architecture/DOMAIN-MODEL.md) — agrégats, value objects, events, machine à états.
- [`docs/ROADMAP.md`](docs/ROADMAP.md) — jalons M0→M3, puis V2 et futur.
- [`docs/architecture/decisions/`](docs/architecture/decisions/) — ADRs (décisions d'architecture tracées).
- [`CLAUDE.md`](CLAUDE.md) — conventions et règles pour travailler dans ce dépôt.

## Développement local (cible)

> Ces commandes seront valides une fois le jalon **M0** scaffoldé.

```bash
docker compose up -d        # FrankenPHP + Postgres + worker + scheduler
# API   : https://localhost
# App   : http://localhost:3000
```
