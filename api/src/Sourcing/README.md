# Sourcing — Ingestion d'annonces (Supporting)

De l'annonce captée à la Piste : file de tri, dédoublonnage, promotion cross-contexte.

## Livré (M3.0 — socle + file de tri)

- **`Domain/CandidateLead/`** : agrégat `CandidateLead` (annonce candidate), enum `Source`
  (`PROZ`/`LINKEDIN`/`TRANSLATORSCAFE`/`RSS`/`MANUAL`), `CandidateStatus`
  (`PENDING → ACCEPTED | MERGED | REJECTED`), `Dedup` (empreinte sha256 normalisée), events
  `CandidateLeadIngested`/`Accepted`/`Merged`/`Rejected`. **Immuable une fois triée**
  (`CandidateAlreadyTriaged`, 409).
- **`Application/`** : commandes `IngestCandidate` (no-op si doublon), `AcceptCandidate`
  (nouvelle organisation + piste), `MergeCandidate` (organisation existante ; rattache la
  piste active si elle existe, sinon en crée une), `RejectCandidate`. Promotion via les
  **ports** `DirectoryGateway` + `ProspectingGateway` (jamais d'accès direct à un agrégat
  étranger). Read model `CandidateQueue` (file « À trier »).
- **`Infrastructure/`** : repository Doctrine (SQLFilter tenant, fail-closed), gateways sur
  `command.bus` (une seule transaction partagée, bus imbriqué), ressource API Platform
  `/candidate-leads` (+ `/{id}/accept`, `/merge`, `/reject`).

Sûreté de la promotion : la garde `PENDING` est évaluée **avant** les gateways → jamais
d'organisation/piste orpheline (ADR-0020). Dédoublonnage à l'ingestion : ADR-0021.

## Livré (M3.1a — moteur d'ingestion RSS)

- **`Domain/RawAlert/`** : `RawAlert` (brut d'une annonce, conservé pour audit/reprocessing),
  référencé par `CandidateLead::rawRef`.
- **`Application/Source/`** : port `AlertSource` (Strategy) + `ParsedAlert` (DTO) ; commande
  `PollAlertSource` (relève → `IngestCandidate` par item, dédoublonnage par `externalId`).
- **`Infrastructure/Source/`** : `RssAlertSource` (HttpClient, parsing best-effort — item
  malformé ignoré), `FakeAlertSource` (démo sans réseau, défaut), `AlertSourceFactory` (réel si
  `SOURCING_RSS_FEED_URL`, factice sinon). `RawAlert` écrit en DBAL (tenant explicite).
- **API** : `POST /sources/poll` (relève manuelle, tenant courant — I/O sync comme la relève M2).

## Reste

- **M3.1b** — gestion des flux (`AlertFeed` + CRUD + écran Réglages « Sources »), Scheduler auto
  (fan-out tous tenants), purge planifiée du brut (D6).
- **M3.2** — alertes email : lecture d'un label dédié (via Mailbox), conservation de l'email brut.
