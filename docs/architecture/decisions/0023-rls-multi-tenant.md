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

## Amendement (2026-08-06) — le câblage du rôle runtime, et pourquoi il faut une garde

**Ce qui n'allait pas.** La répartition des rôles décrite ci-dessus n'était appliquée QUE par
`compose.yaml` (dev) et le `Makefile`. Le kit de déploiement (`compose.prod.yaml`) passait le même
`env_file` aux quatre services : en production, l'API et les workers auraient tourné sous le rôle
propriétaire — qui est de plus le `POSTGRES_USER` du conteneur, donc **SUPERUSER**. La RLS aurait
été **totalement inerte**, alors que la checklist de déploiement affirmait le contraire. Mesuré en
base : 37 pistes visibles sans aucun tenant sous `plume`, 0 sous `plume_app`.

**Décisions.**

1. **Le rôle est une propriété du PROCESSUS, pas de l'application.** `compose.prod.yaml` reproduit le
   dev : `app`/`worker`/`worker_io` reçoivent un second `env_file` (`api/.env.runtime.local`) qui
   bascule `DATABASE_URL` sur `plume_app` ; le `scheduler` garde le propriétaire. Le défaut du
   fichier commun reste le propriétaire, pour que migrations et console fonctionnent sans piège.
2. **Une garde fail-fast, parce que le mode d'échec est SILENCIEUX.** C'est le point central : un
   rôle privilégié ne provoque aucune erreur — l'application fonctionne, simplement sans isolation.
   Une erreur de câblage serait donc invisible. `RlsRuntimeRoleGuard` (listener `kernel.request`,
   `when@prod`) interroge `pg_roles` une fois par processus et **refuse de servir** si le rôle est
   `SUPERUSER` ou `BYPASSRLS`. Il reste tolérant si la base est injoignable : une panne ne doit pas
   se déguiser en erreur de configuration.
3. **`ENABLE` et non `FORCE` : maintenu.** Le propriétaire doit contourner la RLS (migrations,
   scheduler, back-office). `FORCE` casserait la maintenance cross-tenant sans rien apporter, dès
   lors que le runtime n'est plus propriétaire — ce que la garde et les tests vérifient désormais.

**Ce qui est vérifié en CI.** `RlsCoverageTest` teste le **contenu** des policies, pas seulement leur
existence : une policy `USING (true)`, sur la mauvaise colonne, ou sans `WITH CHECK`, échoue
maintenant (validé par mutation : table tenantée sans policy → rouge ; policy permissive → rouge).
Il assert aussi que `plume_app` n'est ni `SUPERUSER`, ni `BYPASSRLS`, ni propriétaire d'une table.
`RowLevelSecurityTest` couvre deux profils de tables (agrégat ORM + projection DBAL pure) et vérifie
en plus qu'un tenant ne peut pas **supprimer** les lignes d'un autre. `SubscriptionIsolationTest`
couvre la table `subscription`, exclue de la RLS : son isolation ne repose que sur un filtrage
applicatif explicite, or c'est elle qui décide du droit d'accès payant — un webhook Stripe ne peut
affecter que le tenant propriétaire du client concerné, et aucun identifiant Stripe ne fuite dans le
snapshot exposé à l'UI.

**Limite assumée.** La suite fonctionnelle tourne toujours sous le propriétaire : elle pose ses
fixtures hors session tenantée et exercerait mal la RLS. La garantie d'isolation en base est portée
par les trois tests ci-dessus (connexion réelle `plume_app`) et par l'E2E, pas par les 170 tests
fonctionnels — dont l'objet est le filtre applicatif, première ligne de défense.
