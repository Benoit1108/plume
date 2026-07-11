# 0005 — Monorepo (api + app)

- **Statut** : Accepté
- **Date** : 2026-07-11

## Contexte
Backend Symfony + frontend Nuxt, développés par un seul dev.

## Décision
**Monorepo unique** : `api/` (Symfony) + `app/` (Nuxt), CI unifiée.

## Conséquences
- ✅ Une PR couvre back + front (changements transverses cohérents).
- ✅ Versionnage et CI unifiés, mise en route simplifiée.
- ⚠️ CI à configurer pour ne lancer que les jobs pertinents selon les chemins modifiés.
- ❌ Dépôts séparés écartés (coordination plus lourde pour un solo).
