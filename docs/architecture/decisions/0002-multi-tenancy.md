# 0002 — Multi-tenancy : base partagée + `tenant_id` + SQLFilter

- **Statut** : Accepté
- **Date** : 2026-07-11

## Contexte
Le projet est pensé comme un SaaS (revente possible à d'autres traducteurs). En V1, une seule utilisatrice, mais l'architecture doit être multi-tenant dès le départ pour éviter une refonte.

## Décision
**Base unique, schéma partagé, discriminant `tenant_id`** sur les tables tenant. Isolation par **Doctrine `SQLFilter`** activé à chaque requête, `TenantId` extrait du token JWT. La tenancy est une **préoccupation d'infrastructure** : le domaine l'ignore. Une Traductrice = un tenant. Pas d'inscription publique ni de billing en V1.

## Conséquences
- ✅ Simple à opérer, migrations uniques.
- ✅ Domaine non pollué par la tenancy.
- ✅ Évolution vers schéma-par-tenant / DB-par-tenant possible si un gros client l'exige.
- ⚠️ Le `SQLFilter` doit être infaillible : une requête non filtrée = fuite inter-tenant. Tests d'isolation obligatoires.
