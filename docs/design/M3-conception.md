# M3 — Ingestion d'annonces (note de cadrage)

> Statut : **validée** (D1→D7 tranchées). Prérequis : M2 clôturé + revue fin M2
> appliquée. Références : ROADMAP § M3, OVERVIEW (Sourcing = *Supporting*, « Passerelle email
> sert Prospection ET Sourcing »), GLOSSAIRE § Contexte Sourcing (termes déjà posés :
> `Sourcing`, `CandidateLead`, Alerte, Parser, enum `Source`), ADR-0015 (import CSV — mêmes
> réflexes de bornage/dédoublonnage), ADR-0017 (relève email — le canal alertes s'y greffe).
> Métier en FR, code en EN.

## 1. Objectif & résultat attendu

Aujourd'hui la Traductrice **saisit à la main** chaque opportunité (démarchage direct, réponse
à une annonce vue sur ProZ/LinkedIn…). M3 fait **entrer les annonces toutes seules** dans une
**file de tri**, où elle **accepte / rejette / fusionne** en un geste — une piste acceptée
devient une vraie `Lead` (avec son organisation), prête pour la rédaction assistée et l'envoi.

Fin M3 :
1. des **annonces sont ingérées** depuis des sources configurées (flux RSS d'abord — ProZ
   notamment ; alertes email selon D2), transformées par un **parser par source** (Strategy) ;
2. chaque annonce devient une **`CandidateLead`** dans une file de tri, **dédoublonnée** contre
   le Répertoire et les candidates existantes (rien n'entre deux fois) ;
3. la Traductrice **trie** : *accepter* (→ crée/rattache l'Organisation + crée la Piste),
   *rejeter* (tracé, ne reviendra pas), *fusionner* (rattache à une organisation existante) ;
4. le **contenu brut** de l'annonce est conservé (reprocessing / audit), avec purge tracée.

Le pipeline, le journal et le tableau de bord existants se nourrissent **sans changement** :
une candidate acceptée passe par `Lead::…` (création) exactement comme aujourd'hui ; la
provenance précise (`PROZ`, `RSS`…) est portée par la piste.

## 2. Périmètre & découpage en slices livrables

| Slice | Contenu | Valeur |
|---|---|---|
| **M3.0 — Socle Sourcing + file de tri** | Contexte `Sourcing`, agrégat `CandidateLead`, read model de la file, écran **file de tri** (accepter/rejeter/fusionner) + API, **promotion** candidate→Piste/Organisation, **dédoublonnage** (le cœur). Source d'amorçage = un `FakeSource` / saisie collée pour jouer la boucle **de bout en bout sans dépendre du réseau**. | La boucle de tri complète, testable seule |
| **M3.1 — Ingestion RSS** | `RssParser` (Strategy) derrière le port `AlertSource`, **polling Scheduler** (pattern relève M2), configuration des flux, dédoublonnage à l'ingestion, conservation du brut | La valeur réelle (ProZ & co en RSS) |
| **M3.2 — Alertes email** | Parsers des emails d'alerte (ProZ/TranslatorsCafe/LinkedIn), la Passerelle email **notifie** Sourcing (`AlertEmailReceived`) via un **label/filtre dédié** (« Plume/Alertes ») lu par la relève, conservation du brut | Les sources sans RSS (dont LinkedIn) |

**Hors périmètre M3** (tracé) : **scraping LinkedIn** (ToS — jamais ; seulement ses emails
d'alerte si D2 les retient), enrichissement automatique de contacts (Futur), acceptation
**automatique** sans tri (jamais en V1 : la Traductrice garde la main), détection tarifaire /
parsing du montant (best-effort seulement), tableau de bord enrichi (réserve fin de jalon).

## 3. Contexte `Sourcing` (nouveau) — *Supporting*

### Agrégats & VOs
- **`CandidateLead`** (racine) : `CandidateLeadId`, `TenantId`, `source` (VO `Source` :
  `PROZ` | `LINKEDIN` | `TRANSLATORSCAFE` | `RSS` | `IMPORT` | `MANUAL`), `status`
  (`PENDING` | `ACCEPTED` | `REJECTED` | `MERGED`), champs extraits **best-effort et bornés**
  (titre, organisation présumée, `LanguagePair?`, URL, extrait, `postedAt?`), `dedupHash`
  (empreinte de déduplication), `rawRef` (référence vers le brut conservé), `ingestedAt`.
  Events : `CandidateLeadIngested`, `CandidateLeadAccepted`, `CandidateLeadRejected`,
  `CandidateLeadMerged`. **Immuable après tri** (garde d'état contre re-tri concurrent —
  leçon P0 fin M1).
- **`RawAlert`** (support, hors agrégat métier) : le contenu **brut** conservé (item RSS ou
  email), `rawId`, `TenantId`, `source`, payload, `fetchedAt` — pour reprocessing/audit.
  Purgé selon D6.

### Ports (Application)
- **`AlertSource`** (Strategy, par source) : `poll(config): iterable<ParsedAlert>` — implémentations
  `RssAlertSource`, `EmailAlertSource` (Infra, ACL). Un parser = une classe testable sur
  **fixtures enregistrées** (flux RSS figé / email figé), aucun test ne touche le réseau.
- **`CandidateLeadRepository`**, read model **`CandidateQueue`** (SQL direct, fail-closed tenant).
- **Frontières cross-contexte par commande/gateway** (jamais d'accès direct à un agrégat d'un
  autre contexte) : à l'acceptation, Sourcing **demande** à Directory de créer/rattacher une
  Organisation et à Prospecting de créer une Piste — via commandes applicatives (ports
  `DirectoryGateway` / `ProspectingGateway`, tenant explicite, pattern `LeadGateway` M1.4).
  La Passerelle email **notifie** Sourcing par event applicatif (M3.2), jamais l'inverse.

### Flux
1. **Ingestion** (asynchrone, worker/Scheduler — pattern relève M2) : `AlertSource::poll` →
   `ParsedAlert` → `IngestCandidate` → dédoublonnage (§4) → `CandidateLead` `PENDING` +
   `RawAlert` conservé. Rien n'est créé côté Répertoire/Pipeline à ce stade.
2. **Tri** (synchrone, geste humain) : `AcceptCandidate` / `RejectCandidate` / `MergeCandidate`.
   - *Accepter* : crée l'Organisation si nouvelle (sinon rattache) + crée la Piste
     (provenance fine portée par `LeadSource` **enrichi**, cf. D5), candidate → `ACCEPTED` (idempotent).
   - *Fusionner* : rattache à une organisation **désignée** (résolution d'un doublon), → `MERGED`.
   - *Rejeter* : → `REJECTED`, `dedupHash` conservé pour ne pas ré-ingérer (D6).

## 4. Dédoublonnage (le cœur du jalon)

Objectif : **ne jamais faire réapparaître** ce qui a déjà été vu ou traité, et **proposer** les
rapprochements plausibles sans jamais fusionner à l'aveugle.
- **À l'ingestion** : `dedupHash` = empreinte normalisée (source + identifiant stable de
  l'annonce si présent, sinon nom d'organisation normalisé + titre). Collision → **no-op**
  (l'annonce n'entre pas deux fois), tracé.
- **Contre le Répertoire** : au tri, on **suggère** l'organisation existante la plus proche
  (nom normalisé — réutilise la normalisation d'unicité M1.1 —, domaine email/URL) et on
  propose *Fusionner*. **V1 = correspondance exacte normalisée + suggestion**, pas de
  *fuzzy*/ML (différé). La décision de fusion reste **humaine**.
- **Contacts** : dédoublonnage à l'acceptation (email normalisé) réutilisant les réflexes de
  l'import CSV (ADR-0015).

## 5. Sécurité & RGPD

- **Minimisation de lecture** (le point sensible de D2) : la relève email actuelle ne lit que
  **les fils initiés par l'app** (ADR-0017). Lire des alertes élargit cette lecture → **canal
  cadré** : un **label/filtre dédié** que la Traductrice pose (ex. « Plume/Alertes »), la
  relève ne lit que ce label. Pas de balayage de la boîte entière. (Ou D2 = RSS seulement.)
- **Données personnelles dans les annonces** : une annonce peut contenir un email de contact →
  mêmes gardes que le Répertoire (opt-out `doNotContact` respecté ; jamais de contact importé
  sans passer par le tri humain).
- **Rétention du brut** (`RawAlert`) : conservé pour reprocessing, **purgé** selon D6
  (candidate rejetée/traitée → purge après délai, tracée) — cohérent avec la ligne « pas de
  rétention temporelle du journal » (ADR-0017) transposée aux bruts.
- **Robustesse parsing** : un parser qui échoue sur un item **n'interrompt pas** le lot
  (best-effort, erreurs en logs sans contenu personnel) ; bornage strict des champs extraits.
- **Isolation tenant** fail-closed sur toutes les nouvelles surfaces (acquis à maintenir).

## 6. API & Front (esquisse)

- **API** : `GET /candidate-leads` (file de tri, filtrable par source/statut),
  `POST /candidate-leads/{id}/accept` (corps : organisation cible existante **ou** nouvelle),
  `/reject`, `/merge` ; config des sources (`GET/POST /sources`). Rate limiting sur l'ingestion
  déclenchée manuellement.
- **Front** : nouvelle entrée de nav **« À trier »** (badge du nombre en attente) → écran file
  de tri : cartes candidates (titre, organisation présumée, `LangStamp`, source, extrait, lien
  vers l'annonce), actions **Accepter / Rejeter / Fusionner** (fusion = sélecteur
  d'organisation existante avec suggestion), état vide actionnable, skeletons (acquis Lot E).
  Réglages § « Sources » (ajouter un flux RSS, activer le label d'alertes selon D2).

## 7. Tests

Pyramide habituelle + spécificités : parsers testés sur **fixtures enregistrées** (flux RSS
figé, email d'alerte figé — pattern `ClaudeMessageGeneratorTest` / adaptateurs M2), aucun test
réseau ; **dédoublonnage** couvert (collision à l'ingestion, suggestion de doublon au tri) ;
**promotion** cross-contexte testée (accepter → Piste + Organisation créées, isolation tenant) ;
idempotence du tri (double accept = no-op) ; E2E de la **boucle complète** via `FakeSource`
(ingestion → file → accepter → la piste apparaît dans le pipeline).

## 8. ADR à acter

- **ADR-0020 — Contexte Sourcing & file de tri** : place du contexte, `CandidateLead` immuable
  après tri, promotion cross-contexte par gateway/commande, pas d'acceptation automatique.
- **ADR-0021 — Dédoublonnage** : `dedupHash`, correspondance exacte normalisée + suggestion
  (pas de fuzzy en V1), fusion humaine, rétention/purge du brut.
- ADR-0017 amendé si D2 retient le canal alertes email (élargissement cadré de la relève).

## 9. Décisions — **validées**

1. **D1 — RSS d'abord**, puis alertes email ; **LinkedIn scraping exclu** (ToS). ✔
2. **D2 — RSS + alertes email** : le canal alertes est retenu via un **label/filtre dédié**
   (« Plume/Alertes ») lu par la relève — élargissement **cadré** de la lecture (ADR-0017 amendé). ✔
3. **D3 — Accepter** crée l'Organisation (si nouvelle) **+** la Piste ; **Fusionner** rattache
   à une organisation existante. ✔
4. **D4 — Dédoublonnage exact normalisé + suggestion** (fusion humaine ; pas de fuzzy en V1). ✔
5. **D5 — `LeadSource` enrichi** (`PROZ`, `LINKEDIN`, `TRANSLATORSCAFE`, `RSS`… en plus de
   `DIRECT`/`REFERRAL`/`JOB_BOARD`/`OTHER`) : la provenance fine est portée **par la Piste**
   → migration + libellés i18n + dashboard/segments à mettre à jour. La `CandidateLead` porte
   la même VO `Source`. ✔
6. **D6 — `RawAlert` purgé après tri + délai** (rejeté → purge à J+30) ; la candidate rejetée
   garde son `dedupHash` (anti-réapparition) mais pas le contenu. ✔
7. **D7 — Découpage** M3.0 (socle + tri) → M3.1 (RSS) → M3.2 (alertes email). ✔

## 10. Definition of Done — M3

- [ ] Contexte `Sourcing` : `CandidateLead` (immuable après tri, events), `RawAlert`, read
      model file de tri fail-closed, pyramide de tests (domaine sans DB → application →
      fonctionnel Postgres + isolation tenant).
- [ ] **File de tri** : écran accepter/rejeter/fusionner + API, promotion cross-contexte
      (Organisation + Piste) par gateway, idempotence du tri.
- [ ] **Dédoublonnage** : anti-doublon à l'ingestion (`dedupHash`) + suggestion de fusion au tri.
- [ ] **`LeadSource` enrichi** (D5) : enum étendu (`PROZ`/`LINKEDIN`/`TRANSLATORSCAFE`/`RSS`),
      migration, libellés i18n FR/EN, dashboard/segments cohérents, rétrocompat des pistes existantes.
- [ ] **Ingestion RSS** : `RssParser` (Strategy) derrière `AlertSource`, polling Scheduler,
      parsers testés sur fixtures, conservation + purge du brut.
- [ ] **Alertes email** : parsers ProZ/TranslatorsCafe/LinkedIn, notification depuis la
      Passerelle email via label dédié (ADR-0017 amendé).
- [ ] ADR-0020 + ADR-0021 écrits (ADR-0017 amendé si besoin) ; openapi / glossaire / ROADMAP
      à jour ; CI verte.
- [ ] **Revue de santé fin M3** (process acté).
