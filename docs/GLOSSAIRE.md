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
| Objectif hebdomadaire | `WeeklyGoal` |
| Série | `Streak` |
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
| Alerte | `Alert` |
| Parser | `AlertParser` |
| Piste candidate | `CandidateLead` |
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
| RelancePlanifiée | `FollowUpScheduled` |
| RelanceEnvoyée | `FollowUpSent` |
| RéponseReçue | `ReplyReceived` |
| StatutChangé | `StatusChanged` |
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

## Contexte Sourcing (V2/M3)

| Terme             | Définition |
|-------------------|------------|
| **Alerte**        | Email de notification d'offres (ProZ, LinkedIn, TranslatorsCafe) ou flux RSS. |
| **Parser**        | Stratégie de lecture d'une source d'alerte donnée. |
| **Piste candidate**| Opportunité issue de l'ingestion, en **file de tri** avant acceptation. |

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
| **Source**        | Origine d'une Piste : `DEMARCHAGE_DIRECT`, `PROZ`, `LINKEDIN`, `TRANSLATORSCAFE`, `RSS`, `RECOMMANDATION`, `IMPORT`. |
| **Priorite**      | `HAUTE`, `MOYENNE`, `BASSE`. |
