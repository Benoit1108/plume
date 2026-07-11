# Account — Comptes & Tenancy (Generic)

Tenants, utilisateurs, authentification, profil de la Traductrice.

À peupler (M0-finalisation) :
- `Infrastructure/Security/` : provider entité `SecurityUser` (remplace le provider en mémoire), listener injectant le `tenant_id` dans les claims JWT et alimentant le `TenantContext`.
- `Domain/` : `Profile` (paires de langues, spécialités, tarifs, signature), `WeeklyGoal`.
