# ADR-0033 — Abonnement SaaS (V2.2)

- **Statut** : accepté (2026-08-03), en cours de livraison par slices.
- **Contexte** : ouvrir Plume en abonnement payant. Cadré avec le porteur.

## Décisions (cadrage)

- **Fournisseur** : **Stripe** (démarrage FR ; franchise de TVA → simple). L'intégration réelle est
  isolée derrière un port + adaptateur, avec un **adaptateur factice** sans clés (dev/CI/E2E) — même
  patron que Gmail/Outlook. *(Slice 2.)*
- **Offre** : **1 plan payant** + **essai gratuit 14 j SANS carte** (friction minimale ; relance en
  fin d'essai). Réversible (carte à l'inscription = réglage Checkout + flag).
- **Fin d'essai / impayé / résiliation** → **LECTURE SEULE** : les données restent consultables,
  exportables (RGPD) et le compte gérable ; seules les écritures produit sont bloquées. Ré-abonnement
  = un clic.
- **Compte offert** (ex. proche) : statut `comped` posé au back-office, hors Stripe. *(Slice 4.)*
- **Ordre** : plomberie d'abord (socle + garde), puis Stripe, puis UI, puis back-office billing.

## Mécanique (slice 1 — socle + garde)

- Contexte `Billing`. Table **`subscription`** (une par tenant) : statut
  (`trialing`/`active`/`past_due`/`canceled`/`comped`) + `trial_ends_at` + colonnes Stripe (prêtes).
  **HORS RLS** (comme `app_user`) : écrite à l'inscription publique (sans tenant) et par les webhooks
  Stripe → toujours filtrée par `tenant_id` EXPLICITE côté code (exclusion assumée, RlsCoverageTest).
- Port `Subscriptions` (`startTrial`, `isEntitled`) + adaptateur DBAL. **Essai démarré à l'inscription**
  (idempotent).
- **Droit d'accès** : `active`/`comped` → oui ; `trialing` → tant que non expiré ; `past_due`/
  `canceled` → non. **Aucun abonnement → OUI** (comptes antérieurs à la facturation = grandfathered ;
  n'enferme jamais les comptes existants). Fail-open sur statut inconnu.
- **Garde « lecture seule »** : listener sur `kernel.controller` (après l'auth JWT qui pose le tenant).
  Bloque les méthodes mutantes (402 `subscription_required`) hors **liste blanche** (auth, inscription,
  `/account`, `/profile`, `/admin`, `/billing`). GET toujours permis.

## Conséquences

- ✅ Modèle d'accès clair et **non régressif** (grandfathered) ; testé (règle d'accès unitaire +
  garde HTTP + essai). Prêt pour Stripe (colonnes + port).
- ⚠️ `subscription` hors RLS : la rigueur repose sur le **filtrage tenant explicite** (jamais de
  requête non scopée) — documenté et couvert par l'exclusion assumée du RlsCoverageTest.
- 🔭 Suite : Stripe réel (checkout/webhooks/portail), UI abonnement + bandeaux lecture seule (gère le
  402), back-office billing (abonnés/MRR/impayés + bascule `comped`), site vitrine + compte démo.
