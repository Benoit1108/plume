# ADR-0034 — Compte de démonstration éphémère (vitrine V2)

- **Statut** : accepté (2026-08-03).
- **Contexte** : la vitrine publique (`/`) propose « Essayer la démo » pour laisser un visiteur
  explorer Plume sans inscription. Il faut une session utilisable immédiatement, isolée, sans risque
  de coût ni d'effet externe, et qui ne survit pas. Décision cadrée avec le porteur : démo ÉPHÉMÈRE
  par visiteur, vitrine intégrée à l'app.

## Décisions

- **Endpoint public `POST /api/v1/demo`** : crée à la volée un **tenant isolé** + un compte
  `ROLE_DEMO`, le pré-remplit de données FICTIVES (`DemoSeeder` : organisations / pistes /
  interactions) et **connecte sans mot de passe** (le success handler lexik pose les cookies JWT).
  Le mot de passe est aléatoire et jamais communiqué : la seule voie d'entrée est ce endpoint.
- **Éphémère** : colonne hors-ORM `app_user.demo_expires_at` (TTL 2 h). Un tick horaire
  (`PurgeExpiredDemosTick`) **réutilise la purge RGPD** (`PurgeAccount`) → effacement atomique du
  tenant + `app_user`, mêmes garanties que la suppression de compte.
- **Capacités BRIDÉES** (l'endpoint est ouvert à un visiteur anonyme — le TTL ne suffit pas comme
  garde-fou) :
  - **Génération IA payante refusée** → repli sur le générateur gratuit `canned`. Neutralisée par le
    port `AiGenerationPolicy` (impl `TenantAiGenerationPolicy`), consulté par le sélecteur : un
    tenant de démo ne déclenche jamais d'appel facturé, même `ANTHROPIC_API_KEY` présente. La démo
    reste pleinement fonctionnelle (elle montre la rédaction assistée, en version gratuite).
  - **Connexion de boîte réelle + envoi d'emails réels refusés** (403 `demo_restricted`) par
    `DemoRestrictionListener` (kernel.controller) : `/mailbox/oauth/start`, `/mailbox/connect`,
    `/mailbox/fetch-replies`, `/mailbox/fetch-alerts`, `/drafts/{id}/send`.
- **Anti-abus** : débit limité **par IP** (5/h) **et** **plafond GLOBAL** de démos actives (503
  `demo_unavailable` au-delà, le temps que la purge horaire libère) — le seul débit par IP est
  contournable par un pool d'IP et, sans Redis, il est par-hôte.

## Conséquences

- **Écriture autorisée** en démo : le tenant n'a pas de `subscription` → « grandfathered » → la garde
  lecture seule (ADR-0033) ne le bride pas. C'est voulu : une démo doit pouvoir créer des données.
  Seules les capacités à **coût** ou **effet externe** ci-dessus sont fermées.
- **`app_user` reste hors RLS** (ADR-0023) : le comptage du plafond et le marquage `demo_expires_at`
  se font par requêtes DBAL filtrées explicitement.
- **Sécurité** : décision issue de la revue de santé du 2026-08-03 (finding S-P1 — une session
  `ROLE_DEMO` non bridée pouvait consommer le budget IA global partagé et envoyer de vrais emails).
