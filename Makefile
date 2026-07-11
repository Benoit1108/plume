.DEFAULT_GOAL := help
DC := docker compose
PHP := $(DC) exec php

.PHONY: help up down build install migrate jwt-keys test phpstan cs cs-fix front-install front-lint

help: ## Affiche cette aide
	@grep -E '^[a-zA-Z_-]+:.*?## .*$$' $(MAKEFILE_LIST) | awk 'BEGIN {FS=":.*?## "}; {printf "  \033[36m%-14s\033[0m %s\n", $$1, $$2}'

up: ## Démarre la stack locale
	$(DC) up -d

down: ## Arrête la stack
	$(DC) down

build: ## (Re)build les images
	$(DC) build

install: ## composer install dans le conteneur php
	$(PHP) composer install

migrate: ## Applique les migrations Doctrine
	$(PHP) php bin/console doctrine:migrations:migrate --no-interaction

jwt-keys: ## Génère la paire de clés JWT (Lexik)
	$(PHP) php bin/console lexik:jwt:generate-keypair --skip-if-exists

test: ## Lance PHPUnit
	$(PHP) php bin/phpunit

phpstan: ## Analyse statique (niveau max)
	$(PHP) vendor/bin/phpstan analyse

cs: ## Vérifie le style (dry-run)
	$(PHP) vendor/bin/php-cs-fixer fix --dry-run --diff

cs-fix: ## Corrige le style
	$(PHP) vendor/bin/php-cs-fixer fix

front-install: ## npm install (front)
	$(DC) run --rm app npm install

front-lint: ## Lint du front
	$(DC) run --rm app npm run lint
