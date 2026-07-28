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

## Jalon 0 — Clôture V2.0 (presque fini)

- 🟦 **Registre de traitement RGPD + DPA** (Claude fournit une trame ; Benoit valide/signe).
- 🟩 *(optionnel, backlog ADR-0025)* révocation OAuth côté fournisseur à la suppression ; journal
  d'audit hors-tenant de la suppression.

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
- 🟩 **Métriques produit** (respectueuses vie privée) : nb comptes, actifs, pistes créées, etc.
- 🟩 **Statut système** : santé workers/scheduler, files Messenger, boîtes en erreur.
- 🟩 **Impersonation support** (se connecter « en tant que », tracé) — ⚖️ à valider (sensible RGPD).
- 🟩 **Feature flags** simples.
- ⚖️ Modèle d'auth admin (compte admin dédié hors tenant ? MFA ?).

## Jalon V2.x — Centre de notifications *(proposé par Benoit)*

Nouveau contexte `Notification` (in-app d'abord, digests email ensuite) :
- 🟩 **Cloche + liste** in-app (lu/non-lu, marquer tout lu), temps quasi-réel (polling puis SSE/WS ⚖️).
- 🟩 **Événements notifiés** : relance due aujourd'hui · réponse reçue · **boîte email déconnectée /
  en erreur** · nouveau candidat à trier · objectif hebdo (série) · (plus tard) quota bientôt atteint,
  incident de paiement.
- 🟩 **Préférences** par canal/type (in-app / email) + **digest email** (quotidien/hebdo, ⚖️ fréquence).
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

## Mes propositions supplémentaires (force de proposition)

À intégrer où c'est pertinent — dites lesquelles retenir :
1. 🟩 **Observabilité** : logs structurés JSON + corrélation par tenant, **error-tracking** (Sentry
   self-hostable ⚖️), métriques. *Indispensable dès qu'il y a de vrais utilisateurs.*
2. 🟩 **Sauvegardes DB** automatisées + chiffrées + test de restauration (checklist ops). 🟦 dépend de l'hébergeur.
3. 🟩 **Journal d'audit** hors-tenant (connexions, suppressions, actions admin) — comble le trou
   RGPD noté à l'ADR-0025 + trace les actions du back-office.
4. 🟩 **2FA / TOTP** (option de sécurité du compte) — *Futur, mais à prévoir tôt dans le modèle auth.*
5. 🟩 **Gestion des sessions** (voir/révoquer ses sessions actives) — complète l'auth.
6. 🟩 **A11y + responsive mobile web** : passe d'audit avant l'app native (WCAG, navigation clavier).
7. 🟩 **Page de statut publique** (uptime) — confiance SaaS. 🟦 dépend hébergement.
8. 🟩 **Alertes de quota / d'usage** (ancrées sur les notifications + V2.2).
9. 🟩 **Reprise sur erreur boîte email** : reconnexion guidée quand un token OAuth expire (lié aux notifs).
10. 🟩 **Cookie/consent RGPD** minimal (pas de tracking tiers → léger).

## Dette technique tracée (ADR-0022, à rouvrir si besoin)
- §3 patrons d'adaptateurs · §4 tables hors ORM (doc) · §5 `RawAlert`/`rawRef`.
- Petits restes revues : garde-fou « Contacter » sur kanban/today, `setQueryData` kanban, dédup type
  `Segment` dérivé du contrat, nullabilité OpenAPI.

## Futur 💤 (post-V2)
- **Gestion de mission** (2ᵉ cœur métier) · **Facturation client** (devis/factures micro-entreprise,
  plafonds CA, export compta) · enrichissement auto de contacts / négociation assistée ·
  **application mobile** (auth JWT prête).
