# 0006 — Authentification JWT access + refresh

- **Statut** : Accepté
- **Date** : 2026-07-11

## Contexte
SPA Nuxt vers API Symfony, ambition SaaS et éventuel client mobile futur.

## Décision
**JWT access + refresh** (Lexik JWT + refresh tokens). Access token court, **refresh token en cookie httpOnly**, **rotation** des refresh tokens. `TenantId` porté par le token (support de la tenancy).

## Conséquences
- ✅ Stateless, adapté SPA + futur mobile.
- ✅ Le token véhicule le tenant → SQLFilter alimenté proprement.
- ⚠️ Plomberie : rotation, révocation, stockage sécurisé du refresh, gestion de l'expiration côté Nuxt.
- ❌ Sessions par cookie écartées (moins adaptées à un futur mobile / multi-client).
