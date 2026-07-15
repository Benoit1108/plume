# ADR-0020 — Contexte Sourcing & file de tri (candidate → piste)

- **Statut : Accepté** (2026-07-15 — M3 ; précise la note de cadrage M3)
- **Contexte** : M3 fait entrer des annonces (RSS, alertes email) dans l'application. Une
  annonce ingérée n'est **pas encore une piste** : elle doit être triée par la Traductrice.
  Où vit cet objet intermédiaire, et comment devient-il une Piste sans coupler les contextes ?

## Décision

1. **Nouveau contexte `Sourcing`** (*Supporting*) : agrégat racine **`CandidateLead`**
   (opportunité non triée) + support **`RawAlert`** (contenu brut conservé). La candidate
   n'est **pas** une `Lead` — elle vit dans Sourcing jusqu'au tri.
2. **`CandidateLead` immuable après tri** : machine à états `PENDING → ACCEPTED | REJECTED |
   MERGED`, garde contre le re-tri concurrent (leçon P0 fin M1 : l'agrégat se défend de la
   redélivrance / du double-clic).
3. **Pas d'acceptation automatique** — jamais en V1 : le tri est **toujours humain**
   (*accepter / rejeter / fusionner*). C'est la garde qualité **et** RGPD (rien n'entre au
   Répertoire/Pipeline sans décision).
4. **Promotion cross-contexte par gateway/commande** : à l'acceptation, Sourcing **demande**
   à Directory de créer/rattacher une Organisation et à Prospecting de créer une Piste, via
   des ports `DirectoryGateway` / `ProspectingGateway` (tenant explicite, pattern `LeadGateway`
   M1.4). **Jamais** d'accès direct à un agrégat d'un autre contexte.
5. **Ingestion derrière un port `AlertSource`** (Strategy par source — cf. ADR-0019 pour le
   registre), asynchrone (Scheduler/worker, pattern relève M2). La Passerelle email **notifie**
   Sourcing par event applicatif (alertes email) ; Sourcing ne l'appelle jamais.

## Conséquences

- ✅ Le cœur Prospection reste **inchangé** : une candidate acceptée passe par la création de
  Piste existante ; Sourcing est un satellite retirable.
- ✅ Tri humain = point de contrôle unique (qualité, opt-out, RGPD).
- ✅ Ajouter une source = un adaptateur `AlertSource`, sans toucher au tri ni à la promotion.
- ⚠️ La promotion orchestre **deux contextes** (Organisation puis Piste) : chaque commande a
  sa transaction ; on garantit l'ordre (org d'abord) et l'**idempotence** (ré-acceptation =
  no-op), un échec partiel est tracé et rejouable (la candidate reste `PENDING`).
