# ADR-0026 — Back-office : connexion propriétaire dédiée (lecteur cross-tenant)

- **Statut** : Accepté (2026-07-28, back-office). **Amende [ADR-0023](0023-rls-multi-tenant.md)** (en nomme
  une exception cadrée, sans la contredire).
- **Contexte** : le back-office (contexte `Admin`, `ROLE_ADMIN`) doit lire **à travers tous les tenants** :
  vue d'ensemble (comptages cross-tenant), liste des comptes des traductrices. Sous le rôle runtime
  `plume_app` + RLS (ADR-0023, fail-closed), ces lectures renverraient **vide** — la RLS est justement
  là pour empêcher un tenant d'en voir un autre. ADR-0023 admet déjà un lecteur cross-tenant légitime
  **hors RLS** : le scheduler propriétaire pour la maintenance. Le back-office est **la même catégorie**.

## Décision

- Une connexion DBAL **dédiée `admin`** (`ADMIN_DATABASE_URL`, **rôle PROPRIÉTAIRE** `plume`) contourne la
  RLS. Elle est **lazy** (ouverte seulement si utilisée) et injectée **UNIQUEMENT** dans les services du
  contexte `Admin`, explicitement, via `#[Autowire(service: 'doctrine.dbal.admin_connection')]` — **jamais**
  la connexion par défaut, **jamais** l'`EntityManager` de l'app. Une recherche du service dans le code
  prouve que seul `Admin/` la référence.
- **Deux gardes en amont** : `access_control` réserve `^/api/v1/admin` à `ROLE_ADMIN`, et la **2FA est
  obligatoire** ([ADR-0027](0027-2fa-totp.md), `AdminTwoFactorRequiredListener` → 403 `admin_2fa_required`).
- **Minimisation** : les lectures ne renvoient que des **comptages** (nombre d'organisations, de pistes,
  statut de boîte) et l'email — **jamais de contenu métier**. Les administrateurs sont exclus des listes
  (`roles NOT LIKE '%ROLE_ADMIN%'`).
- **Mutations support** (demande de suppression RGPD, reset 2FA) : elles passent aussi par la connexion
  `admin` (elles agissent hors du tenant courant), sont **idempotentes** et **tracées au journal d'audit
  hors-tenant** (`AuditLogger` : qui a déclenché quoi, quand) — un admin n'est pas supprimable par cette voie.

## Conséquences

- ✅ Le back-office fonctionne **sans affaiblir la RLS** de l'application : deux rôles distincts, la
  connexion `admin` n'est jamais la connexion par défaut de l'app.
- ✅ Surface d'attaque bornée : deux gardes (rôle + 2FA) **et** injection explicite localisée au seul
  contexte `Admin`. Toute action est journalisée hors-tenant (survit à la purge RGPD).
- ⚠️ Contredit **littéralement** la règle « le runtime n'utilise que `plume_app` » d'ADR-0023 : **cet ADR
  est l'exception nommée**. Toute nouvelle utilisation de la connexion `admin` doit rester dans `Admin/` et
  se limiter à un besoin cross-tenant réel (sinon : fuite d'isolation).
- ⚠️ `ADMIN_DATABASE_URL` porte le **rôle propriétaire** en prod → secret sensible, distinct de
  `DATABASE_URL`, couvert par le fail-fast secrets prod (`ProductionConfigGuard`).
