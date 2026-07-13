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

- **M0 — fondations** : livré (monorepo, Docker, CI durcie, auth JWT + refresh, tenancy).
- **M1.1 — Répertoire** : livré (API Organisations + Contacts, import CSV, écrans Nuxt responsives, i18n FR/EN) + **revue de santé appliquée** (`docs/reviews/`).
- **Prochaine étape : M1.2 — pipeline Lead** (voir [`docs/ROADMAP.md`](docs/ROADMAP.md)).

## Stack

| Brique     | Choix                                                         |
|------------|---------------------------------------------------------------|
| Backend    | Symfony 7.4 LTS (PHP 8.5), API Platform, Messenger, Scheduler  |
| Domaine    | DDD, architecture hexagonale, monolithe modulaire par contexte|
| ORM / BDD  | Doctrine ORM + `symfony/uid` / PostgreSQL 17                  |
| Frontend   | Nuxt 4 (Vue 3, TS) + Nuxt UI + Pinia + i18n (FR/EN)           |
| Auth       | JWT access + refresh avec rotation (Lexik + Gesdinet)         |
| IA         | API Claude (derrière une couche anti-corruption)             |
| Email      | Gmail API + Microsoft Graph (OAuth), tokens chiffrés          |
| Runtime    | FrankenPHP + worker Messenger + Scheduler, via Docker Compose |

## Structure du dépôt (monorepo)

```
plume/
├─ api/              # application Symfony (backend)
│  ├─ src/
│  │  ├─ Prospecting/        # Core domain (pipeline Lead)
│  │  ├─ Directory/          # Répertoire (organisations, contacts, import)
│  │  ├─ Account/            # tenancy, auth, profil
│  │  └─ Shared/             # VOs communs, bus CQRS, exceptions domaine, tenancy
│  ├─ config/doctrine/       # mapping XML (le domaine reste pur)
│  └─ tests/                 # domaine (pur) + application (in-memory) + fonctionnels (Postgres)
├─ app/              # application Nuxt 4 (frontend)
└─ docs/             # documentation (voir ci-dessous)
```

## Documentation

- [`docs/GLOSSAIRE.md`](docs/GLOSSAIRE.md) — langage ubiquitaire (à connaître avant de coder).
- [`docs/architecture/OVERVIEW.md`](docs/architecture/OVERVIEW.md) — carte des contextes, couches, CQRS, tenancy.
- [`docs/architecture/DOMAIN-MODEL.md`](docs/architecture/DOMAIN-MODEL.md) — agrégats, value objects, events, machine à états.
- [`docs/ROADMAP.md`](docs/ROADMAP.md) — jalons M0→M3, puis V2 et futur.
- [`docs/architecture/decisions/`](docs/architecture/decisions/) — ADRs (décisions d'architecture tracées).
- [`docs/reviews/`](docs/reviews/) — revues de santé périodiques.
- [`CLAUDE.md`](CLAUDE.md) — conventions et règles pour travailler dans ce dépôt.

## Développement local

```bash
# 1. Stack minimale (Postgres + API FrankenPHP)
make up

# 2. Première installation uniquement :
make install          # dépendances composer
make jwt-keys         # paire de clés JWT locale
make migrate          # schéma de base
docker compose exec php php bin/console app:user:create vous@exemple.fr
#    → crée l'utilisateur (le mot de passe est demandé, saisie masquée)

# 3. Frontend (sur l'hôte)
cd app && npm install && npm run dev

# API   : https://localhost:8443 (certificat auto-signé — le front passe par un proxy)
# App   : http://localhost:3000
```

Qualité (identiques à la CI) : `make test`, `make phpstan`, `make deptrac`, `make cs`,
`make openapi` (contrat diff-vérifié), et côté front `npm run lint / type-check / test:coverage`.
`make hooks` installe le hook git pre-commit.

## Licence

Code source visible, **tous droits réservés** — voir [`LICENSE`](LICENSE).
