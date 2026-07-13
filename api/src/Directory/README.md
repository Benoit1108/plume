# Directory — Répertoire (Supporting)

Organisations (maisons d'édition, labos AV, agences) et Contacts. **Livré en M1.1.**

- `Domain/` : agrégat `Organization` contenant ses `Contact` ; invariants : email de
  contact unique par organisation, nom d'organisation unique par tenant (ADR + index),
  `doNotContact` réversible et tracé ; un event par mutation.
- `Application/` : commandes CRUD + `OrganizationImporter` (import CSV, 1 ligne = 1
  transaction) ; queries → read models (`OrganizationView`, port `OrganizationSearch`).
- `Infrastructure/` : repository Doctrine (mapping XML, collections JSONB — ADR-0012),
  read model SQL direct fail-closed (ADR-0013), resources API Platform (DTO),
  parseur CSV tolérant.
