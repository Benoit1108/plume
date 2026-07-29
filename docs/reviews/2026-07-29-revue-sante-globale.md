# Revue de santé GLOBALE — projet entier (2026-07-29)

Grande revue à **7 axes** (les 4 habituels + 3 inédits : performance/DB, qualité des tests, produit/UX/a11y),
sur **tout le projet** avec ~60 % d'effort sur le code jamais revu (V2.1 socle + inscription/vérif email +
onboarding, Notification, Admin, 2FA + sessions). 7 audits adversariaux parallèles, lecture du code réel.

## Notes par axe

| Axe | Note | Synthèse |
|---|---|---|
| **Back + archi** | **7,5 / 10** | Nouveau code d'hygiène sécu réelle ; 2 P1 de lockout + secret TOTP en clair (contredit une règle « non négociable »). |
| **Sécurité** | **8,5 / 10** | Aucun P0/P1 exploitable ; jetons/IDOR/RLS solides. P2 : 2FA n'invalide pas les sessions, oracle temporel, admin sans 2FA. |
| **Front** | **8 / 10** | TanStack discipliné, 0 `v-html`, parité i18n réelle. P1 : cache non purgé au logout, clé i18n manquante, erreurs silencieuses. |
| **Docs** | **5 / 10** | Récidive n°4 « docs de tête périmées » (5 jalons présentés comme futurs) + contradiction ADR-0023 sans nouvel ADR. |
| 🆕 **Perf / DB** | **7 / 10** | Indexation tenant-first exemplaire. P1 : policy RLS `::text` non-sargable (preuve EXPLAIN), `notification` sans rétention, `worker_io` unique. |
| 🆕 **Tests** | **7 / 10** | Pyramide réelle + méta-tests rares. P0 : chaîne email jamais assertée, aucun E2E « compte ». |
| 🆕 **Produit / UX / a11y** | **7 / 10** | Intérieur d'appli exemplaire. **2 P0 d'impasse à la porte d'entrée** (vérif email, lockout 2FA). |

**Convergence forte** : back, sécurité, tests ET UX pointent INDÉPENDAMMENT le même trou n°1 — le
**cycle de vie du compte à l'ouverture publique** (vérification email sans re-envoi + email non normalisé
+ récupération 2FA inexistante). C'est LE chantier avant V2.1.

---

## P0 — Bloqueurs avant ouverture publique (V2.1)

### P0-1 (UX+back+tests, convergent) — Vérification email perdue = compte mort-né, message mensonger
Jeton de vérif expire à 24 h (`EmailVerificationSigner.php:18`), envoi **synchrone** (pas de re-tentative),
**aucun endpoint de renvoi** (`routes.yaml` + grep `resend` vides). Login refusé (`AccountStatusChecker`)
mais le front affiche « **Identifiants incorrects.** » (`login.vue:33`). Ré-inscription → 409. → blocage
permanent, réparable seulement en SQL. **Reco** : `POST /account/verify-email/resend` (204 constant, rate-limité,
envoi async) ; message login dédié « email non vérifié » + lien de renvoi ; action admin « renvoyer la vérification ».

### P0-2 (UX+back+sécu, convergent) — Email non normalisé → lockout silencieux
Inscription `mb_strtolower(trim())` (`RegisterController.php:51`) mais login (`property: email` exact) et
`ForgotPasswordController.php:47` (`trim` seul) ne normalisent pas. `Jane@Example.com` → stockée minuscule →
reconnexion/reset avec la casse d'origine échouent (le reset 204-silencieux n'envoie rien). **Reco** :
normaliser en minuscules en UN point (user loader custom + forgot + login), migration des emails existants.

### P0-3 (UX) — Lockout 2FA sans aucune récupération
Téléphone + codes de secours perdus ⇒ login impossible (`TwoFactorLoginListener`), disable exige d'être
connectée, reset MDP ne touche pas la 2FA, **aucune action admin de reset 2FA**. Compte + données irrécupérables.
**Reco** : action support admin « désactiver la 2FA » (tracée audit + vérif d'identité hors bande) ; documenter dans l'UI.

### P0-4 (tests) — Chaîne email transactionnelle jamais assertée
0 `assertEmail*` dans toute la suite ; les tests register/forgot fabriquent/seedent leur propre jeton →
un contrôleur qui n'enverrait AUCUN mail (ou le mauvais jeton) passerait la CI. **Reco** : `assertEmailCount(1)`
+ extraire l'URL de l'email + dérouler la vérif avec le jeton extrait ; `AccountMailerTest` unitaire.

### P0-5 (tests) — Aucun E2E du cycle de vie du compte
Rien sur inscription / vérif / mot de passe oublié / 2FA / notifications / admin / suppression. Le tenant e2e
partagé ne bloque pas (inscription = tenant jetable neuf). **Reco** : `account-lifecycle.spec.ts` + `admin.spec.ts`.

## P1 — Sérieux

- **[Docs P0-1] Récidive n°4 « docs de tête périmées »** : CLAUDE/README/ROADMAP/OVERVIEW/GLOSSAIRE/DOMAIN-MODEL
  présentent V2.1, Notifications, Admin, 2FA comme *à venir* ; contextes `Notification`/`Admin` absents des cartes.
- **[Docs P0-2] Contradiction ADR-0023** : connexion admin rôle propriétaire = trafic HTTP utilisateur sous le
  rôle qui contourne la RLS, alors que l'ADR le réserve à migrations/tests/console/scheduler. → **ADR-0026** d'amendement.
- **[Perf P1-1] Policy RLS `(tenant_id)::text` non-sargable** (preuve EXPLAIN : seq scan 308 ms vs index 37 ms sur 1M).
  Inoffensif aujourd'hui (prédicat applicatif partout) mais toute requête future sur la RLS seule = full scan silencieux.
  → réécrire en `tenant_id = NULLIF(current_setting('app.current_tenant', true), '')::uuid` (préserve le fail-closed).
- **[Perf P1-2] `notification` : aucune rétention** (croissance infinie) ; idem `audit_log`, `password_reset_token`
  expirés. → tick quotidien de purge (aligné registre RGPD).
- **[Perf P1-3] `worker_io` unique** face au fan-out 5 min : backlog structurel à ~100 boîtes. → prévoir réplicas + alerte profondeur file.
- **[Front P1] `queryClient` non purgé au logout** (`auth.ts:70`) → fuite inter-comptes sur poste partagé. → `clear()` au logout.
- **[Front P1] Clé i18n `account.twoFactor.copied` manquante** (fr+en) → toast littéral au copier des codes. → ajouter + test « clés utilisées ⊆ déclarées ».
- **[Front P1] États d'erreur silencieux/trompeurs** (today/dashboard page blanche ; candidates/leads/templates/orgs/admin « état vide » trompeur avec `retry:false`). → branche d'erreur homogène + réessayer.
- **[Tests P1] Unitaires manquants** : `TotpService` (vecteurs RFC 6238, fenêtre ±1), `EmailVerificationSigner` (expiration/altération), `HealthController` (503 jamais testé). 
- **[Tests P1] Front** : `useAccount.ts` 18,75 % (toute la tranche sécu) masqué par le seuil global → seuils par fichier ; `SecuritySection`/`NotificationBell` testés nulle part (ni unit ni E2E).

## P2 — Durcissement / améliorations (backlog)

**Sécurité / back** : activer la 2FA ne révoque pas les sessions existantes ; **secret TOTP en clair** (contredit
CLAUDE.md « secrets chiffrés au repos » — `EncryptedTokenType` existe déjà) ; codes de secours 32 bits (→ ≥8 octets) ;
`disable` 2FA sans OTP ; TOCTOU anti-rejeu (2 logins parallèles même code) ; **admin sans 2FA obligatoire** (or il a la
connexion owner) ; **oracle temporel** forgot (mailer synchrone trahit l'existence malgré 204 → mailer async) ;
refresh tokens en clair au repos (dette bundle) ; **HSTS absent** du Caddyfile ; 500 sur id non-UUID (notif + admin :
requirement `{36}` trop laxiste) ; race à l'inscription → 500 au lieu de 409 ; reset MDP non tracé à l'audit ;
`ProductionConfigGuard` ne vérifie pas le mdp owner de `ADMIN_DATABASE_URL`.

**Perf** : dashboard/today = agrégats O(historique) + `to_char` non sargable ; tick relance = seq scan horaire
(index partiel `next_follow_up_at`) + **pas de rattrapage** ni filtre statut (notif perdue si scheduler down > 24 h) ;
badge « À trier » sans LIMIT + double GET ; export tout-en-mémoire ; runtime prod sans OPcache preload / worker-mode
FrankenPHP / pooler (checklist).

**UX / a11y** : garde-fou « Contacter » absent sur Aujourd'hui (icône `send` trompeuse) ; enrôlement 2FA **sans QR
code** (recopie manuelle = abandon) ; import CSV inaccessible au clavier (`hidden`) ; **tutoiement résiduel** (dont
`common.error` « Réessaie » — le toast le plus affiché) ; pluriels « (s) » au lieu de la syntaxe pipe ; cloche sans
`aria-live` ; enums bruts affichés (admin mailboxStatus, provider) ; « Envoyer » qui disparaît sans explication ;
dashboard mobile `grid-cols-8` ; deep-link perdu au login.

**Tests** : 3 rate-limiter listeners sans test ; ~15 handlers de commande sans test d'application (couverts
indirectement) ; `phpunit.dist.xml` sans `date.timezone=UTC` (NotificationProjectionTest flaky local) ; câblage
`ProductionConfigGuard` (`when@prod`) non testé.

---

## Plan de remédiation proposé (lots)

- **Lot A — P0 cycle de vie du compte** (bloqueur V2.1) : renvoi de vérification (async) + message login dédié +
  normalisation email + action admin reset-2FA. Le strict nécessaire avant ouverture.
- **Lot B — durcissement sécu 2FA/comptes** : révocation sessions à l'activation 2FA, chiffrement du secret TOTP,
  codes de secours ≥8 octets, disable exige OTP, 2FA obligatoire admin, mailer async (tue l'oracle), HSTS, 500→422/404,
  race inscription→409, audit du reset.
- **Lot C — perf/DB** : policies RLS sargables (+migration), rétention notification/audit/reset, index partiel relance
  + rattrapage/filtre statut du tick, checklist runtime prod.
- **Lot D — robustesse front** : `queryClient.clear()` au logout, clé i18n + test, états d'erreur homogènes, QR code 2FA.
- **Lot E — tests** : assertEmail + jeton bout-en-bout, unitaires TotpService/Signer/Health, seuils par fichier,
  `date.timezone=UTC`, E2E account-lifecycle + admin, listeners rate-limit.
- **Lot F — docs** : resync des 6 docs de tête + ADR-0026 (admin/owner) + ADR-0027 (2FA) + ADR-0028 (notifications) +
  ADR-0029 (vérif email sans état) + READMEs contextes + commit des éditions plan/TODO en attente.
- **Lot G — UX polish** : garde « Contacter » Aujourd'hui, import CSV clavier, passe de vouvoiement, pluriels pipe,
  aria-live cloche, enums traduits, hint « Envoyer », dashboard mobile.

Cible : P0 (Lots A+B+E-partiel) soldés AVANT toute ouverture publique ; le reste ≥ 9/10 par axe comme d'habitude.
