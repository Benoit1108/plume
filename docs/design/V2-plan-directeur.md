# V2 — Plan directeur (finir la V2 + tracer le futur)

- **But** : structurer TOUT ce qui reste pour finir la V2, en séparant ce que **Claude peut coder**
  de ce que **Benoit doit faire** (→ [`docs/ops/TODO-benoit.md`](../ops/TODO-benoit.md)).
- **Cadres posés par Benoit** : hébergement = tranché plus tard ; tests = **grosse recette finale**
  plus tard (les tests automatisés restent écrits par slice) ; mail = **LinkedIn + Gmail déjà validés
  en réel** (reste Outlook réel + parsers ProZ/TC, non bloquants).
- **Méthode inchangée** : par slices, CI verte par commit, revue de santé en fin de jalon, docs à jour.
- **Légende** : 🟩 Claude (code) · 🟦 Benoit (hors code) · ⚖️ décision produit à trancher.

---

## Séquence proposée (ajustable)

`V2.0 ✅` → `V2.1 socle d'ouverture` → `V2.1 inscription` → `Back-office` → `Centre de notifications`
→ `V2.2 abonnement` → `V2.3 enrichissement` → `Futur`.

Raison : le **socle d'ouverture** (mot de passe oublié, emails transactionnels…) et l'**inscription**
d'abord ; le **back-office** juste après (dès qu'il y a des comptes publics, il faut les gérer +
traiter les demandes RGPD) ; puis les notifications, l'abonnement, l'enrichissement.

---

## Jalon 0 — Clôture V2.0 ✅

- ✅ **Trames RGPD fournies** : [registre de traitement](../legal/registre-traitements-rgpd.md) +
  [DPA](../legal/DPA-sous-traitance.md) (coquilles ancrées sur l'archi réelle) ; 🟦 reste la
  validation/signature par Benoit (identité, hébergeur, DPF/CCT, relecture pro).
- ✅ **Révocation OAuth côté fournisseur à la suppression** (port `MailboxRevoker` appelé à la purge,
  best-effort — ADR-0025 amendé). Le **journal d'audit hors-tenant** de la suppression était déjà livré
  avec le back-office.

## Jalon V2.1a — Socle d'ouverture publique (fondations 100 % codables)

Prérequis techniques d'un vrai SaaS public, sans décision externe :
- 🟩 **Mot de passe oublié / réinitialisation** (flux complet + token à usage unique + email). *Gap
  actuel : seul le changement connecté existe.*
- 🟩 **Emails transactionnels** : abstraction d'envoi + gabarits FR/EN (vérification, bienvenue,
  reset, confirmation de suppression, **rappel avant purge** J-7). En dev : transport « capture »
  (pas d'envoi réel), en prod : à brancher (⚖️ SMTP/API — décision d'hébergement).
- 🟩 **Vérification d'email** (jeton signé, compte inactif tant que non vérifié).
- 🟩 **Endpoint de santé** `/health` (DB + Redis si activé) pour l'hébergeur/monitoring — *gap
  relevé en revue*.
- 🟩 **Pages légales** (coquilles CGU + politique de confidentialité + consentement RGPD à
  l'inscription) ; 🟦 Benoit fournit le **contenu juridique**.

## Jalon V2.1b — Inscription & onboarding

- 🟩 **Inscription publique** : endpoint public (hors tenant) → crée tenant + `app_user` + `Profile`,
  email de vérification, 1ʳᵉ connexion. Anti-abus (rate-limit, honeypot/captcha ⚖️).
- 🟩 **Onboarding guidé** : connexion de la boîte email, 1ᵉʳ flux d'annonces, import de l'annuaire,
  premiers réglages (objectif, présentation). États vides soignés.
- 🟩 Gestion du compte : suppression RGPD (fait), + « exporter mes données » (fait).

## Jalon V2.x — Back-office / Admin *(proposé par Benoit)*

Nouveau contexte `Admin` + rôle `ROLE_ADMIN` (auth séparée) :
- 🟩 **Comptes & tenants** : liste, recherche, détail, statut (actif / en suppression / suspendu).
- 🟩 **Demandes RGPD** : déclencher export / suppression pour le compte d'une utilisatrice (support).
- 🟩 **Abonnements** (quand V2.2 sera là) : voir plan, quotas, incidents de paiement.
- ✅ **Métriques produit (livré)** (sans PII) : comptes total/vérifiés/**actifs 30 j**, **inscriptions par
  semaine**, répartition des pistes par statut, totaux — `GET /admin/metrics`.
- ✅ **Statut système (livré)** : files Messenger, **âge du backlog** (worker bloqué ?), file `failed`,
  boîtes en erreur — `GET /admin/status` (distinct de la sonde publique `/health`).
- 🟩 **Impersonation support** (se connecter « en tant que », tracé) — ⚖️ à valider (sensible RGPD).
- 🟩 **Feature flags** simples.
- ✅ **Auth admin TRANCHÉE (2026-07-28)** : **compte admin dédié HORS tenant** (ROLE_ADMIN, créé par
  CLI — jamais par inscription), routes `/api/v1/admin/*` réservées, UI admin dans l'app existante ;
  2FA obligatoire dès que la slice 2FA existe.

## Jalon V2.x — Centre de notifications *(proposé par Benoit)*

Nouveau contexte `Notification` (in-app d'abord, digests email ensuite) :
- 🟩 **Cloche + liste** in-app (lu/non-lu, marquer tout lu), temps quasi-réel (polling puis SSE/WS ⚖️).
- 🟩 **Événements notifiés** : relance due aujourd'hui · réponse reçue · **boîte email déconnectée /
  en erreur** · nouveau candidat à trier · objectif hebdo (série) · (plus tard) quota bientôt atteint,
  incident de paiement.
- ✅ **Digest email (livré)** : préférence `digest_frequency` par tenant (NONE/DAILY/WEEKLY, **défaut
  DAILY** — Benoit peut changer le défaut/UX ou l'off-par-défaut ⚖️), tick quotidien, email bilingue sans
  PII. 🔭 Reste : préférences fines par type/canal (au-delà de la fréquence globale).
- Réutilise l'outbox d'events existant (projection Notification sur les domain events).

## Jalon V2.2 — Abonnement SaaS (billing d'ACCÈS)

- ⚖️🟦 Fournisseur de paiement (**Stripe** probable) + plans + prix + gratuité/essai.
- 🟩 Intégration billing (checkout, webhooks, portail client), **quotas par plan** appliqués
  (ancrage : rate-limiters par tenant existants), page d'abonnement.
- ⚠️ NE PAS confondre avec la **facturation CLIENT** de la traductrice (→ Futur).

## Jalon V2.3 — Enrichissement produit

- 🟩 **Pipeline configurable** (statuts personnalisables — ADR-0008 le prévoit).
- 🟩 **Séquences de relance** multi-étapes.
- 🟩 **Annuaire pré-rempli** (éditeurs FR, labos AV via ATAA, agences) — 🟦 sources de données.
- 🟩 **Parsers fins ProZ / TranslatorsCafe** — 🟦 Benoit fournit des **échantillons réels**.
- 🟩 **Outlook réel** (envoi/réponse) — validation en recette.
- 🟩 Dashboard enrichi : délais de réponse, valeur estimée, filtres de période, export.

---

## Propositions — arbitrage Benoit (2026-07-28)

**RETENUES pour la V2** (décidées) :
1. ✅ **Observabilité (LIVRÉ, ADR-0030)** : logs JSON + corrélation **tenant + request_id** (HTTP et
   worker), **error-tracking Sentry** prod-only/env-gated/sans PII (reste 🟦 fournir le `SENTRY_DSN`).
   *Reste : métriques produit (avec le back-office/monitoring).*
2. 🟩 **Journal d'audit** hors-tenant (connexions, suppressions, actions admin) — comble le trou
   RGPD noté à l'ADR-0025 + trace le back-office. *Va de pair avec le jalon back-office.*
3. 🟩 **2FA / TOTP + gestion des sessions** (« OTP » demandé par Benoit) — **slice de sécurité dédiée**
   dans V2.1 : enrôlement TOTP (app d'authentification), **codes de secours**, voir/révoquer ses
   sessions actives. Après le socle + l'inscription.
4. 🟩 **Page de statut publique** (uptime, 🟦 dépend hébergement) + **reprise de boîte email** :
   reconnexion guidée quand un token OAuth expire (lié au centre de notifications).

**Gardées mais plus tard / au fil de l'eau** (non prioritaires) :
5. 🟩 Sauvegardes DB auto + test de restauration (🟦 dépend hébergeur) · 🟩 a11y + responsive mobile
   web · 🟩 alertes de quota (avec V2.2) · 🟩 consentement RGPD minimal (léger, pas de tracking tiers).

## Dette technique tracée (ADR-0022, à rouvrir si besoin)
- §3 patrons d'adaptateurs · §4 tables hors ORM (doc) · §5 `RawAlert`/`rawRef`.
- Petits restes revues : garde-fou « Contacter » sur kanban/today, `setQueryData` kanban, dédup type
  `Segment` dérivé du contrat, nullabilité OpenAPI.

## Futur 💤 (post-V2)
- **Gestion de mission** (2ᵉ cœur métier) · **Facturation client** (devis/factures micro-entreprise,
  plafonds CA, export compta) · enrichissement auto de contacts / négociation assistée ·
  **application mobile** (auth JWT prête).
