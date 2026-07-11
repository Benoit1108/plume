# `src/` — organisation par bounded context

Chaque contexte suit les 3 couches hexagonales (`Domain` / `Application` / `Infrastructure`).
Sens des dépendances : **Infrastructure → Application → Domain**. Le `Domain` est du PHP pur.

| Dossier | Contexte | Statut M0 |
|---|---|---|
| `Shared/` | Kernel partagé (bus CQRS, TenantId, AggregateRoot, tenancy) | ✅ posé |
| `Prospecting/` | Core — pipeline, pistes, relances | 🟡 tranche `Lead` de référence posée |
| `Account/` | Comptes, tenancy, profil, auth | ⬜ à peupler |
| `Directory/` | Répertoire — organisations & contacts | ⬜ à peupler |
| `Drafting/` | Rédaction assistée (IA) + modèles | ⬜ à peupler |
| `Mailbox/` | Passerelle email (OAuth Gmail/Outlook) | ⬜ à peupler |
| `Sourcing/` | Ingestion d'annonces (M3) | ⬜ à peupler |

Voir `docs/architecture/OVERVIEW.md` et `docs/architecture/DOMAIN-MODEL.md`.
