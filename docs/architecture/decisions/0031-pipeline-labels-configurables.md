# ADR-0031 — Pipeline personnalisable : libellés d'étapes configurables (amende ADR-0008)

- **Statut** : Accepté (2026-07-30, V2.3).
- **Contexte** : [ADR-0008](0008-pipeline-opinione.md) a figé le pipeline en V1 (états sémantiques +
  transitions codées) et prévoyait « V2 : statuts personnalisables ». Or la **logique métier dépend des
  états sémantiques** : cadence de relance branchée sur `CONTACTED`/`FOLLOWED_UP`, réponse → `IN_DISCUSSION`,
  `WON`/`LOST` terminaux (taux de conversion/réponse), tableau « Aujourd'hui », notifications. Rendre les
  états **arbitraires** (transitions dynamiques) casserait tout cela — c'est une refonte du cœur, pas un
  incrément propre.

## Décision

La personnalisation V2.3 porte sur les **LIBELLÉS des étapes**, pas sur la machine à états :
- La traductrice peut **renommer** chaque stade selon son vocabulaire (« Test/Échantillon » → « Essai »,
  « À contacter » → « Prospects »…). Préférence par tenant `profile.pipeline_labels` (map `statut → libellé`,
  overrides uniquement ; absent = libellé i18n par défaut).
- **La machine à états reste STRICTEMENT FIXE** (mêmes états, mêmes transitions, mêmes invariants) : zéro
  risque pour la cadence, les métriques, le kanban, les notifications.
- Une **source unique** de libellé côté front (`useLeadLabels.statusLabel`) résout : override du profil →
  sinon i18n. Le back-office (vue cross-tenant) garde les libellés par défaut (les customs sont par tenant).

## Conséquences
- ✅ Vraie personnalisation visible (kanban, fiche, tableau de bord) sans toucher au domaine.
- ✅ Réversible et sûr : vider un libellé = retour au défaut ; un statut inconnu dans la map est ignoré.
- 🔀 **États/transitions arbitraires** = toujours reportés (nécessiteraient de découpler toute la logique
  métier des états sémantiques — hors périmètre V2.3, à rouvrir si un vrai besoin émerge).
- ⚠️ Les libellés custom sont **cosmétiques** : ils ne changent ni le comportement ni les exports/API
  (qui restent sur les codes de statut stables).
