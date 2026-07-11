# 0004 — API Platform (DTO + State Providers) sur bus CQRS

- **Statut** : Accepté
- **Date** : 2026-07-11

## Contexte
Front Nuxt (SPA) consommant une API. Tension connue entre le modèle « resource = entité » d'API Platform et la pureté DDD.

## Décision
**API Platform** en couche Infrastructure, mais :
- les **resources sont des DTO**, jamais les entités Doctrine ni les agrégats ;
- les **State Providers** (lecture) et **State Processors** (écriture) **délèguent au bus CQRS** (Messenger) ;
- CQRS léger : command bus synchrone/transactionnel, event bus asynchrone, queries sur read models.

## Conséquences
- ✅ Domaine préservé (jamais exposé directement).
- ✅ Gains gratuits : pagination, filtres, validation, négociation de contenu, doc OpenAPI.
- ✅ Vélocité de dev élevée pour un solo.
- ⚠️ Boilerplate DTO ↔ commande/query à maintenir.
- ❌ Alternative « API maison » (contrôleurs minces) écartée : trop de plomberie à recoder (doc, pagination, filtres).
