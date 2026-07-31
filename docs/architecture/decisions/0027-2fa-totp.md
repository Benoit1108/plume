# ADR-0027 — Double authentification (TOTP) : librairie éprouvée, anti-rejeu, codes de secours

- **Statut** : Accepté (2026-07-29, V2.1).
- **Contexte** : l'ouverture publique des comptes + le back-office imposent une **MFA**. Question posée
  (Benoit) : « le TOTP maison, c'est une bonne idée niveau sécurité ? ». **Non** — on n'écrit pas sa propre
  cryptographie. On délègue à une implémentation éprouvée et on concentre notre code sur l'**intégration**
  (anti-rejeu, cycle de vie, codes de secours), là où sont les vrais pièges.

## Décision

- **Librairie `spomky-labs/otphp`** pour la génération/vérification TOTP (RFC 6238), plutôt que maison.
- **Enrôlement en 2 temps** : `POST /account/2fa/setup` génère un secret + une URI `otpauth://` (encore
  **inactive**) ; `POST /account/2fa` confirme un **premier code** → active la 2FA et délivre les **codes de
  secours** (affichés **une seule fois**). On ne peut pas s'enfermer dehors (on prouve que l'app fonctionne
  avant activation).
- **Anti-rejeu** : `totp_last_used_step` mémorise le dernier pas de temps consommé — un code déjà utilisé
  dans sa fenêtre de 30 s est **refusé** (sinon un code capté serait rejouable ~30 s).
- **Fenêtre ±1 pas** : tolère la dérive d'horloge, sans élargir inutilement la surface.
- **Codes de secours** : entropie 64 bits, **usage unique**, stockés en **sha256** (non réversibles),
  normalisés à la vérification. Le nombre restant est exposé à l'utilisatrice.
- **Intégration login** : `TwoFactorLoginListener` sur `CheckPassportEvent` **priorité -10** (donc **après**
  la vérification du mot de passe) ; si la 2FA est active et l'OTP absent/invalide → échec avec **codes
  stables** `2fa_required` / `2fa_invalid` (le front sait alors afficher le champ OTP).
- **Back-office** : `AdminTwoFactorRequiredListener` → **403 `admin_2fa_required`** sur `/admin/*` si un
  `ROLE_ADMIN` n'a pas encore enrôlé sa 2FA (force l'enrôlement avant tout accès — cf. ADR-0026).
- **Cycle de vie des sessions** : l'activation **et** la désactivation **révoquent toutes les sessions**
  (une session détournée ne survit pas au changement d'état 2FA).
- **Reset support** : `AdminResetTwoFactorController` (dernier recours si l'utilisatrice perd son appareil
  **et** ses codes de secours) — désactive la 2FA, **tracé au journal d'audit**.

## Conséquences

- ✅ La crypto est déléguée à une librairie maintenue ; notre logique sensible (anti-rejeu, codes de
  secours, cycle de vie) est **couverte par des tests unitaires** (`TotpServiceTest`) et fonctionnels.
- ✅ Codes d'erreur **stables** → contrat clair avec le front (afficher l'OTP, distinguer invalide/requis).
- ✅ **Résolu (2026-07-30) — secret TOTP chiffré au repos.** Port `App\Account\Application\Crypto\SecretCipher`
  + adaptateur `SodiumSecretCipher` (secretbox, même primitive qu'[ADR-0016](0016-chiffrement-tokens-oauth.md)).
  Clé **dédiée** `TOTP_ENCRYPTION_KEY` (32 o base64), **fail-fast en prod**, dérivée d'`APP_SECRET` avec
  **séparation de domaine** (préfixe `totp:`) hors prod. Le secret n'est déchiffré qu'en mémoire, le temps de
  générer l'URI de provisionnement (setup) ou de vérifier un code (confirm / login). Colonnes élargies à 255.
  *NB : le patron sodium est dupliqué avec Mailbox (chaque contexte possède son port) — unification `Shared`
  = dette tracée ADR-0022.*
- ✅ **Résolu (2026-07-30) — QR code à l'enrôlement.** Rendu client (`useQrCode` → lib `qrcode`) de l'URI
  `otpauth://` déjà produite ; la clé reste affichée **en repli** (saisie manuelle).
