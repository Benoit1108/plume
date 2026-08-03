# Checklist de déploiement en production — Plume

Prérequis pour un déploiement **hébergement-agnostique** (VPS Docker ou PaaS FR — à trancher au 1er
déploiement, cf. [cadrage V2](../design/V2-cadrage.md)). Cette liste est le pendant opérationnel de
V2.0-c : la config est prête, cette page dit **quoi surcharger** avant d'ouvrir au public.

> Fail-fast en place : `MAILBOX_ENCRYPTION_KEY` (ADR-0016), `TOTP_ENCRYPTION_KEY` (ADR-0027) et un garde-fou
> qui refuse de démarrer si `APP_SECRET` / `JWT_PASSPHRASE` sont restés sur le placeholder de dev
> (`ProductionConfigGuard`). Ils rattrapent les oublis les plus dangereux — mais ne dispensent pas de cette liste.

## 1. Secrets à surcharger IMPÉRATIVEMENT (jamais les valeurs de dev)

| Variable | Rôle | Générer |
|---|---|---|
| `APP_SECRET` | Signature/CSRF Symfony | `php -r "echo bin2hex(random_bytes(32));"` |
| `JWT_PASSPHRASE` | Passphrase des clés JWT | mot de passe fort ; régénérer les clés (`lexik:jwt:generate-keypair`) avec |
| `MAILBOX_ENCRYPTION_KEY` | Chiffrement des tokens OAuth (ADR-0016) | `php -r "echo base64_encode(random_bytes(32));"` |
| `TOTP_ENCRYPTION_KEY` | Chiffrement du secret 2FA/TOTP au repos (ADR-0027) | `php -r "echo base64_encode(random_bytes(32));"` |
| `APP_DB_PASSWORD` | Mot de passe du rôle runtime `plume_app` | mot de passe fort ; répercuter dans `APP_DATABASE_URL` |
| Mot de passe du rôle propriétaire `plume` | Migrations/console/scheduler | mot de passe fort ; répercuter dans `DATABASE_URL` |
| `GOOGLE_CLIENT_SECRET` / `MICROSOFT_CLIENT_SECRET` | OAuth mail réel | consoles Google/Microsoft |
| `ADMIN_DATABASE_URL` | Connexion back-office (rôle **propriétaire**, réservée aux routes ROLE_ADMIN) | répercuter le mot de passe fort du rôle `plume` |

- Ne **jamais** committer ces valeurs (`.env.local` / secrets de la plateforme ; `.env` = défauts de dev).
- `APP_ENV=prod`, `APP_DEBUG=0`.

## 2. Réseau / proxy

- `TRUSTED_PROXIES` = IP/réseau du proxy same-origin devant l'API (sinon `getClientIp()` = IP du
  proxy → rate-limiting par IP effondré). Défaut loopback OK si proxy sur le même hôte.
- `CORS_ALLOW_ORIGIN`, `DEFAULT_URI`, `APP_FRONTEND_URL`, les `*_REDIRECT_URI` OAuth = domaine de prod (HTTPS).
- **`MAILER_DSN`** = transport des emails SYSTÈME (vérification, reset de mot de passe…). Défaut
  `null://null` (aucun envoi) → **obligatoire en prod** (SMTP/API, ex. `smtp://…`), + `MAIL_FROM`.
- **Sonde de santé** : `GET /api/v1/health` (public, 200/503) pour le load-balancer / monitoring.

## 2bis. Observabilité (ADR-0030)

- **Logs** : en prod, sortie **JSON structurée sur stderr** (à récupérer par la plateforme). Chaque ligne
  porte `extra.tenant_id` + `extra.request_id` (corrélation par compte et par requête, HTTP **et** worker).
- **`X-Request-Id`** : réutilisé s'il est fourni par le proxy en amont (forme sûre), sinon généré ;
  renvoyé dans la réponse. Configurer le proxy pour le poser/propager si un id de trace existe déjà.
- **`SENTRY_DSN`** : **vide = error-tracking désactivé** (le bundle n'est même chargé qu'en prod). Pour
  activer : renseigner le DSN d'un projet **Sentry** (SaaS ou self-hosté). `send_default_pii` est à
  `false` (aucune PII envoyée) ; les événements sont tagués `tenant_id` + `request_id`.

## 2ter. Garde-fou de coût IA (ADR-0032)

- **`AI_MONTHLY_TOKEN_BUDGET`** : plafond mensuel de jetons Anthropic (entrée+sortie). **Défaut `0` =
  illimité** → **à fixer avant d'ouvrir au public** (ex. `2000000`). Au-delà, repli automatique sur le
  générateur gratuit (aucune facture surprise, aucune coupure de service).
- **`AI_GENERATION_ENABLED`** : **bouton d'arrêt d'urgence**. `0` coupe instantanément tout appel payant
  (repli gratuit). Défaut `1`.
- Rappel : sans `ANTHROPIC_API_KEY`, aucune IA payante n'est appelée (générateur local gratuit).
- Suivi : `GET /admin/status` → `aiUsage` (jetons du mois, plafond, appels, état) — visible au back-office.

## 3. Base de données (deux rôles, RLS — ADR-0023)

Dans l'ordre, en tant que **propriétaire `plume`** :

```
doctrine:migrations:migrate --no-interaction
messenger:setup-transports          # crée messenger_messages + les files async, io, failed
app:db:provision-app-role           # crée/actualise plume_app (DML only, soumis à la RLS)
```

Puis l'**API + les workers** tournent sous `plume_app` (runtime, RLS active). Sauvegardes DB
**chiffrées** + testées (restauration). Rotation périodique des secrets ci-dessus.

Créer le (ou les) **administrateur du back-office** : `php bin/console app:admin:create <email>`
(compte hors tenant, ROLE_ADMIN — jamais créé par l'inscription publique).

## 4. Processus à lancer

- **API** (FrankenPHP).
- **`worker`** → `messenger:consume async` (events/projections légers).
- **`worker_io`** → `messenger:consume io` (relèves RSS/IMAP/Graph — I/O lourd isolé, ADR-0022 §5).
- **`scheduler`** → `messenger:consume scheduler_default`, rôle **propriétaire**, **UNE seule
  instance** (maintenance cross-tenant : fan-out, purges RGPD/brut).

## 4bis. Performance runtime (au 1er déploiement — revue globale perf)

- **OPcache prod** : `opcache.validate_timestamps=0` + `opcache.preload` (préchargement Symfony) —
  installé mais non configuré (`Dockerfile`). Premier levier de latence.
- **FrankenPHP worker mode** : `frankenphp { worker ... }` (boot Symfony réutilisé entre requêtes,
  latence ÷2-5). ⚠️ si activé, le reset tenant `kernel.request` (déjà en place, V2.0-c) devient
  critique — il est déjà là.
- **Pooler** : pgbouncer si le churn de connexions grimpe (Doctrine non persistant).
- **`worker_io`** : à ~100+ boîtes email, le fan-out (5 min) dépasse un worker séquentiel → prévoir
  des **réplicas** de `worker_io` (le transport doctrine gère les consommateurs concurrents via
  `FOR UPDATE SKIP LOCKED`). Surveiller la profondeur de la file `io` (exposée dans le back-office).

## 5. Multi-instances (scale horizontal) — sinon ignorer

Si l'API tourne sur **plusieurs instances**, les compteurs de rate-limiting DOIVENT être partagés,
sinon la limite effective est multipliée par le nombre d'instances :

- Surcharger le paramètre `app.cache_adapter` → `cache.adapter.redis` (config prod).
- Pointer `REDIS_DSN` sur l'instance Redis.

Le `scheduler` reste en **exemplaire unique** quel que soit le nombre d'instances API/worker.

## 6. Prérequis externes (hors code, à anticiper)

- **Validation des apps OAuth** Google/Microsoft (sortie du mode test → écran de consentement
  vérifié) — **processus long**, à lancer tôt.
- **Nom de domaine** + certificat (FrankenPHP fait ACME automatiquement si le domaine est public).
- **RGPD** : registre de traitement + DPA signés (sous-traitants Anthropic, Google/Microsoft,
  hébergeur) — cf. reste de V2.0-a.
