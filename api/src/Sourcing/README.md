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

## Livré (M3.1 — ingestion RSS complète)

- **`Domain/RawAlert/`** : `RawAlert` (brut d'une annonce, conservé pour audit/reprocessing),
  référencé par `CandidateLead::rawRef`.
- **`Domain/AlertFeed/`** : agrégat `AlertFeed` (flux RSS configuré par tenant : source, url,
  label, actif) + events.
- **`Application/`** : port `AlertSource` (Strategy) + `ParsedAlert` ; `PollAlertSource` (relève
  les flux actifs → `IngestCandidate` par item, dédoublonnage par `externalId`) ; commandes
  `AddAlertFeed`/`RemoveAlertFeed`/`SetAlertFeedActive` + query `GetAlertFeeds`.
- **`Infrastructure/Source/`** : `RssAlertSource` (HttpClient, parsing best-effort — item malformé
  ignoré), `FakeAlertSource` (démo sans réseau, repli quand aucun flux). `RawAlert`/`AlertFeed` en
  DBAL/ORM (tenant explicite, fail-closed).
- **`Infrastructure/Scheduler/`** : `PollAllSourcesTick` (relève auto 30 min, fan-out par tenant)
  + `PurgeRawAlertsTick` (purge quotidienne du brut des annonces rejetées, D6, J+30).
- **API** : `GET/POST /sources`, `POST /sources/{id}/{activate,deactivate}`, `DELETE /sources/{id}`,
  `POST /sources/poll` (relève manuelle, tenant courant).

## Livré (M3.2 — alertes email, plomberie)

- **`Application/AlertEmail/AlertEmailParser`** : parser générique d'email d'alerte (1 annonce =
  1 email, provenance déduite du domaine de l'expéditeur).
- **`Infrastructure/Policy/IngestAlertEmailOnAlertEmailReceived`** : consomme l'event Mailbox
  `AlertEmailReceived` (langage publié) → parse → `IngestCandidate` (tenant réactivé, dédoublonnage
  par id de message). Côté Passerelle : `FetchAlertEmails` lit un label dédié (ADR-0017 amendé) et
  publie l'event ; `FakeAlertEmailFetcher` par défaut (adaptateurs réels Gmail/Outlook = suivi).

**M3 complet.** Suivi (avec de vrais emails) : lecture réelle du label par fournisseur + parsers
fins ProZ/TranslatorsCafe/LinkedIn.
