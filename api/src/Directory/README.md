# Directory — Répertoire (Supporting)

Organisations (maisons d'édition, labos AV, agences) et Contacts.

À peupler (M1) :
- `Domain/` : agrégat `Organization` contenant ses `Contact` (entités), invariants (email unique par org, tenant cohérent).
- `Application/` : commandes CRUD, import CSV.
- `Infrastructure/` : repository Doctrine, resources API Platform (DTO).
