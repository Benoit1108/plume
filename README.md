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
- **M1 — cœur prospection : livré au complet** —
  **M1.1** Répertoire (API + import CSV + écrans), **M1.2** pipeline Lead (machine à
  états, kanban, journal d'interactions projeté, garde RGPD), **M1.3** relances &
  écran « Aujourd'hui » (cadence auto, objectif hebdo + série), **M1.4** rédaction
  assistée (contexte Drafting, brouillons draft-first, gabarits, Claude par env /
  générateur local par défaut), **M1.5** tableau de bord (taux de réponse,
  conversion, activité hebdo, segments).
- **M2 — passerelle email : livré au complet** — connexion OAuth **Gmail + Outlook**
  (tokens chiffrés au repos), **envoi** d'un brouillon relu depuis la vraie adresse,
  **captation des réponses** par threading (la piste passe en discussion, relance annulée),
  relances envoyées dans le fil ; auth par cookies **httpOnly**.
- **M3 — ingestion d'annonces : livré au complet** — contexte Sourcing (file de tri
  accepter/fusionner/rejeter), flux RSS/Atom par tenant, relève des alertes email (label dédié),
  parser LinkedIn fin, promotion cross-contexte par gateway.
- **Phase pré-V2 : livrée** — RLS multi-tenant fail-closed (rôle runtime dédié, ADR-0023), mail
  réel (Gmail), front **SPA `ssr:false`** + **TanStack Query** + types générés OpenAPI, poll async,
  `AbstractStringIdType`.
- **Revues de santé** appliquées à chaque jalon (`docs/reviews/`), remédiations fin M1/M2/M3 et fin pré-V2 incluses.
- **Prochaine étape : cadrage V2** (voir [`docs/ROADMAP.md`](docs/ROADMAP.md)).

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
│  │  ├─ Prospecting/        # Core domain (pipeline Lead, relances, journal, dashboard)
│  │  ├─ Directory/          # Répertoire (organisations, contacts, import)
│  │  ├─ Drafting/           # Rédaction assistée (brouillons, gabarits, ACL IA)
│  │  ├─ Account/            # tenancy, auth, profil
│  │  ├─ Mailbox/            # Passerelle email (OAuth Gmail/Outlook, envoi, réponses)
│  │  ├─ Sourcing/           # ingestion d'annonces (RSS/Atom + alertes email, file de tri)
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
# 1. Stack de dev (Postgres + API FrankenPHP + worker Messenger — le journal en dépend)
make up

# 2. Première installation uniquement :
make install            # dépendances composer
make jwt-keys           # paire de clés JWT locale
make provision-app-role # rôle applicatif runtime plume_app (soumis à la RLS) — cf. plus bas
make migrate            # schéma de base (rôle propriétaire plume)
docker compose exec php php bin/console app:user:create vous@exemple.fr
#    → crée l'utilisateur (le mot de passe est demandé, saisie masquée)

# 3. Frontend (sur l'hôte)
cd app && npm install && npm run dev

# API   : https://localhost:8443 (certificat auto-signé — le front passe par un proxy)
# App   : http://localhost:3000
```

> **Deux rôles Postgres (multi-tenant fail-closed).** Le rôle **propriétaire `plume`**
> (`DATABASE_URL`) exécute migrations, tests et commandes console — il **contourne** la RLS.
> L'API HTTP et le worker se connectent avec le rôle **runtime `plume_app`** (non-propriétaire,
> `make provision-app-role`), **soumis** à la _Row-Level Security_ : filet de sécurité en base
> par-dessus le filtre applicatif. Créé une fois par cluster, idempotent.

Qualité (identiques à la CI) : `make test`, `make phpstan`, `make deptrac` (couches **et**
frontières de contextes), `make cs`, `make openapi` (contrat diff-vérifié — à régénérer
**après** un `cache:clear` si des propriétés d'API ont changé), et côté front
`npm run lint / type-check / test:coverage`. `make hooks` installe le hook git pre-commit.

### Tests E2E (Playwright)

La suite `app/e2e/` pilote un **build de production** du front contre l'API réelle :

```bash
# Prérequis : stack up (Postgres + php + worker) + utilisateur e2e dédié (tenant isolé)
docker compose exec php php bin/console app:user:create e2e@plume.test   # mot de passe : e2e-secret-123
docker compose stop app        # le conteneur front dev et le build E2E partagent .nuxt
cd app && npx playwright test  # lance build + serveur + tests (workers sérialisés : tenant partagé)
```

Chaque test porte un garde-fou console/hydratation (toute erreur runtime fait échouer).
En CI, le job `E2E (Playwright)` rejoue exactement ce parcours.

### Activer la génération IA (Claude)

Sans configuration, la rédaction assistée utilise un **générateur local déterministe**
(gratuit — dev, tests, CI). Pour brancher l'API Anthropic :

```bash
# api/.env.local (jamais commité)
ANTHROPIC_API_KEY=sk-ant-…       # la clé reste un secret d'environnement
DRAFTING_MODEL=claude-sonnet-5   # surchargeable
```

Les demandes de génération sont plafonnées (30/h par tenant) et l'ADR-0014 documente
les données transmises au sous-traitant. Voir [`docs/architecture/decisions/`](docs/architecture/decisions/).

### Activer la passerelle email (Gmail / Outlook)

Sans identifiants OAuth, la passerelle utilise des **connecteurs factices** : le parcours
complet (connexion, envoi, relève des réponses) fonctionne en local, dev, CI et E2E **sans
aucun compte réel**. Pour brancher les vrais fournisseurs :

```bash
# api/.env.local (jamais commité)
GOOGLE_CLIENT_ID=…                # OAuth Google (Gmail send + readonly)
GOOGLE_CLIENT_SECRET=…
GOOGLE_REDIRECT_URI=https://votre-hote/oauth/gmail/callback
MICROSOFT_CLIENT_ID=…             # OAuth Microsoft (Graph Mail.Send + Mail.Read)
MICROSOFT_CLIENT_SECRET=…
MICROSOFT_REDIRECT_URI=https://votre-hote/oauth/outlook/callback
# Clé DÉDIÉE de chiffrement des tokens au repos (obligatoire en prod — ADR-0016) :
MAILBOX_ENCRYPTION_KEY=$(php -r "echo base64_encode(random_bytes(32));")
```

Le fournisseur (Gmail ou Outlook) est choisi à la connexion, dans les Réglages. Un seul
compte connecté par utilisatrice en V1. La relève des réponses tourne toutes les 5 minutes
(Scheduler) ; aucun tracking d'ouverture. Voir ADR-0007/0016/0017.

## Licence

Code source visible, **tous droits réservés** — voir [`LICENSE`](LICENSE).
