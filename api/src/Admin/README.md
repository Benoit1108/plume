# Admin — Back-office (Generic, hors tenant)

Administration transverse du SaaS par un **compte dédié `ROLE_ADMIN`** (hors tenant métier), créé
**uniquement par CLI** — jamais par l'inscription publique. Lecteur **cross-tenant** légitime.

Voir [ADR-0026](../../../docs/architecture/decisions/0026-connexion-admin-proprietaire.md) (connexion
propriétaire) et [ADR-0027](../../../docs/architecture/decisions/0027-2fa-totp.md) (2FA obligatoire).

## Livré

- **`Infrastructure/Console/CreateAdminCommand`** : `app:admin:create <email> [password]` — crée un
  administrateur (tenant généré jamais peuplé, `ROLE_ADMIN`).
- **`Infrastructure/Http/`** (routes `^/api/v1/admin`, réservées `ROLE_ADMIN`) :
  - `AdminOverviewController` — comptages cross-tenant (jamais de contenu métier).
  - `AdminStatusController` — **statut système** : profondeur des files Messenger, âge du backlog
    (worker bloqué ?), file `failed`, boîtes en erreur. Distinct de `/health` (sonde publique LB).
  - `AdminMetricsController` — **métriques produit** (sans PII) : comptes actifs (30 j), inscriptions
    par semaine, répartition des pistes par statut, totaux.
  - `AdminAccountsController` — liste des comptes des traductrices (admins exclus), recherche par email,
    bornée à 100 ; **comptages** organisations/pistes + statut de boîte.
  - `AdminRequestAccountDeletionController` — demande de suppression RGPD **côté support** (soft-delete +
    purge 30 j, sémantique identique au self-service), idempotente, sessions révoquées.
  - `AdminResetTwoFactorController` — reset 2FA de dernier recours (appareil + codes de secours perdus).
- **`Infrastructure/Security/AdminTwoFactorRequiredListener`** : **403 `admin_2fa_required`** si un
  `ROLE_ADMIN` accède au back-office sans 2FA enrôlée (force l'enrôlement).

## Garde-fous (non négociables)

- **Connexion propriétaire `admin`** (contourne la RLS) injectée **uniquement ici**, explicitement
  (`#[Autowire(service: 'doctrine.dbal.admin_connection')]`) — jamais la connexion par défaut ni l'ORM.
- **Minimisation** : comptages seulement, jamais de contenu métier.
- **Toute action support est tracée** au **journal d'audit hors-tenant** (`AuditLogger` : survit à la
  purge RGPD). Un compte admin n'est pas supprimable par les voies support.
