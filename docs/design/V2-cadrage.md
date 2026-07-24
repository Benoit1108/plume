# Cadrage V2 — Plume, du tool privé au SaaS multi-utilisateurs

- **Statut** : proposé (à valider avant de détailler le premier jalon)
- **Point de départ** : V1 (M0→M3) livrée + phase **pré-V2 terminée et revue** (RLS multi-tenant
  fail-closed, mail réel, front SPA/TanStack, revue de santé 9/9/9/9). Le socle technique du
  multi-tenant est **déjà posé** — la V2 est surtout du produit + de l'ouverture, pas une refonte.
- **Périmètre V2 retenu (arbitrage Benoit)** : les 4 piliers — ouverture SaaS multi-utilisateurs,
  facturation/abonnements, backlog gardé (Point D + Futur + dettes ADR-0022 §3/§4/§5), enrichissement
  produit.

## Vision

Passer d'un **outil privé mono-utilisatrice** (la copine de Benoit) à un **SaaS que plusieurs
traductrices indépendantes peuvent utiliser** — inscription autonome, abonnement payant, isolation
garantie. La V1 reste pleinement utilisable pendant toute la V2 (pas de big-bang).

## Ce qui est DÉJÀ prêt (à ne pas refaire)

- **Isolation multi-tenant à deux lignes fail-closed** : SQLFilter applicatif + **RLS Postgres**
  (rôle runtime `plume_app`, ADR-0023). Le plus dur du multi-tenant est fait et testé.
- Auth JWT + refresh en **cookies httpOnly** (ADR-0018), claim `tenant_id` serveur.
- Passerelle email OAuth Gmail/Outlook, tokens chiffrés (ADR-0016).
- Front **SPA** découplé (ADR-0024) + types dérivés du contrat.
- Ports/adapters propres → ajouter billing/annuaire/etc. se branche sans toucher le domaine.

## Jalons proposés (séquencés)

### V2.0 — Prérequis & bascule multi-tenant *(fondation, avant toute ouverture publique)*
Rien de public ne peut sortir sans ça.
- **Point D (prérequis techniques)** : héberg. de production (VPS vs PaaS FR — à trancher),
  **apps OAuth réelles** (sortir du mode test Google/Microsoft : vérification/validation des apps),
  **nom définitif** du produit (« Plume » = code provisoire).
- **Durcir le multi-tenant pour le vrai multi-comptes** : lever les invariants V1 mono-utilisatrice
  (une seule boîte email/tenant → multi-boîtes ?), **`app_user` sous RLS** (aujourd'hui exclu — cf.
  ADR-0023 §4), reset tenant `kernel.request` **si** passage FrankenPHP worker-mode, revue des
  chemins « propriétaire » (scheduler) à l'échelle multi-tenant.
- **RGPD** : registre de traitement + **DPA** (sous-traitants : Anthropic, Google/Microsoft),
  politique de rétention, export/suppression des données d'un compte.
- **Dettes ADR-0022 §3/§4/§5** : §3 harmonisation des adaptateurs, §4 tables hors ORM, §5 charge
  inter-contextes (files Messenger par contexte) — à trancher ici, car elles pèsent à l'échelle SaaS.

### V2.1 — Ouverture des comptes (inscription & workspace)
- Inscription publique (création de compte + tenant + première boîte), vérification email.
- Modèle **workspace** : un tenant = une traductrice (ou une petite équipe ?) — **à décider**.
- Onboarding guidé (connexion boîte, premier flux, import annuaire).
- Gestion du compte (mot de passe déjà là ; ajout : suppression de compte RGPD).

### V2.2 — Abonnement SaaS (billing d'ACCÈS)
> À NE PAS confondre avec la facturation CLIENT de la traductrice (Futur, cf. ci-dessous).
- Plans + **quotas par plan** (génération IA, nb de pistes/flux, boîtes connectées).
- Paiement (**Stripe** probable — à trancher), portail d'abonnement, essais/gratuité.
- Application des quotas (les rate-limiters par tenant existants sont un point d'ancrage).

### V2.3 — Enrichissement produit
- **Pipeline configurable** (statuts personnalisables — ADR-0008 le prévoit en V2).
- **Séquences de relance** multi-étapes.
- **Annuaire pré-rempli** (éditeurs FR, labos AV via ATAA, agences).
- Complétions mail réel : parsers fins **ProZ / TranslatorsCafe**, envoi/réponse **Outlook** réel,
  dashboard enrichi (délais de réponse, valeur estimée, filtres période, export).

### Futur 💤 (post-V2, gardé obligatoire mais après)
- **Gestion de mission** (Core métier n°2) : volume, deadline, tarif, livrables ; Piste gagnée → Mission.
- **Facturation CLIENT** de la traductrice : devis, factures (mentions micro-entreprise art. 293 B),
  suivi paiement, **plafonds de CA**, export compta (`brick/money`).
- Enrichissement auto de contacts, aide à la négociation, réponses assistées.
- Application mobile (l'auth JWT le permet).

## Décisions produit / tech à trancher (avant de détailler V2.0)

1. **Hébergement de production** — VPS (Docker Compose/Swarm) vs PaaS FR (Scalingo/Clever Cloud) ?
   Impacte : FrankenPHP worker-mode (→ reset tenant), store des rate-limiters (Redis multi-instances),
   sauvegardes/chiffrement DB, où vit `MAILBOX_ENCRYPTION_KEY`.
2. **Modèle de compte** — 1 tenant = 1 personne, ou workspace multi-utilisateurs (rôles) ? (change
   `app_user`, l'auth, l'UI).
3. **Fournisseur de paiement** + modèle de prix (plans, gratuité, essai).
4. **Nom définitif** du produit (bloque le domaine, les apps OAuth, la marque).
5. **Validation des apps OAuth** Google/Microsoft (processus de vérification = délai à anticiper).
6. **RGPD** : as-tu besoin d'un DPO / d'un hébergeur certifié santé/HDS (non a priori) ? DPA à signer.

## Risques

- **Ouvrir trop tôt** : chaque décision de V2.0 (isolation `app_user`, hébergement, RGPD) est un
  prérequis dur — une ouverture publique avant = risque de fuite / non-conformité. D'où V2.0 en premier.
- **Deux « facturations »** confondues (abonnement SaaS vs facturation client) → séparées ici.
- **Scope tentaculaire** : les 4 piliers = plusieurs mois. Livrer par jalons (V2.0 → V2.3), chacun
  avec sa propre note de conception détaillée + revue de santé, comme la V1.

## Prochaine étape

Valider ce découpage + trancher les décisions produit ci-dessus (au moins 1, 2, 4 pour démarrer
V2.0). Ensuite : note de conception détaillée **V2.0** (prérequis + durcissement multi-tenant),
puis exécution incrémentale CI-verte, comme la V1.
