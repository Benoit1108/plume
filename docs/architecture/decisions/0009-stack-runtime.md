# 0009 — Stack technique & runtime local

- **Statut** : Accepté
- **Date** : 2026-07-11

## Contexte
Choix des technologies et de l'environnement d'exécution, aligné sur la maîtrise du dev (Symfony/Nuxt) et un coût minimal.

## Décision
- **Backend** : Symfony **7.4 LTS** (PHP 8.5), API Platform, Messenger (command + event bus), Scheduler.
- **Domaine/ORM** : Doctrine ORM + `symfony/uid` (UUID), mapping en Infrastructure.
- **BDD** : PostgreSQL 17 (JSONB pour payloads/email brut, full-text pour la recherche).
- **Frontend** : Nuxt 3 (Vue 3, TypeScript) + Pinia. *(Amendé 2026-07-13 : livré en **Nuxt 4** — même écosystème, décision inchangée.)*
- **IA** : API Claude derrière une couche anti-corruption.
- **Email** : Gmail API + Microsoft Graph (OAuth).
- **Runtime local** : **FrankenPHP + Postgres + worker Messenger + Scheduler** via **Docker Compose**.
- **Qualité** : PHPStan (max), PHP-CS-Fixer/ECS, PHPUnit ; Vitest/ESLint côté front ; CI GitHub Actions.

## Conséquences
- ✅ Stack maîtrisée, coût quasi nul en local.
- ✅ Domaine testable sans DB.
- 🔀 **Hébergement de production différé** : VPS + Docker Compose vs PaaS français, tranché au premier déploiement.
- ℹ️ **Symfony 7.4 LTS et non 8.0** : au moment du scaffolding, l'écosystème (notamment `doctrine/doctrine-bundle` et `gesdinet/jwt-refresh-token-bundle`) ne supporte pas encore Symfony 8. 7.4 est la dernière version LTS réellement installable, compatible PHP 8.5. Migration vers 8.x prévue quand Doctrine suivra (le domaine, sans dépendance framework, ne sera pas impacté).
