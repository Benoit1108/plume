# 0012 — Collections de l'agrégat Organization en JSONB

- **Statut** : Accepté
- **Date** : 2026-07-13

## Contexte

La note de conception M1.1 prévoyait de persister les `Contact` de l'agrégat
`Organization` en table enfant (one-to-many, `orphanRemoval`). À l'implémentation,
ce mapping imposait soit des annotations ORM dans le domaine, soit un modèle de
persistance dédié — deux entorses au domaine pur pour une collection qui, par
définition d'agrégat, est **chargée et sauvée en bloc avec sa racine**.

## Décision

Les collections internes de l'agrégat (`contacts`, `workingLanguages`, `segments`)
sont persistées en **colonnes JSONB** sur la ligne de la racine, via des types DBAL
dédiés (`ContactCollectionType`, …) qui convertissent VO/entités ↔ JSON.

Piège connu et assumé : Doctrine ne détecte pas la mutation en place d'un objet
dans une collection JSON — `updateContact` **remplace** l'élément par une nouvelle
instance (commentaire dans l'agrégat).

## Conséquences

- ✅ Domaine strictement pur ; cohérence transactionnelle triviale (une ligne) ;
  sémantique d'agrégat respectée (pas d'accès aux contacts hors racine).
- ✅ JSONB (PG 17) : indexation GIN possible plus tard si des recherches dans les
  contacts deviennent nécessaires.
- ⚠️ Pas de requête SQL directe sur les contacts ni d'intégrité référentielle en
  base — acceptable tant que le contact vit exclusivement dans son organisation.
  Si un besoin de requêtage lourd apparaît (M3+), la réponse est une **projection
  dédiée** (read model), pas un retour au one-to-many.
- La note `docs/design/M1.1-repertoire.md` reste le reflet de l'intention initiale ;
  le présent ADR trace le revirement.
