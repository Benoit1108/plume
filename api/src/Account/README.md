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
- **Ouverture des comptes (V2.1)** :
  - **Inscription publique** (`POST /register`) : crée un compte **non vérifié** ; login refusé tant que
    l'email n'est pas confirmé. Email normalisé, provider **insensible à la casse** (`AppUserProvider`).
  - **Vérification d'email SANS état** (`EmailVerificationSigner` : HMAC `kernel.secret`, TTL 24 h) +
    renvoi anti-énumération (`ResendVerificationController`, 204, débit limité). Cf.
    [ADR-0029](../../../docs/architecture/decisions/0029-verification-email-sans-etat.md).
  - **Mot de passe oublié** AVEC état (`password_reset_token`, hash sha256, usage unique, anti-énumération 204).
  - `AccountStatusChecker` : codes stables `email_not_verified` / `account_deleted`.
- **2FA TOTP + sessions (V2.1)** : enrôlement 2 temps, anti-rejeu (`totp_last_used_step`), codes de secours
  sha256, librairie `spomky-labs/otphp` — [ADR-0027](../../../docs/architecture/decisions/0027-2fa-totp.md).
  Gestion des **sessions actives** (liste, révocation unitaire / des autres) ; activation/désactivation 2FA
  **révoque les sessions**. Chaque session porte son **appareil** (`refresh_tokens.user_agent`, résumé par
  `DeviceLabel`) et sa **dernière activité** (`last_seen_at`, posés par `StampRefreshTokenListener`) — sans
  quoi les lignes sont interchangeables. `PruneSessionsListener` ferme les expirées et plafonne les vivantes
  (10) à chaque authentification.
- **RGPD (V2.0-a)** : suppression de compte (soft-delete + purge 30 j) + export ZIP —
  [ADR-0025](../../../docs/architecture/decisions/0025-rgpd-suppression-export.md).
- Écran **Compte** (front) : identité, mot de passe, **section Sécurité** (2FA + sessions), export, suppression.

Tarifs (`Rate`) : différés (cf. ROADMAP § M2 du `DOMAIN-MODEL.md`).
