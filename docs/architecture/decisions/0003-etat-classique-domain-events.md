# 0003 — État classique + domain events (pas d'event sourcing)

- **Statut** : Accepté
- **Date** : 2026-07-11

## Contexte
Le domaine est riche en événements (contact, relance, réponse, changement de statut). Se pose la question de l'event sourcing.

## Décision
**État classique** persisté par Doctrine (état courant des agrégats). Les **domain events** servent au découplage (projections, notifications), **pas** de source de vérité. **Pas d'event sourcing.** Le **journal d'Interactions** est une **projection** append-only alimentée par ces events, ce qui fournit l'historique/audit. Fiabilité assurée par un **transactional outbox** (events persistés dans la transaction de l'agrégat, relayés après commit).

## Conséquences
- ✅ Simple, bien outillé (Doctrine + Messenger), suffisant.
- ✅ Historique conservé via le journal d'Interactions.
- ✅ Découplage propre cœur ↔ effets de bord.
- ❌ Pas de « rejeu » complet ni de reconstruction d'état depuis un flux d'events (non requis).
- ⚠️ L'outbox doit garantir zéro perte d'event entre commit et publication.
