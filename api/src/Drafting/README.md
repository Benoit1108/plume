# Drafting — Rédaction assistée (Supporting)

Génération IA de mails/lettres + modèles.

À peupler (M1) :
- `Application/` : port `MessageGenerator` (interface), commande `GenerateDraft`.
- `Domain/` : agrégat `Template`, value objects (langue, ton par segment).
- `Infrastructure/` : adapter Claude (ACL) réalisant `MessageGenerator`, appel asynchrone via Messenger.
