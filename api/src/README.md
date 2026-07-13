# `src/` — organisation par bounded context

Chaque contexte suit les 3 couches hexagonales (`Domain` / `Application` / `Infrastructure`).
Sens des dépendances : **Infrastructure → Application → Domain**. Le `Domain` est du PHP pur.

| Dossier | Contexte | Statut |
|---|---|---|
| `Shared/` | Kernel partagé (bus CQRS, TenantId, AggregateRoot, tenancy) | ✅ posé |
| `Prospecting/` | Core — pipeline, pistes, relances | 🟡 tranche `Lead` de référence posée |
| `Account/` | Comptes, tenancy, profil, auth | ✅ auth JWT+refresh, tenancy, user |
| `Directory/` | Répertoire — organisations & contacts | ✅ livré (M1.1 : CRUD, import CSV, read models) |
| `Drafting/` | Rédaction assistée (IA) + modèles | ⬜ à peupler |
| `Mailbox/` | Passerelle email (OAuth Gmail/Outlook) | ⬜ à peupler |
| `Sourcing/` | Ingestion d'annonces (M3) | ⬜ à peupler |

Voir `docs/architecture/OVERVIEW.md` et `docs/architecture/DOMAIN-MODEL.md`.
