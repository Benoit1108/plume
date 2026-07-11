# Sourcing — Ingestion (Supporting, M3)

Parsing des alertes (ProZ, LinkedIn, TranslatorsCafe) et RSS → pistes candidates.

À peupler (M3) :
- `Domain/` : agrégat `CandidateLead` (file de tri), service `AlertParser` (Strategy par source).
- `Application/` : acceptation → dédoublonnage `Organization` + création `Lead`.
- `Infrastructure/` : lecture d'un label dédié (via Mailbox), conservation de l'email brut.
