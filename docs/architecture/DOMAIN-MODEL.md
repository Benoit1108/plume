# Modèle de domaine

Modélisation tactique DDD. Détaille le **cœur Prospection** ; survole les autres contextes.
Les noms sont ici **métier (FR)** ; les identifiants du code sont **EN** via la table de
correspondance du [glossaire](../GLOSSAIRE.md) (contractuelle — ADR-0010).
*Resynchronisé à M3.0 (2026-07-20) : contexte Sourcing, `LeadSource` enrichi, contexte Compte,
events aux noms réels (EN).*

---

## Contexte Prospection (Core)

### Agrégat racine : `Piste`

Frontière : **les Relances vivent DANS l'agrégat** (invariant fort « une réponse annule les relances en attente ») ; **les Interactions sont un journal séparé** (projection append-only) pour ne pas gonfler l'agrégat.

```
Piste (Aggregate Root)
├─ PisteId              (VO, UUID)
├─ TenantId             (VO)              cohérence tenant garantie
├─ OrganisationId       (réf. cross-agrégat)
├─ ContactId?           (réf. cross-agrégat, nullable)
├─ Segment              (VO)
├─ PaireDeLangue        (VO)
├─ Source               (VO)
├─ Priorite             (VO)
├─ ValeurEstimee?       (VO: Tarif — **différée**, reportée en ROADMAP § M2)
├─ Statut               (VO: StatutPipeline)
├─ ProchaineAction?     (date + libellé — dénormalisé pour « à faire »)
├─ Relances[]           (Entités DANS l'agrégat)
└─ dates (créée, dernier contact, dernière réponse)
```

**Méthodes métier** (chacune émet un ou des domain events) :

| Méthode | Effet | Event(s) |
|---------|-------|----------|
| `contact()` | marque la Piste contactée + **planifie la relance de cadence** | `LeadContacted`, `FollowUpScheduled` |
| `returnToContact()` | correction (contact par erreur) : `CONTACTÉE → À_CONTACTER`, annule la relance auto, efface la date de contact (ADR-0008 amendé) | `LeadReturnedToContact` (+ `FollowUpCancelled`) |
| `scheduleFollowUp(date)` | planifie/replanifie LA relance en attente | `FollowUpScheduled` |
| `recordFollowUp()` | relance faite + planifie la suivante (cadence J+7/21/45) | `FollowUpSent`, `FollowUpScheduled` |
| `recordReply()` | **annule la relance en attente**, passe en `EN_DISCUSSION` | `ReplyReceived`, `FollowUpCancelled` |
| `moveToSampleTest()` | → `TEST_ECHANTILLON` | `LeadMovedToSampleTest` |
| `markWon()` / `markLost()` | états terminaux (annulent la relance en attente) | `LeadWon` / `LeadLost` (+ `FollowUpCancelled`) |
| `pause()` / `resume()` | met en veille (statut mémorisé) / réactive | `LeadPaused` / `LeadResumed` |
| `addNote(texte)` | note manuelle | `NoteAdded` |

### Machine à états (pipeline opinioné, figé en V1)

```
À_CONTACTER ─► CONTACTÉE ─► RELANCÉE ⭯ ─► EN_DISCUSSION ─► TEST/ÉCHANTILLON ─► GAGNÉE
                  │            │              │                    │           (→ Mission, futur)
                  └────────────┴──────────────┴────────────────────┴────► PERDUE
       (tout état actif, sauf TEST_ECHANTILLON — phase courte non interruptible) ◄─► EN_PAUSE
```

- Transition **automatique** : réponse entrante depuis `CONTACTÉE`/`RELANCÉE` → `EN_DISCUSSION` (déclenchée par la Passerelle email).
- Transition de **correction** : `CONTACTÉE → À_CONTACTER` (`returnToContact()`, ADR-0008 amendé) — annule la relance auto, pour un « Contacter » cliqué par erreur.
- `GAGNÉE` / `PERDUE` = terminaux → plus de relance planifiable.

### Invariants portés par `Piste`
1. Seules les transitions du graphe sont permises (sinon exception domaine).
2. Une réponse reçue **annule** toutes les relances en attente.
3. États terminaux → aucune relance planifiable, aucune transition sortante (hors réouverture explicite).
4. **Une seule relance en attente (`PENDING`) par piste** (remplacée, jamais empilée — décision M1.3).
5. `TenantId` de la Piste = celui de l'Organisation/Contact référencés.
6. `ValeurEstimee ≥ 0` *(différée avec le champ — cf. ROADMAP § M2)*.

### Régularité (objectif & série)
- **Cible** = configuration sur le Profil : `ObjectifHebdomadaire(nbContactsVises)`.
- **Progression** = read model projeté depuis `PisteContactee` sur la fenêtre semaine.
- **Série** = read model : nombre de semaines consécutives où l'objectif est atteint.
- Pas d'agrégat lourd : c'est de la **lecture dérivée** des événements.

### Journal d'Interactions (projection)
Table append-only écrite par des handlers réagissant aux domain events (`PisteContactee`, `ReponseRecue`, `RelanceEnvoyee`, `NoteAjoutee`…) et aux commandes explicites (note, appel). La timeline d'une Piste = simple requête sur cette table.

---

## Value Objects (partagés / Prospection)

| VO | Détail |
|----|--------|
| **PaireDeLangue** | (LangueSource, LangueCible), codes ISO 639. Directionnelle. |
| **Langue** | Code ISO 639-1 validé. |
| **Segment** | `PUBLISHING` \| `AUDIOVISUAL` \| `TECHNICAL` \| `OTHER` (UI : Édition, Audiovisuel, Technique, Autre). |
| **StatutPipeline** | Enum + règles de transition. |
| **LeadSource** | `DIRECT` (démarchage direct, cœur métier) \| `REFERRAL` (recommandation) \| `JOB_BOARD` (annonce, source non précisée) \| `PROZ` \| `LINKEDIN` \| `TRANSLATORSCAFE` \| `RSS` \| `OTHER`. **Enrichi en M3.0** (ADR-0020) : les valeurs fines viennent du tri d'annonces (`Source` du Sourcing → `LeadSource`). Table **contractuelle** — cf. glossaire. |
| **Priorite** | `HAUTE` \| `MOYENNE` \| `BASSE`. |
| **Tarif** | Montant + devise + base (`AU_MOT_SOURCE`/`AU_MOT_CIBLE`/`A_LA_MINUTE`/`FORFAIT`) + minimum. |
| **Money** | Montant + devise. |
| **AdresseEmail** | Validée. |
| **CadenceRelance** | Suite de délais, ex. `[J+7, J+21, J+45]`. |

---

## Domain events (colonne vertébrale du découplage)

`LeadCreated` · `LeadContacted` · `LeadReturnedToContact` · `FollowUpScheduled` · `FollowUpSent` · `FollowUpCancelled` · `ReplyReceived` · `LeadMovedToSampleTest` · `LeadWon` · `LeadLost` · `LeadPaused` · `LeadResumed` · `NoteAdded`

Consommateurs : journal d'Interactions, KPIs du tableau de bord, progression/série, notifications.

---

## Autres contextes (survol)

### Répertoire
- **`Organisation`** (racine) **contient** ses **`Contact`** (entités) : peu nombreux, édités ensemble.
- Invariants : email unique par organisation, tenant cohérent.
- Dédoublonnage des annonces à l'ingestion : porté par le contexte Sourcing (livré M3.0, ADR-0021).

### Rédaction assistée (contexte `Drafting`, livré M1.4 — cf. ADR-0014)
- **`Brouillon` (`Draft`) est un agrégat persistant à états** : `GENERATING → READY | FAILED`,
  avec gardes (édition sur `READY` uniquement ; un résultat de génération n'est accepté que
  depuis `GENERATING` — l'asynchrone livre at-least-once). Référence la Piste par ID.
- Agrégat **`Modele` (`Template`)** : gabarits par type/segment/langue avec variables,
  3 seedés à la première utilisation.
- **Port `MessageGenerator`** (l'ACL Claude et le générateur local vivent en Infrastructure) ;
  génération **asynchrone** par le worker, garde RGPD re-vérifiée avant l'appel.
- Le journal de la Piste s'enrichit de `draft_generated` (event `DraftGenerated` consommé
  cross-contexte — ADR-0003 amendé).

### Passerelle email (contexte `Mailbox`, livré M2 — cf. ADR-0007/0016/0017)
- Agrégat **`ConnectedMailbox`** (`MailboxId`, `provider` `GMAIL`|`OUTLOOK`, tokens
  `EncryptedToken` **chiffrés au repos**, `status` `CONNECTED`|`ERROR`|`REVOKED`). Une par
  tenant en V1 (invariant levable). Events `MailboxConnected`/`MailboxRevoked`/`MailboxSyncFailed`.
- Agrégat **`OutboundMessage`** (envoi) : `SENDING → SENT | FAILED`, gardes d'état
  anti-redélivrance, `threadKey` (fil provider). Events `EmailSendRequested` (async),
  `EmailSent`, `EmailSendFailed`, `ReplyCaptured`, `AlertEmailReceived` (M3.2, émis par le
  handler `FetchAlertEmails` — langage publié vers le Sourcing).
- Ports (routés par fournisseur via des registres) : `MailboxConnector` (OAuth),
  `MailSender` (envoi), `ReplyFetcher` (relève), `TokenCipher` (chiffrement) ;
  frontières `DraftGateway`/`RecipientResolver`/`OpenThreads` (tenant explicite, worker-safe).
- **Envoi** (`EmailSent`) → la Prospection avance la piste (D3 : `contact()` / `recordFollowUp()`).
- **Réponse captée** par threading (`ReplyCaptured`) → la Prospection appelle
  `Lead::recordReply()` (**idempotent**), qui passe en `IN_DISCUSSION` et annule la relance.
- **Alerte captée** sous le label dédié (`AlertEmailReceived`, M3.2) → le Sourcing ingère
  l'annonce (ADR-0017 amendé). Lecture limitée au label (minimisation).

### Sourcing — file de tri (contexte `Sourcing`, livré M3.0 — cf. ADR-0020/0021)
- Agrégat **`CandidateLead`** (annonce candidate) : une annonce captée, en attente de tri.
  Champs : `CandidateLeadId`, `TenantId`, `Source`, `dedupHash`, `CandidateStatus`, titre,
  nom d'organisation ?, paire de langue ?, url ?, extrait ?, date de publication ?,
  `promotedLeadId` ?, `organizationId` ?, date d'ingestion. **Immuable une fois triée.**
- Enum **`Source`** (provenance fine) : `PROZ` \| `LINKEDIN` \| `TRANSLATORSCAFE` \| `RSS` \| `MANUAL`.
  `Source::toLeadSource()` projette vers `LeadSource` (identité, sauf `MANUAL → JOB_BOARD`).
- **Machine à états** (`CandidateStatus`) :
  ```
  PENDING ─► ACCEPTED   (nouvelle Organisation + nouvelle Piste)
          ├► MERGED     (Organisation existante ; rattache à la piste active ou en crée une)
          └► REJECTED   (écartée)
  ```
  `PENDING` est le seul état triable ; tout re-tri lève `CandidateAlreadyTriaged` (409).
- **Dédoublonnage à l'ingestion** (ADR-0021) : `Dedup::hash(Source, externalId?, orgName?, titre)`
  (sha256 normalisé) + index unique `(tenant_id, dedup_hash)`. `IngestCandidate` est un **no-op**
  si le hash existe déjà pour le tenant. `MANUAL` sans identifiant externe → garde faible (assumé).
- **Promotion cross-contexte par gateways** (ports Application — jamais d'accès direct à un
  agrégat étranger) : `DirectoryGateway::createOrganization` (Répertoire) +
  `ProspectingGateway::createLead` (Prospection). Accept et Merge dispatchent sur `command.bus` :
  tri + création d'organisation + création de piste partagent **une seule transaction** (bus
  imbriqué, `doctrine_transaction`). La garde `PENDING` est évaluée **AVANT** les gateways →
  aucune organisation/piste orpheline (ADR-0020, verrouillé par un test d'atomicité).
- **Fusion vers une organisation à piste active** (ADR-0021, décision produit du 2026-07-20) :
  l'invariant « 1 piste active par organisation » (M1.2) interdit une 2e piste ; `MergeCandidate`
  **rattache l'annonce à la piste active existante** (note « Annonce rattachée : … » via
  `ProspectingGateway::annotateLead`), sans créer de doublon.
- Events : `CandidateLeadIngested` · `CandidateLeadAccepted` · `CandidateLeadMerged` · `CandidateLeadRejected`.
- Écran **« À trier »** : file des `PENDING`, actions accepter/fusionner/rejeter, badge de compte
  dans la navigation.
- **Ingestion RSS (M3.1 livré)** : support **`RawAlert`** (brut d'une annonce conservé pour
  audit/reprocessing — `RawAlertId`, `TenantId`, `Source`, payload, `fetchedAt` ; hors agrégat
  métier, écrit en DBAL, non mappé ORM) ; la `CandidateLead` le référence par `rawRef`. Port
  **`AlertSource`** (Strategy) → `RssAlertSource` (HttpClient, parsing best-effort) /
  `FakeAlertSource` (démo, repli sans flux). Agrégat **`AlertFeed`** (flux RSS par tenant :
  `source`, `url`, `label`, `active`). `PollAlertSource` relève les flux actifs → `IngestCandidate`
  par item (dédoublonnage par `externalId`). Déclenchement **manuel** (`POST /sources/poll`) et
  **automatique** (Scheduler 30 min, fan-out par tenant). Purge planifiée du brut des annonces
  rejetées (D6, J+30).
- **Alertes email (M3.2 livré, plomberie)** : la Passerelle lit un **label dédié** et publie
  `AlertEmailReceived` (langage publié) ; la politique Sourcing `IngestAlertEmailOnAlertEmailReceived`
  parse (générique : provenance par domaine de l'expéditeur) et dispatche `IngestCandidate`.
  Adaptateurs réels Gmail/Outlook + parsers fins par fournisseur = suivi. **M3 complet.**

### Compte (contexte `Account`, livré M2.0 / M3.0)
- Agrégat **`Profile`** (un par tenant) : préférences et présentation de la traductrice.
  - **Régularité** : `weeklyGoal` (objectif hebdo, défaut 5), `timezone` (défaut `Europe/Paris`).
  - **Présentation** (matière première des prompts de génération — M1.4) : `bio`, `specialties`, `signature`.
  - **Identité d'affichage** (M3.0) : `firstName`, `lastName` (nom affiché dans l'app).
  - Méthodes : `changeWeeklyGoal`, `changePresentation`, `changeIdentity` — chacune n'émet un
    event **que si** la valeur change.
  - Events : `ProfileCreated` · `WeeklyGoalChanged` · `ProfilePresentationChanged` · `ProfileIdentityChanged`.
- **Authentification** (M2.0) : JWT en cookies httpOnly + refresh tokens (gesdinet). Le
  **changement de mot de passe révoque tous les refresh tokens** du compte (expulse une session
  détournée — remédiation revue M3.0). Préoccupation d'infrastructure (Symfony Security), hors
  agrégat de domaine.

### Gestion de mission (futur)
- **`Mission`** : volume, deadline, tarif, livrables, statut. Lien `PisteGagnee` → `Mission`.
