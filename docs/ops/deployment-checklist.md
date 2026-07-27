# Checklist de déploiement en production — Plume

Prérequis pour un déploiement **hébergement-agnostique** (VPS Docker ou PaaS FR — à trancher au 1er
déploiement, cf. [cadrage V2](../design/V2-cadrage.md)). Cette liste est le pendant opérationnel de
V2.0-c : la config est prête, cette page dit **quoi surcharger** avant d'ouvrir au public.

> Fail-fast en place : `MAILBOX_ENCRYPTION_KEY` (ADR-0016) et un garde-fou qui refuse de démarrer si
> `APP_SECRET` / `JWT_PASSPHRASE` sont restés sur le placeholder de dev (`ProductionConfigGuard`).
> Ils rattrapent les oublis les plus dangereux — mais ne dispensent pas de cette liste.

## 1. Secrets à surcharger IMPÉRATIVEMENT (jamais les valeurs de dev)

| Variable | Rôle | Générer |
|---|---|---|
| `APP_SECRET` | Signature/CSRF Symfony | `php -r "echo bin2hex(random_bytes(32));"` |
| `JWT_PASSPHRASE` | Passphrase des clés JWT | mot de passe fort ; régénérer les clés (`lexik:jwt:generate-keypair`) avec |
| `MAILBOX_ENCRYPTION_KEY` | Chiffrement des tokens OAuth (ADR-0016) | `php -r "echo base64_encode(random_bytes(32));"` |
| `APP_DB_PASSWORD` | Mot de passe du rôle runtime `plume_app` | mot de passe fort ; répercuter dans `APP_DATABASE_URL` |
| Mot de passe du rôle propriétaire `plume` | Migrations/console/scheduler | mot de passe fort ; répercuter dans `DATABASE_URL` |
| `GOOGLE_CLIENT_SECRET` / `MICROSOFT_CLIENT_SECRET` | OAuth mail réel | consoles Google/Microsoft |

- Ne **jamais** committer ces valeurs (`.env.local` / secrets de la plateforme ; `.env` = défauts de dev).
- `APP_ENV=prod`, `APP_DEBUG=0`.

## 2. Réseau / proxy

- `TRUSTED_PROXIES` = IP/réseau du proxy same-origin devant l'API (sinon `getClientIp()` = IP du
  proxy → rate-limiting par IP effondré). Défaut loopback OK si proxy sur le même hôte.
- `CORS_ALLOW_ORIGIN`, `DEFAULT_URI`, les `*_REDIRECT_URI` OAuth = domaine de prod (HTTPS).

## 3. Base de données (deux rôles, RLS — ADR-0023)

Dans l'ordre, en tant que **propriétaire `plume`** :

```
doctrine:migrations:migrate --no-interaction
messenger:setup-transports          # crée messenger_messages + les files async, io, failed
app:db:provision-app-role           # crée/actualise plume_app (DML only, soumis à la RLS)
```

Puis l'**API + les workers** tournent sous `plume_app` (runtime, RLS active). Sauvegardes DB
**chiffrées** + testées (restauration). Rotation périodique des secrets ci-dessus.

## 4. Processus à lancer

- **API** (FrankenPHP).
- **`worker`** → `messenger:consume async` (events/projections légers).
- **`worker_io`** → `messenger:consume io` (relèves RSS/IMAP/Graph — I/O lourd isolé, ADR-0022 §5).
- **`scheduler`** → `messenger:consume scheduler_default`, rôle **propriétaire**, **UNE seule
  instance** (maintenance cross-tenant : fan-out, purges RGPD/brut).

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
