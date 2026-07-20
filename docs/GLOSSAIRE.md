# Glossaire — langage ubiquitaire

Le vocabulaire métier ci-dessous est **contractuel** et reste en **français** (langue de l'utilisatrice et du métier) : UI, échanges, documentation. Le **code** (classes, méthodes, events) est en **anglais**, via la table de correspondance ci-dessous qui fait le pont sans trahir l'*ubiquitous language*. Voir [ADR-0010](architecture/decisions/0010-langue-de-nommage.md).

## Correspondance FR ↔ EN (code)

### Contextes (dossiers `src/`)
| Métier (FR) | Code (EN) |
|---|---|
| Prospection | `Prospecting` |
| Répertoire | `Directory` |
| Rédaction assistée | `Drafting` |
| Passerelle email | `Mailbox` |
| Sourcing | `Sourcing` |
| Compte & Tenancy | `Account` |
| Partagé (kernel) | `Shared` |

### Concepts
| Métier (FR) | Code (EN) |
|---|---|
| Traductrice | `Translator` |
| Tenant | `Tenant` / `TenantId` |
| Piste | `Lead` |
| Statut (pipeline) | `PipelineStatus` |
| Interaction | `Interaction` |
| Relance | `FollowUp` |
| Cadence | `FollowUpCadence` |
| Objectif hebdomadaire | `weeklyGoal` (champ du `Profile`) |
| Série | `streak` (calculée sur le journal, pas une classe) |
| Segment | `Segment` |
| Organisation | `Organization` |
| Contact | `Contact` |
| Annuaire | `Directory` (read model) |
| Brouillon | `Draft` |
| Modèle | `Template` |
| Génération (service) | `MessageGenerator` |
| Compte email connecté | `ConnectedMailbox` |
| Envoi | `OutboundMessage` |
| Réponse | `Reply` |
| Source d'annonces (Strategy) | `AlertSource` (port) → `RssAlertSource` / `FakeAlertSource` |
| Annonce extraite | `ParsedAlert` (DTO) |
| Brut d'annonce conservé | `RawAlert` (audit / reprocessing) |
| Alerte email | `Alert` (M3.2) |
| Annonce candidate (file de tri) | `CandidateLead` |
| Mission (futur) | `Assignment` |
| Facture (futur) | `Invoice` |

### Value Objects
| Métier (FR) | Code (EN) |
|---|---|
| PaireDeLangue | `LanguagePair` |
| Tarif | `Rate` |
| Source | `LeadSource` |
| Priorité | `Priority` |
| AdresseEmail | `EmailAddress` |
| CodePays (ISO 3166-1) | `CountryCode` |
| CodeLangue (ISO 639-1) | `LanguageCode` |
| IdentifiantDeTenant | `TenantId` |

### Répertoire — `Organization`
| Métier (FR) | Code (EN) |
|---|---|
| Type d'organisation | `OrganizationType` : `PUBLISHER` (Éditeur), `AV_STUDIO` (Labo A/V), `AGENCY` (Agence), `OTHER` (Autre) |
| Ne pas contacter (RGPD) | `doNotContact` — **réversible et tracé** (`markDoNotContact()` / `allowContact()`, events dédiés) |
| Nom unique (par tenant) | invariant `OrganizationNameAlreadyUsed` (insensible à la casse) |
| mettre à jour le profil | `updateProfile()` |
| ajouter / modifier / retirer un contact | `addContact()` / `updateContact()` / `removeContact()` |

### Répertoire — Import CSV
| Métier (FR) | Code (EN) |
|---|---|
| Import (orchestration) | `OrganizationImporter` (Application) |
| Ligne d'import | `ImportedOrganizationRow` |
| Rapport d'import | `ImportReport` : importées `imported`, ignorées `skipped` (doublon de nom), en échec `failed` + erreurs par ligne |
| Parseur CSV | `CsvOrganizationParser` (Infrastructure — délimiteur auto, en-têtes FR/EN) |

### Prospection — VOs et enums de la Piste
| Métier (FR) | Code (EN) |
|---|---|
| PaireDeLangue (source>cible, ex. en>fr) | `LanguagePair` |
| Origine de la piste | `LeadSource` : `DIRECT` (démarchage direct, cœur métier), `REFERRAL` (recommandation), `JOB_BOARD` (annonce générique), `PROZ`, `LINKEDIN`, `TRANSLATORSCAFE`, `RSS`, `OTHER` — **table contractuelle** ; les 4 valeurs fines viennent du tri d'annonces (Sourcing, ADR-0020) |
| Priorité | `Priority` : `LOW` \| `MEDIUM` \| `HIGH` |
| Une piste active par organisation | invariant `ActiveLeadAlreadyExists` (index partiel en filet) |
| Démarchage interdit (RGPD) | `OrganizationNotContactable` — vérifié via le port `OrganizationGateway` |
| Journal d'interactions | table `interaction` (projection append-only des events, idempotente par `event_id`) |

### Prospection — Tableau de bord (`Dashboard`, M1.5)
| Métier (FR) | Code (EN) |
|---|---|
| Tableau de bord | `Dashboard` (port) / `DashboardView` — slice 100 % lecture, aucune projection dédiée |
| Taux de réponse | pistes avec ≥ 1 réponse / pistes contactées — comptes **par piste** sur le journal (`lead_id` distinct) |
| Conversion | gagnées / **décidées** (gagnées + perdues) — l'UI affiche toujours les comptes en clair |
| Activité hebdomadaire | `WeekActivity[]` : 8 semaines ISO au fuseau du profil, objectif courant en référence |
| Répartition du pipeline | `PipelineSlice[]` (ordre du kanban) |
| Résultats par segment | `SegmentStats[]` : contactées / réponses / gagnées |

### Rédaction assistée — `Draft` / `Template` (M1.4)
| Métier (FR) | Code (EN) |
|---|---|
| Brouillon (agrégat) | `Draft` : type, langue cible, sujet?, corps, statut `GENERATING` \| `READY` \| `FAILED` — **draft-first**, jamais d'envoi en M1 |
| Type de message | `DraftType` : `APPLICATION_EMAIL` (candidature), `COVER_LETTER` (lettre de motivation), `FOLLOW_UP_EMAIL` (relance) |
| Modèle / gabarit (agrégat) | `Template` : nom, type, segment, langue, variables `{{contact}}` `{{organisation}}` `{{langues}}` `{{bio}}` `{{specialites}}` `{{signature}}` — 3 seedés à la première utilisation |
| Port de génération | `MessageGenerator` — adaptateurs `CannedMessageGenerator` (défaut sans clé, coût zéro) / `ClaudeMessageGenerator` (ACL Anthropic, env `ANTHROPIC_API_KEY` + `DRAFTING_MODEL`) |
| Matière du prompt | `DraftPrompt` (profil + cible + piste + gabarit) assemblé par `DraftPromptBuilder` (worker, tenant explicite) |
| Frontière vers la Piste | port `LeadGateway` (tenant **explicite** : utilisable depuis le worker) |
| Génération interdite (RGPD) | `DraftingNotAllowed` (409) — re-vérifiée par le worker (`FailDraft` code `contact_not_allowed`) |
| Édition hors READY | `DraftNotEditable` (409) |
| Raison d'échec affichable | code stable (`generation_failed`, `lead_unavailable`, `contact_not_allowed`) traduit côté front (`drafts.failures.*`) |
| Présentation du profil | `Profile.changePresentation()` : `bio`, `specialties`, `signature` (Account, M1.4) |

### Prospection — Relance (`FollowUp`, M1.3)
| Métier (FR) | Code (EN) |
|---|---|
| Relance (entité dans l'agrégat Lead) | `FollowUp` : échéance `dueAt`, libellé, statut `PENDING` \| `DONE` \| `CANCELLED` — une seule PENDING par piste |
| Cadence par défaut | `FollowUpCadence` : J+7, J+21, J+45 (auto après contact et chaque relance faite) |
| Motif d'annulation | `FollowUpCancelReason` : `REPLY` \| `TERMINAL` \| `PAUSED` \| `MANUAL` |
| Prochaine relance (dénormalisée) | `nextFollowUpAt` / `nextFollowUpLabel` (requête « dues aujourd'hui ») |
| Objectif hebdomadaire | `Profile.weeklyGoal` (Account) — un acte = contact ou relance faite |
| Série | `streak` : semaines ISO consécutives ≥ objectif (calculée sur le journal) |

### Méthodes de l'agrégat `Lead`
| Métier (FR) | Code (EN) |
|---|---|
| contacter | `contact()` |
| planifier une relance | `scheduleFollowUp()` |
| enregistrer une réponse | `recordReply()` |
| passer au test/échantillon | `moveToSampleTest()` |
| marquer gagnée / perdue | `markWon()` / `markLost()` |
| mettre en pause / reprendre | `pause()` / `resume()` |
| ajouter une note | `addNote()` |

### Domain events
| Métier (FR) | Code (EN) |
|---|---|
| PisteCréée | `LeadCreated` |
| PisteContactée | `LeadContacted` |
| RelancePlanifiée | `FollowUpScheduled` (dueAt, auto) |
| RelanceFaite | `FollowUpSent` (l'envoi réel arrive en M2) |
| RelanceAnnulée | `FollowUpCancelled` (reason) |
| RéponseReçue | `ReplyReceived` |
| PassageAuTest / MiseEnPause / Reprise | `LeadMovedToSampleTest` / `LeadPaused` / `LeadResumed` |
| PisteGagnée / PistePerdue | `LeadWon` / `LeadLost` |
| NoteAjoutée | `NoteAdded` |
| OrganisationCréée | `OrganizationCreated` |
| ProfilOrganisationMisÀJour | `OrganizationProfileUpdated` |
| ContactAjouté / Modifié / Retiré | `ContactAdded` / `ContactUpdated` / `ContactRemoved` |
| DémarchageInterdit / Réautorisé | `OrganizationDoNotContactMarked` / `OrganizationDoNotContactCleared` |

### Statuts du pipeline
| Métier (FR) | Code (EN) |
|---|---|
| À contacter | `TO_CONTACT` |
| Contactée | `CONTACTED` |
| Relancée | `FOLLOWED_UP` |
| En discussion | `IN_DISCUSSION` |
| Test / Échantillon | `SAMPLE_TEST` |
| Gagnée | `WON` |
| Perdue | `LOST` |
| En pause | `PAUSED` |

---

> Les tableaux ci-dessous décrivent le **sens métier** (en français). Le nom de code EN est rappelé quand utile.

## Acteurs

| Terme          | Définition |
|----------------|------------|
| **Traductrice** | L'utilisatrice. Possède un **Profil**. En V1, une seule ; l'architecture reste multi-tenant. |
| **Tenant**      | Espace de travail isolé (une Traductrice = un tenant). Toutes les données sont cloisonnées par `TenantId`. |

## Contexte Prospection (cœur)

| Terme          | Définition |
|----------------|------------|
| **Piste**       | Une opportunité suivie dans le pipeline (candidature spontanée, réponse à une annonce…). Objet central qui *bouge* d'un statut à l'autre. Agrégat racine. |
| **Statut**      | Position de la Piste dans le pipeline (machine à états, voir DOMAIN-MODEL). |
| **Interaction** | Événement de communication rattaché à une Piste (mail envoyé, réponse reçue, note, appel, test envoyé). Journal append-only alimenté par événements. |
| **Relance**     | Action de suivi **planifiée** (ex. J+10 sans réponse). Vit dans l'agrégat Piste. |
| **Cadence**     | Suite de délais définissant l'échéancier des relances (ex. J+7, J+21, J+45). |
| **Objectif**    | Cible de régularité (ex. 10 démarchages/semaine). Progression et **série** dérivées des événements. |
| **Segment**     | Domaine visé — valeurs de code (EN, contractuelles) : `PUBLISHING`, `AUDIOVISUAL`, `TECHNICAL`, `OTHER` (UI : Édition, Audiovisuel, Technique, Autre). Conditionne le ton des messages. |

## Contexte Répertoire

| Terme            | Définition |
|------------------|------------|
| **Organisation** | Une cible : maison d'édition, labo de sous-titrage/doublage, agence (LSP), autre. Agrégat racine. |
| **Contact**      | Une personne au sein d'une Organisation (responsable de collection, chef de projet…). Entité dans l'agrégat Organisation. |
| **Annuaire**     | Base pré-remplie d'Organisations fournie de série (V2). |

## Contexte Rédaction assistée

| Terme         | Définition |
|---------------|------------|
| **Génération** | Production assistée par IA d'un mail / lettre de motivation à partir du Profil + de la cible + du Segment + de la langue. |
| **Brouillon**  | Résultat d'une Génération, éditable, avant envoi. La Traductrice **relit et valide toujours** (draft-first). |
| **Modèle**     | Gabarit de message réutilisable, avec variables, par segment/langue. |

## Contexte Passerelle email

| Terme                    | Définition |
|--------------------------|------------|
| **Compte email connecté** | Boîte reliée par OAuth (Gmail / Outlook). Sert à envoyer, capter les réponses et ingérer les alertes. |
| **Envoi**                 | Émission d'un message depuis la boîte de la Traductrice. |
| **Réponse**               | Message entrant rattaché à une Piste via `Message-ID` / `References`. |

### Passerelle email — `ConnectedMailbox` (M2.1)
| Métier (FR) | Code (EN) |
|---|---|
| Compte email connecté (agrégat) | `ConnectedMailbox` : `MailboxId` propre, provider `GMAIL` \| `OUTLOOK`, statut `CONNECTED` \| `ERROR` \| `REVOKED` — une par tenant (invariant V1 levable, D6) |
| Jeton chiffré | `EncryptedToken` (VO) — chiffrement sodium au repos, ADR-0016 ; effacé à la révocation |
| Chiffreur de jetons | port `TokenCipher` → `SodiumTokenCipher` (clé `MAILBOX_ENCRYPTION_KEY`) |
| Connecteur OAuth | port `MailboxConnector` (via `MailboxConnectorRegistry`) → `GmailConnector` / `OutlookConnector` (ACL Google & Microsoft Graph) / `FakeMailboxConnector` (défaut sans identifiants) |
| Fournisseur | routé PAR LA BOÎTE : registres `MailboxConnectorRegistry` / `MailSenderRegistry` / `ReplyFetcherRegistry` — le provider voyage signé dans le `state` OAuth |
| State anti-CSRF | `OAuthStateCodec` (HMAC, lié au tenant, TTL 10 min, sans stockage serveur) |
| Échec de connexion | `MailboxConnectionFailed` → 422 propre ; boîte non opérationnelle : `MailboxNotOperational` (409) |

### Passerelle email — `OutboundMessage` (M2.2)
| Métier (FR) | Code (EN) |
|---|---|
| Fournisseur (enum) | `MailProviderName` : `GMAIL` \| `OUTLOOK` |
| Envoi (agrégat) | `OutboundMessage` : `SENDING` \| `SENT` \| `FAILED`, `threadKey` (fil provider), gardes d'état anti-redélivrance ; events `EmailSendRequested` (async) / `EmailSent` / `EmailSendFailed` |
| Frappe d'access token | `AccessTokenMinter` → `OAuthAccessTokenMinter` (une instance par fournisseur, partagée sender+relève) |
| Port d'envoi | `MailSender` (via `MailSenderRegistry`) → `GmailMailSender` / `OutlookMailSender` (Graph : brouillon puis send) / `FakeMailSender` |
| Destinataire | port `RecipientResolver` : contact désigné de la piste, sinon premier contact avec email — RGPD organisation ET contact |
| Frontière vers le brouillon | port `DraftGateway` (tenant explicite, worker-safe) |
| Envoi fait avancer la piste (D3) | politique `AdvanceLeadOnEmailSent` (Prospecting) : candidature → `contact()`, relance → `recordFollowUp()` — conflits absorbés (idempotente) ; **réactive le tenant depuis l'event** (worker) |
| Codes d'échec | `mailbox_unavailable`, `recipient_unavailable`, `contact_not_allowed`, `send_failed` (i18n `mailbox.failures.*`) |
| Journal | types `email_sent` / `email_send_failed` ; `reply` porte l'aperçu (`payload.preview`) |
| Réponse captée | event `ReplyCaptured` (Mailbox → politique Prospection `RecordReplyOnReplyCaptured`) |
| Relève | port `ReplyFetcher` (via `ReplyFetcherRegistry`) → `GmailReplyFetcher` / `OutlookReplyFetcher` (bodyPreview) / `FakeReplyFetcher` + `OpenThreads` ; Scheduler 5 min + geste manuel (ADR-0017) |

## Contexte Sourcing (M3.0 socle + M3.1a moteur RSS livrés ; M3.1b + M3.2 à venir)

| Terme             | Définition |
|-------------------|------------|
| **Annonce candidate** (`CandidateLead`) | Opportunité captée, en **file de tri** avant décision. Immuable une fois triée. |
| **File « À trier »** | Écran listant les annonces `PENDING` ; actions **accepter** / **fusionner** / **rejeter**. |
| **Source** (`Source`) | Provenance fine d'une annonce : `PROZ`, `LINKEDIN`, `TRANSLATORSCAFE`, `RSS`, `MANUAL`. Projetée vers `LeadSource` à la promotion (`MANUAL → JOB_BOARD`, sinon identité). |
| **Statut de tri** (`CandidateStatus`) | `PENDING` (à trier) \| `ACCEPTED` (nouvelle organisation + piste) \| `MERGED` (rattachée à une organisation existante) \| `REJECTED` (écartée). |
| **Accepter** (`AcceptCandidate`) | Crée une **nouvelle** Organisation + une Piste, puis passe l'annonce `ACCEPTED`. |
| **Fusionner** (`MergeCandidate`) | Rattache à une **Organisation existante** : réutilise la piste active si elle existe (note « annonce rattachée »), sinon en crée une. Passe `MERGED`. |
| **Rejeter** (`RejectCandidate`) | Écarte l'annonce (`REJECTED`). |
| **Empreinte de dédoublonnage** (`dedupHash`) | sha256 normalisé de `(Source, externalId?, orgName?, titre)` + index unique `(tenant_id, dedup_hash)` : l'ingestion d'un doublon est un no-op (ADR-0021). |
| **Re-tri interdit** (`CandidateAlreadyTriaged`, 409) | seul `PENDING` est triable ; une annonce déjà triée est immuable. |
| **Relever** (`PollAlertSource`, `POST /sources/poll`) | Interroger la source configurée (tenant courant) et ingérer les annonces trouvées (M3.1a). |
| **Source d'annonces** (`AlertSource`) | Stratégie de lecture par canal : `RssAlertSource` (flux RSS réel, si `SOURCING_RSS_FEED_URL`) / `FakeAlertSource` (démo, défaut). |
| **Brut d'annonce** (`RawAlert`) | Contenu brut conservé d'une annonce (audit / reprocessing) ; `CandidateLead.rawRef` y renvoie. Purge planifiée (D6) : M3.1b. |
| **Alerte email** (`Alert`, M3.2) | Email de notification d'offres (ProZ, LinkedIn, TranslatorsCafe) — *pas encore livré*. |

## Concepts futurs

| Terme       | Définition |
|-------------|------------|
| **Mission** | Travail décroché : volume, échéance, tarif, livrables. Pipeline « après-vente ». Une **Piste gagnée** peut donner une Mission. |
| **Facture** | Document de facturation (mentions micro-entreprise). |

## Value Objects transverses

| Terme             | Définition |
|-------------------|------------|
| **PaireDeLangue** | Couple directionnel (source → cible), ex. `EN→FR`, `ES→FR`. `EN↔FR` = deux paires. |
| **Tarif**         | Montant + devise + **base** (`AU_MOT_SOURCE`, `AU_MOT_CIBLE`, `A_LA_MINUTE`, `FORFAIT`) + minimum de facturation. |
| **Source de piste** (`LeadSource`) | Origine d'une Piste : `DIRECT` (démarchage direct), `REFERRAL` (recommandation), `JOB_BOARD` (annonce générique), `PROZ`, `LINKEDIN`, `TRANSLATORSCAFE`, `RSS`, `OTHER`. Table contractuelle — à ne pas confondre avec `Source` (Sourcing), qui s'y projette. |
| **Priorite**      | `HAUTE`, `MOYENNE`, `BASSE` (code : `Priority` = `LOW` \| `MEDIUM` \| `HIGH`). |
