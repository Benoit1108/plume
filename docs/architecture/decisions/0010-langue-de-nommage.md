# 0010 — Langue de nommage : code en anglais, métier en français

- **Statut** : Accepté
- **Date** : 2026-07-11

## Contexte
L'*ubiquitous language* du domaine est en français (langue de l'utilisatrice et du métier : « Piste », « Relance »…). Se pose la langue des identifiants du code. Le français produit des identifiants accentués (`RéponseReçue`) peu propres, et s'écarte des conventions de l'écosystème.

## Décision
- **Code en anglais** : classes, méthodes, events, propriétés, dossiers de contexte. **Pas d'identifiants accentués.**
- **Métier en français** : UI, documentation, échanges avec l'utilisatrice.
- Le **glossaire** (`docs/GLOSSAIRE.md`) porte la **table de correspondance FR ↔ EN**, qui garde l'*ubiquitous language* intact conceptuellement (ex. Piste ↔ `Lead`, Relance ↔ `FollowUp`, RéponseReçue ↔ `ReplyReceived`).

## Conséquences
- ✅ Identifiants propres et conventionnels ; cohérence avec les termes techniques (déjà anglais).
- ✅ Facilite une éventuelle ouverture du produit à des contributeurs.
- ✅ L'alignement métier est préservé via la table de correspondance (obligatoire à jour).
- ⚠️ Toute nouvelle notion métier doit être ajoutée à la table FR↔EN avant d'être codée.
