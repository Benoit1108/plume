# 0008 — Pipeline opinioné figé en V1

- **Statut** : Accepté
- **Date** : 2026-07-11

## Contexte
Le pipeline de prospection peut être figé (opinioné) ou personnalisable par l'utilisatrice.

## Décision
Pipeline **opinioné et figé en V1**, statuts : `À_CONTACTER → CONTACTÉE → RELANCÉE → EN_DISCUSSION → TEST/ÉCHANTILLON → GAGNÉE`, plus `PERDUE` et `EN_PAUSE`. Le stade **Test/Échantillon** est spécifique au métier (agences & AV). La configurabilité des statuts est reportée en V2.

## Conséquences
- ✅ Machine à états simple, invariants clairs, kanban robuste.
- ✅ Modélisation DDD nettement simplifiée (statuts = VO, transitions codées).
- 🔀 V2 : statuts personnalisables (transitions dynamiques) — évolution prévue.

## Amendement (2026-07-23)
Ajout d'une transition **`CONTACTÉE → À_CONTACTER`** exposée comme action « Repasser à À contacter »
(commande `ReturnLeadToContact`, event `LeadReturnedToContact`). Ce n'est **pas** un nouveau stade
ni un assouplissement du pipeline en avant : c'est une **correction** d'un « Contacter » cliqué par
erreur (elle annule la relance auto planifiée et efface la date de contact). Cohérent avec le fait
que `À_CONTACTER` était déjà une cible atteignable (depuis `EN_PAUSE`).
