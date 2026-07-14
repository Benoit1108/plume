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

- **Sens des dépendances** : `Infrastructure → Application → Domain`. Jamais l'inverse (deptrac le vérifie en CI).
- **`Domain/` est du PHP pur** : aucune dépendance à Symfony, Doctrine, API Platform. Pas d'annotation ORM sur les agrégats (mapping XML dans `api/config/doctrine/`).
- **Un contexte ne dépend d'un autre que par ID** (références cross-agrégat) ou **par port** (interface). Jamais d'accès direct à un agrégat d'un autre contexte.
- **Une commande = une transaction.** Les domain events sont dispatchés via l'outbox transactionnel (transport doctrine, même transaction que la commande).
- **Toute mutation d'agrégat émet un domain event** (projections, journal, traçabilité RGPD).
- **Les queries lisent des read models** (vues immuables via un port `…Search`, SQL direct fail-closed sur le tenant — cf. ADR-0013), jamais les agrégats.
- **API Platform expose des DTO**, jamais les entités Doctrine ni les agrégats. State Providers/Processors délèguent au bus CQRS.
- **Les erreurs métier héritent de `Shared\Domain\Exception\DomainError`** (`InvalidValue` → 422, `NotFound` → 404, `Conflict` → 409 — mapping `exception_to_status`). Jamais d'exception SPL nue dans le domaine.
- **Multi-tenancy = préoccupation d'infra** : `SQLFilter` sur `tenant_id` (ORM) + prédicat explicite (read models DBAL), toujours **fail-closed**. Le domaine n'en sait rien.
- **Pas d'horloge ni d'UUID en dur** dans Application : ports `Clock` et `IdGenerator`.
- **Secrets/tokens chiffrés au repos** ; jamais de credential en clair en base ou en log.

## Ajouter une fonctionnalité (flux type)

1. Modéliser dans `Domain/` (méthode d'agrégat + invariants + domain event).
2. Écrire un **Command** + son **Handler** dans `Application/` (le handler publie les events).
3. Implémenter le repository/adapter dans `Infrastructure/`.
4. Lecture : vue + port dans `Application/ReadModel/`, implémentation SQL en Infrastructure.
5. Exposer via **DTO + State Processor/Provider** (API Platform) avec contraintes Assert complètes **et bornées** (longueurs max — leçon M1.1/M1.4) ; régénérer `openapi.json` (`make openapi` — diff bloquant en CI). ⚠️ Après tout changement de propriétés d'une resource : `cache:clear` **avant** `make openapi` (métadonnées API Platform en cache = contrat obsolète).
6. Tests : domaine (pur) → application (repo in-memory, `tests/Support/`) → fonctionnel (`ApiTestCase` + Postgres).
7. Front Nuxt : composable/store + vue — **tout texte passe par i18n** (fr + en), toasts sur les mutations, confirmation avant action destructive.

## Conventions de code

- **PHP 8.5**, typage strict (`declare(strict_types=1)`).
- **PHPStan niveau max** et **PHP-CS-Fixer** doivent passer avant commit (`make hooks` installe le hook pre-commit).
- VOs immuables, validation dans le constructeur (échec = `InvalidValue`).
- Nommage du **code en anglais** (classes, méthodes, events, propriétés) via la table de correspondance du glossaire ; **pas d'identifiants accentués**. Le vocabulaire **métier reste français** dans l'UI et la doc.
- Front : TypeScript strict, ESLint, composants Vue en `<script setup>`, libellés métier centralisés (`useDirectoryLabels` + locales i18n).

## Tests

- Le domaine se teste **sans base de données** — c'est un objectif de conception, pas un accident.
- Pas de fonctionnalité métier sans test de domaine correspondant ; pas de handler sans test d'application ; pas d'endpoint sans test fonctionnel (l'**isolation tenant** est couverte par `tests/Functional/`).
- Front : seuils de coverage **bloquants** dans `vitest.config.ts` — ne pas les baisser pour faire passer un build.
- **E2E Playwright** (`app/e2e/`, helpers partagés dans `e2e/helpers.ts`) : build de prod contre l'API réelle, user dédié `e2e@plume.test` (tenant isolé), **workers sérialisés** (tenant partagé entre fichiers), garde console/hydratation systématique. Lancement local : voir README § Tests E2E (stopper le conteneur `app` d'abord).

## Commandes

```bash
make up             # stack dev (Postgres + API https://localhost:8443 + worker — le journal en dépend)
make migrate        # migrations Doctrine
make jwt-keys       # génère les clés JWT locales (une fois)
make test           # PHPUnit complet (crée/migre la base _test)
make phpstan        # analyse statique niveau max
make deptrac        # frontières DDD (couches + contextes, 2 configs)
make cs-fix         # PHP-CS-Fixer
make openapi        # régénère api/openapi.json (obligatoire après tout changement d'API)
make hooks          # installe le hook git pre-commit

cd app && npm run dev          # front (http://localhost:3000, proxy /api vers l'API)
cd app && npm run test:coverage / lint / type-check
```

Créer un utilisateur local : `docker compose exec php php bin/console app:user:create <email>` (mot de passe demandé).

## Git

- **Trunk-based assumé** : commits atomiques sur `main`, CI verte obligatoire, jamais de force-push (protection de branche active). Une branche courte reste bienvenue pour une exploration risquée.
- Messages conventionnels (`feat(scope): …`, `fix: …`, `docs: …`), descriptions en français.
- Ne jamais committer de secrets (`.env.local`, tokens OAuth, clés JWT — gitignorés).

## État actuel

**M1 complet et revue de santé fin M1 appliquée** (M0 fondations, M1.1 Répertoire,
M1.2 pipeline Piste, M1.3 relances & « Aujourd'hui », M1.4 rédaction assistée — contexte
`Drafting`, M1.5 tableau de bord ; remédiation en 3 lots, `docs/reviews/`).
Prochaine étape : **M2 — passerelle email** (les dettes actées pour M2 sont listées en ROADMAP).
