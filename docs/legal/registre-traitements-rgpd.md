# Registre des activités de traitement (Art. 30 RGPD) — TRAME

> ⚠️ **TRAME À VALIDER JURIDIQUEMENT.** Ce document est une **coquille technique** produite à partir de
> l'architecture réelle de Plume, destinée à faire gagner du temps. Il **ne constitue pas un conseil
> juridique** et doit être **relu et complété par un professionnel** (juriste / DPO), en particulier sur
> les bases légales, les durées de conservation, les transferts hors UE et le partage des rôles
> responsable/sous-traitant. Les points à trancher/renseigner par Benoit sont marqués 🟦.

- **Éditeur / opérateur du service** : 🟦 _identité, statut (micro-entreprise ?), SIRET, adresse, contact._
- **Référent RGPD** : 🟦 _nom + email de contact (pas de DPO obligatoire a priori pour une micro-entreprise, mais un point de contact est nécessaire)._
- **Date de la version** : 🟦 _à dater à la validation._

## Rôles — une double casquette à clarifier

Plume est un SaaS mono-tenant (**1 compte = 1 traductrice = 1 tenant**, cf.
[ADR-0025](../architecture/decisions/0025-rgpd-suppression-export.md)). L'opérateur porte **deux rôles
distincts** selon la donnée :

| Donnée | Rôle de l'opérateur (Plume) | Responsable de traitement |
|--------|------------------------------|---------------------------|
| **Compte utilisateur** (la traductrice : identifiants, sécurité, profil) | **Responsable de traitement** | Plume |
| **Données de prospection** (les prospects de la traductrice : organisations, contacts, pistes, messages) | **Sous-traitant** | **La traductrice** (chaque cliente) |

> 🟦 Ce partage doit être confirmé juridiquement. Conséquence pratique : pour les données de prospection,
> c'est **la traductrice** qui doit disposer d'une base légale valable (voir plus bas) ; Plume agit **sur
> ses instructions** (→ cf. le [DPA](DPA-sous-traitance.md)).

## Traitement n°1 — Gestion des comptes & sécurité *(Plume = responsable)*

- **Finalités** : création/authentification de compte, sécurité (2FA, sessions), facturation d'accès
  (à venir, V2.2), support, obligations légales.
- **Personnes concernées** : les traductrices titulaires d'un compte.
- **Catégories de données** :
  - Identité/contact : email, prénom, nom d'affichage.
  - Authentification : **hash** du mot de passe (jamais en clair), **secret TOTP** _(🟦 dette : pas encore
    chiffré au repos — à corriger, cf. [ADR-0027](../architecture/decisions/0027-2fa-totp.md))_, **codes de
    secours** (empreintes sha256), jetons de session (refresh tokens), jetons de reset (empreintes sha256).
  - Profil : bio, spécialités, signature, objectif hebdomadaire, fuseau horaire.
- **Base légale** : exécution du contrat (CGU) ; intérêt légitime pour la sécurité ; obligation légale
  (le cas échéant, facturation).
- **Durées de conservation** : durée du contrat ; à la suppression → **désactivation immédiate + purge
  physique après 30 j** (délai de grâce, ADR-0025). Jetons de reset : jusqu'à expiration puis purge.
- **Destinataires** : l'opérateur ; hébergeur (sous-traitant ultérieur, 🟦) ; service d'emails
  transactionnels (🟦, si SMTP tiers).

## Traitement n°2 — CRM de prospection *(Plume = sous-traitant ; traductrice = responsable)*

- **Finalité** : permettre à la traductrice de gérer sa **prospection commerciale B2B** (pistes, relances,
  messages, suivi) et l'ingestion d'annonces.
- **Personnes concernées** : prospects de la traductrice — **contacts professionnels** (éditeurs, agences,
  labos audiovisuels…).
- **Catégories de données** :
  - Organisations : nom, type, site web, pays, langues de travail, segments.
  - Contacts : nom, email, téléphone, fonction.
  - Pistes : statut, priorité, paire de langues, origine, relances programmées.
  - Interactions : journal append-only (contacts, réponses, envois).
  - Messages : brouillons et emails **envoyés/reçus** via la passerelle (objet + corps).
  - Sourcing : annonces candidates + **brut d'annonce** conservé.
- **Base légale** : 🟦 **relève de la traductrice** (typiquement l'**intérêt légitime** en prospection B2B).
  À documenter côté traductrice (information des prospects, droit d'opposition — géré par « ne pas
  contacter »/opt-out déjà outillé).
- **Durées de conservation** : tant que le compte est actif ; **effacées à la purge** du compte (30 j).
  **Brut d'annonce** (`raw_alert`) : purgé **30 j** après tri. **Notifications** lues : **90 j**.
- **Destinataires / sous-traitants ultérieurs** : Anthropic (génération assistée), Google/Microsoft
  (envoi/lecture email), hébergeur (🟦). Cf. [DPA](DPA-sous-traitance.md) pour le détail.

## Traitement n°3 — Journalisation & sécurité opérationnelle *(Plume = responsable)*

- **Finalités** : traçabilité (journal d'audit hors-tenant : connexions/actions support/suppressions),
  logs applicatifs, prévention des abus (rate-limiting).
- **Données** : identifiant technique du tenant, email de l'acteur pour les **actions sensibles**
  (suppression, reset 2FA), horodatages, adresses IP (rate-limiters).
- **Base légale** : intérêt légitime (sécurité, preuve de conformité RGPD).
- **Durée** : 🟦 à définir — le **journal d'audit survit à la purge RGPD** (preuve d'effacement) ; fixer une
  durée bornée (ex. 1 à 3 ans) et une politique de rotation des logs.

## Transferts hors Union européenne

Trois sous-traitants ultérieurs sont établis **aux États-Unis** : **Anthropic**, **Google**, **Microsoft**.

- **Encadrement** : 🟦 vérifier pour chacun l'adhésion à l'**EU-US Data Privacy Framework** (DPF) et/ou la
  signature de **clauses contractuelles types (CCT)** + mesures complémentaires.
- **Minimisation** vers Anthropic : le nom du contact est **interpolé localement** et ne part pas à l'API
  (ADR-0014) ; génération « canned » (sans IA) par défaut.

## Mesures de sécurité (Art. 32) — état réel

- **Isolation multi-tenant fail-closed** : Row-Level Security PostgreSQL (rôle runtime non-propriétaire) +
  filtre applicatif, sur deux lignes de défense ([ADR-0023](../architecture/decisions/0023-rls-multi-tenant.md)).
- **Chiffrement des tokens OAuth au repos** (sodium, clé dédiée — [ADR-0016](../architecture/decisions/0016-chiffrement-tokens-oauth.md)).
- **Mots de passe hachés**, **2FA TOTP** + codes de secours, **cookies httpOnly** même-origine, HTTPS + HSTS.
- **Rate-limiting** par tenant/IP, secrets hors dépôt, fail-fast des secrets en production.
- **Droits des personnes outillés** : accès/**portabilité** (export ZIP JSON/CSV), **effacement** (suppression
  + purge 30 j + **révocation OAuth côté fournisseur**), rectification (édition).
- 🟦 **Dette de sécurité à solder** : chiffrement du **secret TOTP** au repos (tracé ADR-0027).

## Droits des personnes — comment ils sont exercés

| Droit | Mécanisme dans Plume |
|-------|----------------------|
| Accès / portabilité | Export ZIP (`/account/export`) — self-service |
| Effacement | Suppression de compte (self-service ou support) → purge 30 j + révocation OAuth |
| Rectification | Édition du profil / des données CRM |
| Opposition (prospects) | « Ne pas contacter » / opt-out (garde re-vérifiée à l'envoi) |
| Demande via le support | Back-office admin (tracé au journal d'audit) |
