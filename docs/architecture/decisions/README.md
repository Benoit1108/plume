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
| [0012](0012-collections-agregat-jsonb.md) | Collections d'agrégat en JSONB (contacts, relances) | Accepté |
| [0013](0013-read-models-v1.md) | Read models V1 : SQL direct fail-closed, seul le journal est projeté | Accepté |
| [0014](0014-integration-ia.md) | Intégration IA : ACL Claude, canned par défaut, coûts bornés, RGPD sous-traitant | Accepté |
| [0015](0015-import-csv.md) | Import CSV : bornes, dédoublonnage par nom, rapport | Accepté |
| [0016](0016-chiffrement-tokens-oauth.md) | Chiffrement des tokens OAuth au repos (sodium, clé dédiée, fail-fast prod) | Accepté |
| [0017](0017-captation-reponses.md) | Captation des réponses : polling minimisé sur nos fils, recordReply idempotent | Accepté |
| [0018](0018-auth-cookies-httponly.md) | Auth par cookies httpOnly (SPA même-origine, amende 0006) | Accepté |
| [0019](0019-adaptateurs-par-fournisseur.md) | Adaptateurs email par fournisseur (3 ports + registres) | Accepté |
| [0020](0020-contexte-sourcing.md) | Contexte Sourcing & file de tri (candidate → piste, promotion par gateway, tri humain) | Accepté |
| [0021](0021-dedoublonnage-sourcing.md) | Dédoublonnage des annonces (dedupHash + suggestion exacte, fusion humaine) | Accepté |
| [0022](0022-dettes-architecture-v2.md) | Dettes d'architecture à trancher en V2 (revue fin M3) | Proposé |
| [0023](0023-rls-multi-tenant.md) | Row-Level Security multi-tenant (rôle runtime dédié, var de session, fail-closed) | Accepté |
| [0024](0024-spa-ssr-false.md) | Front en SPA (ssr:false), serveur Nitro conservé pour le proxy (cookies) | Accepté |
