# TODO Benoit — ce que Claude ne peut pas faire

Tout ce qui suit dépend de toi (comptes tiers, contenu juridique, décisions produit, actions
manuelles). Le reste (code) est pris en charge par Claude. **Hébergement : volontairement mis de
côté, on tranchera plus tard.** Tests : **grosse recette manuelle plus tard** (guide fourni le moment venu).

## 🔴 À lancer TÔT (délais longs / bloquants pour l'ouverture publique)

- [ ] **Nom définitif du produit** (« Plume » = code provisoire). Bloque : domaine, marque, apps OAuth.
- [ ] **Validation des apps OAuth** — sortir du mode test, écran de consentement vérifié :
  - [ ] Google (Gmail) — process de vérification (peut prendre des semaines).
  - [ ] Microsoft (Outlook).
- [ ] **Contenu juridique** (Claude fournit les coquilles / trames, tu fournis/valides le fond) :
  - [ ] CGU (conditions générales d'utilisation) — coquille front à préciser.
  - [ ] Politique de confidentialité — coquille front à préciser.
  - [~] **Registre de traitement RGPD** — ✅ **trame fournie** ([`docs/legal/registre-traitements-rgpd.md`](../legal/registre-traitements-rgpd.md)) ; reste à **valider/compléter** (points 🟦 : identité éditeur, durées, transferts).
  - [~] **DPA** (accord de sous-traitance) — ✅ **trame fournie** ([`docs/legal/DPA-sous-traitance.md`](../legal/DPA-sous-traitance.md)) ; reste à **signer les DPA** des sous-traitants (Anthropic, Google, Microsoft, hébergeur) + vérifier DPF/CCT.

## 🟠 Décisions produit à trancher (quand on arrivera au jalon)

- [ ] **Paiement / abonnement** (V2.2) : fournisseur (**Stripe** probable ?), plans, prix, essai/gratuité.
- [x] **Auth admin** (back-office) : ✅ tranché 2026-07-28 — compte admin dédié hors tenant (CLI,
      ROLE_ADMIN, 2FA obligatoire à terme). Reste ⚖️ : impersonation support autorisée (RGPD) ?
- [ ] **Notifications** : fréquence des digests email (quotidien / hebdo / off par défaut ?).
- [ ] **Inscription** : captcha/anti-abus (lequel), double opt-in email (oui a priori).
- [ ] **Mes propositions** (cf. [plan directeur](../design/V2-plan-directeur.md) §« propositions ») :
      lesquelles retenir (observabilité, 2FA, journal d'audit, page de statut, etc.).

## 🟡 Comptes / accès à créer (au fil des jalons)

- [ ] Domaine + (plus tard) hébergeur.
- [ ] Stripe (si retenu) — compte + clés.
- [ ] Service d'emails transactionnels si SMTP tiers (⚖️ dépend hébergement) — ex. compte SMTP/API.
- [ ] Error-tracking si retenu (ex. Sentry) — compte + DSN.

## 🟢 À fournir à Claude quand disponible (non bloquant)

- [ ] **Échantillons d'emails réels ProZ / TranslatorsCafe** (pour les parsers fins — tu as LinkedIn, pas encore ceux-là).
- [ ] Sources de données pour l'**annuaire pré-rempli** (éditeurs FR, labos AV via ATAA, agences) si tu en as.
- [ ] Le moment venu : **recette manuelle** de bout en bout (Claude fournit un guide UAT complet).

## Déjà validé / réglé ✅
- Mail réel : **LinkedIn + Gmail** (relève d'alertes, envoi, réponse) — OK.
- Compte Gmail de test (OAuth scopes send+readonly, tokens chiffrés).
- V2.0 : RGPD suppression/export, isolation de charge, prépa déploiement (conteneur prod compile).
