# ADR-0021 — Dédoublonnage des annonces ingérées

- **Statut : Accepté** (2026-07-15 — M3 ; précise la note de cadrage M3 §4)
- **Contexte** : l'ingestion se répète (re-polling RSS, relèves email successives) et plusieurs
  sources décrivent la même opportunité. Il faut **ne jamais ré-ingérer** ni recréer une
  organisation déjà connue, **sans jamais fusionner à l'aveugle**.

## Décision

1. **Empreinte `dedupHash` sur `CandidateLead`** : normalisée = `source` + identifiant stable
   de l'annonce s'il existe (GUID RSS, ID de l'offre), sinon nom d'organisation **normalisé**
   + titre. À l'ingestion, une collision = **no-op tracé** (l'annonce n'entre pas deux fois).
2. **La candidate rejetée conserve son `dedupHash`** (anti-réapparition) même après purge du
   brut (`RawAlert`) — une annonce écartée ne revient pas hanter la file.
3. **Contre le Répertoire** (au tri) : suggestion de l'organisation existante la plus proche
   par **correspondance exacte normalisée** (nom — réutilise la normalisation d'unicité M1.1 —,
   domaine email/URL). **V1 = exact + suggestion**, pas de *fuzzy*/ML (différé V2). La décision
   de **fusion reste humaine** (*Fusionner* pointe une organisation désignée).
4. **Contacts** : dédoublonnage à l'acceptation par email **normalisé**, réutilisant les
   réflexes de l'import CSV (ADR-0015).

## Conséquences

- ✅ Zéro doublon involontaire à l'ingestion ; décisions de fusion **traçables et humaines**.
- ✅ Simple et prévisible : peu de faux positifs, comportement explicable.
- ⚠️ Les **quasi-doublons** (variantes orthographiques d'un même éditeur) échappent à l'exact →
  ils atterrissent comme candidates distinctes ; la Traductrice peut toujours fusionner à la
  main. Rapprochement approché (fuzzy) à réévaluer en V2 si le volume le justifie.
