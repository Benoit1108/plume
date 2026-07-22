# ADR-0023 — Row-Level Security multi-tenant (filet de sécurité en base)

- **Statut : Accepté** (2026-07-22 — chantier « pré-V2 » de durcissement multi-tenant, cf.
  [note de cadrage PRE-V2](../PRE-V2-cadrage.md))
- **Contexte** : jusqu'ici l'isolation multi-tenant reposait **uniquement** sur la couche
  applicative — `SQLFilter` Doctrine (ORM) + prédicat `tenant_id` explicite dans les read models
  DBAL (ADR-0013), pilotés par le `TenantContext`. Une seule ligne de défense : un handler, une
  requête DBAL brute ou un projecteur qui oublierait le filtre exposerait des données d'un autre
  tenant. À l'ouverture SaaS multi-utilisateurs (V2), ce risque devient inacceptable. On veut un
  **filet de sécurité indépendant, en base**, qui échoue fermé même si le filtre applicatif est
  contourné par erreur.

## Décision

Activer la **Row-Level Security (RLS) PostgreSQL** comme **défense en profondeur** sous le filtre
applicatif (qui reste la première ligne, pour la performance et l'ergonomie des requêtes).

### 1. Deux rôles PostgreSQL

- **`plume` (propriétaire)** : migrations, tests, console, scheduler. **Contourne** la RLS
  (`ENABLE`, pas `FORCE`). Reste le rôle d'administration/maintenance.
- **`plume_app` (runtime, non-propriétaire)** : l'API HTTP + le worker Messenger s'y connectent
  et sont **soumis** à la RLS. Créé de façon idempotente par `app:db:provision-app-role`
  (privilèges DML + `DEFAULT PRIVILEGES` ; **aucun** droit DDL/`CREATE` — moindre privilège).

### 2. Variable de session comme porteuse du tenant

`TenantScope` est le **point unique** qui synchronise, à chaque `activate()`/`clear()`, trois
choses : le `TenantContext`, le `SQLFilter` Doctrine **et** la variable de session Postgres
`app.current_tenant` (`set_config`, niveau session — lisible hors transaction par les read models).
Les policies comparent `tenant_id::text = current_setting('app.current_tenant', true)`.

Propagation symétrique **partout** :
- **HTTP** : activée à l'auth JWT (`TenantContextListener`), remise à zéro en fin de requête
  (`TenantScopeResetListener` sur `kernel.terminate` — indispensable car FrankenPHP réutilise
  process et connexion).
- **Worker** : `TenantIsolationMiddleware` active le tenant du message (convention : propriété
  `tenantId`, tous les domain events et commandes async la portent) puis nettoie, pour **chaque**
  message consommé.

### 3. Fail-closed

Hors session tenantée, `current_setting('app.current_tenant', true)` vaut `NULL` (jamais posée)
ou `''` (après `clear()`) → le prédicat est faux → **aucune ligne visible**. Une connexion qui
« oublie » d'activer un tenant ne voit rien, plutôt que tout.

### 4. Périmètre

- **Sous RLS** (11 tables métier portant `tenant_id`) : `alert_feed`, `candidate_lead`,
  `connected_mailbox`, `draft`, `interaction`, `lead`, `organization`, `outbound_message`,
  `profile`, `raw_alert`, `template`.
- **Exclus** : `app_user` (lu **avant** le tenant, au login → jamais de RLS sinon l'auth casse),
  `refresh_tokens`, `messenger_messages`, `doctrine_migration_versions` (infra, sans `tenant_id`).

### 5. Maintenance cross-tenant → scheduler propriétaire

Les tâches du Scheduler (relève de tous les tenants ayant un flux/boîte, purge globale du brut)
sont **cross-tenant par conception**. Le service `scheduler` tourne donc sous le rôle
**propriétaire** (contourne la RLS pour énumérer/purger). Il ne sert **aucun** trafic utilisateur
et se limite à du fan-out : les messages par-tenant qu'il émet partent sur `async`, consommés par
le `worker` (`plume_app`, tenant activé → RLS appliquée).

## Conséquences

- **+** Filet indépendant : une fuite du filtre applicatif ne suffit plus à traverser les tenants.
- **+** `messenger_messages` n'est plus auto-créée au runtime (`auto_setup: false`) — le rôle
  runtime n'a pas `CREATE` ; la table/trigger est créée par `messenger:setup-transports` en
  propriétaire (Makefile `migrate`/`test`, CI).
- **−** Toute nouvelle table métier tenantée doit : porter `tenant_id`, recevoir sa policy dans une
  migration, et rester couverte par les `DEFAULT PRIVILEGES` de `plume_app`. Un oubli de policy
  laisse la table **non protégée par la RLS** (le filtre applicatif reste, mais le filet saute).
- **−** Le scheduler contourne la RLS : son code (ticks) doit rester strictement du fan-out
  cross-tenant, jamais de logique exposant des données à un utilisateur.
- Couvert par `RowLevelSecurityTest` (isolation par tenant, fail-closed, rejet `WITH CHECK`) via
  une vraie connexion `plume_app`, et par la suite E2E (API + worker réels sous `plume_app`).
