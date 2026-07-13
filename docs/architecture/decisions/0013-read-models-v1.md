# 0013 — Read models V1 : vues SQL directes, fail-closed sur le tenant

- **Statut** : Accepté
- **Date** : 2026-07-13

## Contexte

La règle CLAUDE.md « les queries lisent des read models, pas les agrégats » était
violée en M1.1 : les handlers de query retournaient des entités Doctrine managées
(mutables, hydratation complète, pagination impossible proprement). La revue de
santé a requalifié ce raccourci en dette à rembourser avant M1.2.

## Décision

Le côté lecture du CQRS repose sur des **vues immuables** (`OrganizationView`,
`ContactView`, `OrganizationPage`) exposées par un **port applicatif** (`OrganizationSearch`)
et implémentées en Infrastructure en **SQL direct (DBAL)**, sans hydratation ORM.

Deux règles absolues :

1. **Fail-closed sur le tenant** : le SQLFilter Doctrine ne s'applique pas au DBAL ;
   chaque implémentation de read model exige un tenant en contexte et lève sinon —
   jamais de requête non scopée par accident.
2. Les vues sont **des DTO en lecture seule** : aucune entité managée ne franchit
   la couche Application côté query.

En V1, pas de tables de projection dédiées : les vues sont construites à la volée
depuis les tables d'écriture (volumes faibles). Les domain events émis par toutes
les mutations permettront d'introduire de vraies projections (M1.3+ : journal,
compteurs, dashboard) sans toucher aux ports.

## Conséquences

- ✅ Pagination réelle (LIMIT/OFFSET + COUNT) et paramètres documentés au contrat.
- ✅ Testabilité : ports mockables ; les tests fonctionnels couvrent l'isolation tenant.
- ⚠️ Deux chemins de lecture des mêmes tables (ORM en écriture, DBAL en lecture) :
  toute évolution de schéma doit mettre à jour les deux (le mapping des vues est
  centralisé dans `DoctrineOrganizationSearch`).
