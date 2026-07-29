# ADR-0029 — Vérification d'email sans état (HMAC) vs reset mot de passe avec état

- **Statut** : Accepté (2026-07-29, V2.1b — inscription publique).
- **Contexte** : l'inscription publique introduit deux « jetons envoyés par email » : **vérifier l'email**
  d'un nouveau compte, et **réinitialiser un mot de passe oublié**. Ils se ressemblent, mais leurs exigences
  de sécurité **diffèrent** — on ne les implémente donc pas pareil.

## Décision

### Vérification d'email — jeton **SANS état** (HMAC signé)
- `EmailVerificationSigner` signe `email + expiration` en **HMAC** (clé = `kernel.secret`), **TTL 24 h**.
  **Aucune table** : la validité est **auto-portée** (signature + horodatage vérifiés à la lecture).
- Pourquoi sans état : la vérification est **idempotente** et **non révocable par nature** (confirmer deux
  fois est un no-op). Rien à stocker, rien à purger, aucun risque de table qui gonfle.

### Reset mot de passe — jeton **AVEC état** (table hachée)
- Table `password_reset_token` : **hash sha256** du jeton, `expires_at`, **usage unique** (consommé à
  l'usage). Pourquoi avec état : un reset **doit** être révocable et à usage unique — un lien utilisé ne doit
  pas resservir, changer le mot de passe doit invalider les autres liens. Le sans-état ne le garantit pas.
- **Anti-énumération** : `POST /account/password/forgot` renvoie **toujours 204**, le jeton n'est créé que si
  le compte existe (idem `resend`). Le reset **révoque les sessions** en vigueur.

### Cycle de vie du compte
- `app_user.email_verified` par **défaut `true`** (**zéro-ripple**) : la CLI, le seed et les tests créent des
  comptes **de confiance** déjà vérifiés ; **seule** l'inscription publique appelle `requireEmailVerification()`
  (passe à `false` et envoie le lien). Aucun compte/outillage existant n'est cassé par l'ajout.
- `AccountStatusChecker` (UserChecker) refuse le login d'un email non vérifié avec le code stable
  `email_not_verified` ; `ResendVerificationController` (anti-énumération 204, débit limité) renvoie le lien.

## Conséquences

- ✅ Pas de table ni de purge pour la vérification ; l'état n'est payé que là où la **révocabilité** l'exige
  (le reset). Chaîne complète **testée bout-en-bout** (l'email part, le jeton qu'il contient fonctionne).
- ✅ Le défaut `true` garantit **aucune régression** sur les comptes existants et l'outillage.
- ⚠️ Un lien de vérification reste **valide 24 h même après usage** (sans état ⇒ non révocable) — acceptable
  (re-vérifier est inoffensif).
- ⚠️ Le secret HMAC est `kernel.secret` : une **rotation d'`APP_SECRET`** invalide les liens de vérification
  en vol (rare, acceptable — l'utilisatrice redemande un lien).
