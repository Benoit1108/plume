.DEFAULT_GOAL := help
DC := docker compose
UID := $(shell id -u)
GID := $(shell id -g)
# Commandes ponctuelles en tant qu'utilisateur hôte (évite les fichiers root-owned)
# avec un HOME/COMPOSER_HOME inscriptibles.
PHP := $(DC) run --rm --no-deps --user $(UID):$(GID) -e HOME=/tmp -e COMPOSER_HOME=/tmp/composer php
# URL du rôle PROPRIÉTAIRE `plume` (contourne la RLS) : migrations, provisioning, tests, console DDL.
# Le service `php` pointe sinon sur le rôle runtime `plume_app` (soumis à la RLS) — cf. compose.yaml.
DB_OWNER_URL := postgresql://plume:plume@database:5432/plume?serverVersion=17&charset=utf8
# Variante avec dépendances (DB up) pour les migrations, en tant que PROPRIÉTAIRE.
PHP_DB := $(DC) run --rm --user $(UID):$(GID) -e HOME=/tmp -e COMPOSER_HOME=/tmp/composer -e 'DATABASE_URL=$(DB_OWNER_URL)' php

.PHONY: help up up-full down build install lock jwt-keys provision-app-role migrate seed test phpstan deptrac cs cs-fix audit schema-validate openapi front-install front-lint front-typecheck front-test

help: ## Affiche cette aide
	@grep -E '^[a-zA-Z_-]+:.*?## .*$$' $(MAKEFILE_LIST) | awk 'BEGIN {FS=":.*?## "}; {printf "  \033[36m%-14s\033[0m %s\n", $$1, $$2}'

up: ## Démarre la stack de dev (DB + API) — front à lancer sur l'hôte (npm run dev)
	$(DC) up -d

up-full: ## Démarre TOUT (DB, API, worker, scheduler, front en conteneur)
	$(DC) --profile full up -d

down: ## Arrête la stack
	$(DC) down

build: ## (Re)build les images
	$(DC) build

install: ## composer install (utilisateur hôte)
	$(PHP) composer install

lock: ## Met à jour composer.lock
	$(PHP) composer update --lock

jwt-keys: ## Génère la paire de clés JWT (Lexik)
	$(PHP) php bin/console lexik:jwt:generate-keypair --skip-if-exists

provision-app-role: ## Crée/actualise le rôle applicatif runtime plume_app (RLS) + ses privilèges
	$(PHP_DB) php bin/console app:db:provision-app-role

migrate: ## Applique les migrations Doctrine (DB requise)
	$(PHP_DB) php bin/console doctrine:migrations:migrate --no-interaction

seed: ## Jeu de données de RECETTE (dev only : recette@plume.fr / recette-2026)
	$(PHP_DB) php bin/console app:dev:seed

hooks: ## Installe le hook git pre-commit (cs-fixer + eslint)
	git config core.hooksPath .githooks
	chmod +x .githooks/pre-commit
	@echo "Hook pre-commit actif (.githooks/)."

test: ## Lance PHPUnit (unitaires + fonctionnels — crée/migre la base de test, rôle propriétaire)
	$(DC) run --rm --user $(UID):$(GID) -e HOME=/tmp -e COMPOSER_HOME=/tmp/composer -e APP_ENV=test -e 'DATABASE_URL=$(DB_OWNER_URL)' php \
		sh -c "php bin/console doctrine:database:create --if-not-exists \
		&& php bin/console doctrine:migrations:migrate --no-interaction \
		&& php bin/console app:db:provision-app-role \
		&& vendor/bin/phpunit"

phpstan: ## Analyse statique (niveau max)
	$(PHP) vendor/bin/phpstan analyse --memory-limit=1G

deptrac: ## Vérifie les frontières DDD (couches + contextes)
	$(PHP) vendor/bin/deptrac analyse --no-progress
	$(PHP) vendor/bin/deptrac analyse --no-progress --config-file=deptrac-contexts.yaml

cs: ## Vérifie le style (dry-run)
	$(PHP) vendor/bin/php-cs-fixer fix --dry-run --diff

cs-fix: ## Corrige le style
	$(PHP) vendor/bin/php-cs-fixer fix

audit: ## Audit de sécurité des dépendances PHP
	$(PHP) composer audit

schema-validate: ## Valide le mapping Doctrine (DB requise)
	$(PHP_DB) php bin/console doctrine:schema:validate --skip-sync

openapi: ## Régénère le contrat OpenAPI (openapi.json)
	$(PHP) php bin/console api:openapi:export --output=openapi.json

front-install: ## npm install (front)
	$(DC) run --rm app npm install

front-lint: ## Lint du front
	$(DC) run --rm app npm run lint

front-typecheck: ## Type-check du front
	$(DC) run --rm app npm run type-check

front-test: ## Tests front + coverage
	$(DC) run --rm app npm run test:coverage
