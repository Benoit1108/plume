# Architecture Decision Records (ADR)

Chaque décision d'architecture structurante est tracée ici. Format : Statut · Contexte · Décision · Conséquences.
Ne pas contredire un ADR accepté sans en écrire un nouveau qui le remplace (statut « Remplacé par… »).

| N°   | Titre | Statut |
|------|-------|--------|
| [0001](0001-ddd-hexagonal-monolithe-modulaire.md) | DDD hexagonal, monolithe modulaire par contexte | Accepté |
| [0002](0002-multi-tenancy.md) | Multi-tenancy : base partagée + `tenant_id` + SQLFilter | Accepté |
| [0003](0003-etat-classique-domain-events.md) | État classique + domain events (pas d'event sourcing) | Accepté |
| [0004](0004-api-platform-cqrs.md) | API Platform (DTO + State Providers) sur bus CQRS | Accepté |
| [0005](0005-monorepo.md) | Monorepo (api + app) | Accepté |
| [0006](0006-auth-jwt.md) | Authentification JWT access + refresh | Accepté |
| [0007](0007-passerelle-email-oauth.md) | Passerelle email OAuth Gmail/Outlook, sans tracking d'ouverture | Accepté |
| [0008](0008-pipeline-opinione.md) | Pipeline opinioné figé en V1 | Accepté |
| [0009](0009-stack-runtime.md) | Stack technique & runtime local | Accepté |
| [0010](0010-langue-de-nommage.md) | Langue de nommage : code EN, métier FR | Accepté |
| [0011](0011-preoccupations-transverses.md) | Préoccupations transverses (i18n, thème, TZ, /v1…) | Accepté |
