# `src/` — organisation par bounded context

Chaque contexte suit les 3 couches hexagonales (`Domain` / `Application` / `Infrastructure`).
Sens des dépendances : **Infrastructure → Application → Domain**. Le `Domain` est du PHP pur.

| Dossier | Contexte | Statut |
|---|---|---|
| `Shared/` | Kernel partagé (bus CQRS, TenantId, AggregateRoot, tenancy, HydratesRows) | ✅ posé |
| `Prospecting/` | Core — pipeline `Lead`, relances (`FollowUp`), journal, « Aujourd'hui », tableau de bord | ✅ livré (M1.2/M1.3/M1.5) |
| `Account/` | Comptes, tenancy, auth, profil (`Profile` : objectif hebdo + présentation) | ✅ livré |
| `Directory/` | Répertoire — organisations & contacts, import CSV | ✅ livré (M1.1) |
| `Drafting/` | Rédaction assistée — `Draft`/`Template`, port `MessageGenerator` (canned/Claude) | ✅ livré (M1.4) |
| `Mailbox/` | Passerelle email — `ConnectedMailbox`/`OutboundMessage`, OAuth Gmail+Outlook, envoi, captation des réponses | ✅ livré (M2) |
| `Sourcing/` | Ingestion d'annonces | ⬜ à peupler (M3) |

Les frontières inter-contextes sont **outillées** : `deptrac.yaml` (couches) et
`deptrac-contexts.yaml` (le cœur d'un contexte — Domain+Application — ne dépend
que de lui-même et de `Shared`). L'intégration entre contextes passe par **ID**,
**port** (ex. `LeadGateway`) ou **domain event** consommé en Infrastructure
(ex. le journal ← `DraftGenerated`, cf. ADR-0003 amendé).

Voir `docs/architecture/OVERVIEW.md` et `docs/architecture/DOMAIN-MODEL.md`.
