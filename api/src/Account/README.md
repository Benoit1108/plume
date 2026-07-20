# Account — Comptes & Tenancy (Generic)

Tenants, utilisateurs, authentification, profil de la Traductrice.

## Livré

- **Authentification** (M2.0) : JWT en **cookies httpOnly** + refresh tokens (gesdinet).
  Provider entité + listener injectant le `tenant_id` dans les claims JWT et alimentant le
  `TenantContext`. Le **changement de mot de passe révoque tous les refresh tokens** du
  compte (expulse une session détournée — remédiation revue M3.0).
- **`Domain/Profile/`** : agrégat `Profile` (un par tenant) — objectif hebdomadaire
  (`weeklyGoal`), fuseau (`timezone`), présentation (`bio`, `specialties`, `signature` —
  matière première des prompts de génération, M1.4), identité d'affichage (`firstName`,
  `lastName` — M3.0). Events `ProfileCreated`, `WeeklyGoalChanged`,
  `ProfilePresentationChanged`, `ProfileIdentityChanged` (émis seulement si la valeur change).
- **`Application/`** : `UpdateProfile`, `GetProfile` (read models `ProfileSettings` / `ProfileView`).
- Écran **Compte** (front) : mot de passe et nom d'affichage.

Tarifs (`Rate`) : différés (cf. ROADMAP § M2 du `DOMAIN-MODEL.md`).
