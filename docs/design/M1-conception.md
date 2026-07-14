# M1 — Note de conception (le cœur métier)

> Statut : **livré au complet** (M1.1→M1.5, 2026-07-13/14 ; DoD §14 tenue, décisions §13 soldées) · Prérequis : M0 clôturé.
> Langue : métier en français, code en anglais (cf. [ADR-0010](../architecture/decisions/0010-langue-de-nommage.md) et [glossaire](../GLOSSAIRE.md)).

## 1. Objectif & résultat attendu

Donner à la traductrice un outil **réellement utilisable au quotidien** : constituer une base de cibles (éditeurs, labos AV, agences), suivre chaque **Piste** dans un pipeline, être **relancée automatiquement**, et **tenir un rythme** de démarchage malgré son temps limité — le tout scoped par tenant.

**Résultat M1** : elle se connecte, importe/saisit des Organisations & Contacts, crée des Pistes, les fait avancer dans un kanban, génère des messages (IA), planifie/voit ses relances, et suit sa régularité (objectif hebdo + série).

Priorité métier (rappel) : **édition + audiovisuel** (démarchage direct), technique en secondaire.

## 2. Périmètre & découpage en slices livrables

On garde la discipline « vertical slice » : chaque slice va du domaine jusqu'à l'écran, testée + CI verte.

| Slice | Contenu | Valeur |
|---|---|---|
| **M1.1 — Répertoire** | Agrégat `Organization` (+ `Contact`), CRUD, API, écran liste/fiche, import CSV | Base de cibles exploitable |
| **M1.2 — Pipeline Lead** | `Lead` enrichi, transitions, kanban, fiche piste, journal d'interactions | Suivi commercial |
| **M1.3 — Relances & régularité** | `FollowUp` (dans l'agrégat), cadence, Scheduler, objectif hebdo + série, « à faire aujourd'hui » | Le nerf de la guerre |
| **M1.4 — Rédaction assistée** | Port `MessageGenerator` (ACL Claude), `GenerateDraft`, `Template`, éditeur de brouillon | Gain de temps rédaction |
| **M1.5 — Tableau de bord** | KPIs (taux de réponse, conversion, volume/semaine) | Pilotage |

Auth/login front est un prérequis transverse de M1.1 (page de connexion, stockage du token, `useApi` authentifié).

## 3. Contexte Répertoire (Directory) — *Supporting*

### Agrégats & VOs
- **`Organization`** (racine) : `OrganizationId`, `TenantId`, `name`, `type` (`OrganizationType`: `PUBLISHER` | `AV_STUDIO` | `AGENCY` | `OTHER`), `website?`, `country?`, `workingLanguages[]`, `segments[]`, `notes?`, `doNotContact` (bool, RGPD).
  - **contient** `Contact` (entités) : `ContactId`, `fullName`, `role?`, `email?` (VO `EmailAddress`), `phone?`, `linkedinUrl?`, `preferredLanguage?`, `doNotContact`.
  - Invariants : email unique par organisation ; `TenantId` cohérent ; `doNotContact` propagé (bloque tout envoi côté Passerelle plus tard).
- VOs : `OrganizationType`, `EmailAddress`, `CountryCode`, réutilise `LanguagePair`/`Segment`.

### Application
- Commands : `CreateOrganization`, `UpdateOrganization`, `AddContact`, `UpdateContact`, `RemoveContact`, `MarkDoNotContact`, `ImportOrganizationsCsv`.
- Queries : `ListOrganizations` (filtres : type, segment, langue, texte), `GetOrganization`.
- **Import CSV** : commande qui parse un CSV (colonnes documentées), crée/complète des Organisations + Contacts, avec **dédoublonnage léger** (par nom normalisé + domaine email). Rapport d'import (créées / fusionnées / ignorées).

### Infrastructure
- `DoctrineOrganizationRepository` ; mapping **XML** dans `config/doctrine/directory/` (domaine pur). Contacts mappés en collection (one-to-many, cascade).
- Resources API Platform (DTO) + State Providers/Processors → bus.

## 4. Contexte Prospection — pipeline complet (*Core*)

### `Lead` enrichi (par rapport au squelette M0)
Champs à ajouter à l'agrégat : `contactId?`, `paireDeLangue` (`LanguagePair`), `source` (`LeadSource`), `priority` (`Priority`), `estimatedValue?` (`Rate`), `nextAction?` (date + libellé, dénormalisé), `followUps[]` (entités **dans** l'agrégat), horodatages (créée, dernier contact, dernière réponse).

### Machine à états (déjà posée en M0)
`TO_CONTACT → CONTACTED → FOLLOWED_UP⭯ → IN_DISCUSSION → SAMPLE_TEST → WON` · `LOST` · `PAUSED` (cf. [DOMAIN-MODEL](../architecture/DOMAIN-MODEL.md)). Transition auto `→ IN_DISCUSSION` sur réponse entrante (Passerelle = M2).

### Application — commands
`CreateLead`, `ContactLead` *(existe)*, `ScheduleFollowUp`, `RecordReply`, `MoveToSampleTest`, `MarkWon`, `MarkLost`, `PauseLead`, `ResumeLead`, `AddNote`.
Chaque command handler : charge l'agrégat, appelle la méthode métier, sauvegarde, publie les events (outbox). Les handlers implémentent le marqueur `CommandHandler` (pas d'attribut framework — cf. deptrac).

### Invariants clés (rappel + ajouts)
1. Transitions limitées au graphe. 2. Réponse reçue → annule les relances en attente + `IN_DISCUSSION`. 3. États terminaux → plus de relance planifiable. 4. Pas deux relances actives à la même échéance. 5. `TenantId` cohérent Piste/Organisation/Contact.

### Interactions (journal)
Projection **append-only** `interaction` alimentée par les domain events (`LeadContacted`, `FollowUpScheduled`, `ReplyReceived`, `NoteAdded`, `StatusChanged`…) + commande explicite `AddNote`. La **timeline** d'une piste = requête sur cette table. Pas dans l'agrégat (cf. [ADR-0003](../architecture/decisions/0003-etat-classique-domain-events.md)).

## 5. Régularité — objectif hebdo, série, « à faire aujourd'hui »

- **Cible** : `WeeklyGoal(targetContacts)` porté par le Profil (contexte Account).
- **Progression** : read model projeté depuis `LeadContacted` sur la fenêtre semaine (fuseau utilisateur → UTC, cf. [ADR-0011](../architecture/decisions/0011-preoccupations-transverses.md)).
- **Série (streak)** : nombre de semaines consécutives où l'objectif est atteint (projection).
- **« À faire aujourd'hui »** : query = relances **dues** (date ≤ aujourd'hui, piste non terminale) + pistes `TO_CONTACT` prioritaires. C'est l'écran d'entrée quotidien.
- **Scheduler** : un message récurrent (Symfony Scheduler, transport `scheduler_default` déjà câblé) recalcule les relances dues / notifie. Les relances elles-mêmes ne « s'envoient » pas seules en M1 (envoi = M2) : M1 **rappelle** et prépare le brouillon.

## 6. Rédaction assistée (Drafting) — *Supporting*, ACL Claude

- **Port** (Application) : `MessageGenerator::generate(DraftRequest): DraftResult` — `DraftRequest` = profil (bio, langues, spécialités, tarifs, signature) + cible (Organisation/Contact) + segment + langue cible + type (mail candidature / lettre de motivation / relance).
- **Adapter** (Infrastructure) : `ClaudeMessageGenerator` réalise le port (API Claude), **appel asynchrone** via Messenger pour ne pas bloquer le HTTP ; ACL stricte (le domaine ignore « Claude »).
- **`Template`** (agrégat) : gabarits réutilisables par segment/langue, variables interpolées.
- **Garde-fou** : *draft-first* — la génération produit un **brouillon éditable**, jamais un envoi. Distinction UI locale ≠ **langue cible du contenu** (le prospect).
- Modèle Claude : dernier modèle adapté (cf. skill `claude-api` au moment de l'implémentation).

## 7. Read models & projections

| Read model | Source | Usage |
|---|---|---|
| `interaction` (journal) | events | timeline fiche piste |
| Pipeline board | table `lead` (query) | kanban (group by statut) |
| Progression / série | events `LeadContacted` | régularité |
| KPIs dashboard | agrégations SQL sur `lead`/`interaction` | tableau de bord |

Projections alimentées par des **handlers d'events async** (event.bus) ; idempotence à gérer (clé event). Les vues simples (kanban) restent des **queries** directes sur le write model (pas de sur-ingénierie).

## 8. API (API Platform) — conventions

- Resources = **DTO** sous `<Contexte>/Infrastructure/ApiResource/` (attributs PHP), **jamais** les agrégats/entités Doctrine.
- **State Providers** (lecture → QueryBus) / **State Processors** (écriture → CommandBus).
- Toutes sous `/api/v1`, protégées JWT (sauf login/docs), **auto-scopées par tenant** (TenantFilter actif).
- Erreurs **RFC 7807**. Pagination/tri/filtres via API Platform. OpenAPI tenu à jour (contract sync en CI → `openapi.json`).
- Style : ressources **orientées cas d'usage** quand utile (ex. `POST /leads/{id}/contact`) plutôt que CRUD pur, pour coller aux commandes métier.

## 9. Frontend (Nuxt + Nuxt UI)

- **Auth** : page de connexion (`/login`), échange `login_check` → JWT, store Pinia (`auth`), `useApi` ajoute le `Authorization: Bearer`. (Refresh token = suivi post-M0.)
- **Écrans** :
  - Répertoire : liste filtrable (table Nuxt UI), fiche Organisation + Contacts, import CSV.
  - **Kanban pipeline** : colonnes = statuts ; drag-and-drop → `POST /leads/{id}/{transition}` ; carte = piste (segment, langue, priorité, prochaine action). Kanban via primitives **Reka UI** + Tailwind (ou lib compatible), a11y clavier.
  - Fiche Piste : timeline interactions, actions (contacter, relancer, gagner/perdre…), **éditeur de brouillon** (génération IA + édition).
  - **À faire aujourd'hui** : liste priorisée (relances dues + à contacter).
  - Tableau de bord : KPIs, objectif hebdo + série.
  - Profil/Réglages : langues, spécialités, tarifs, signature, **objectif hebdo**, préférences (locale/thème/TZ).
- i18n FR/EN, thème clair/sombre (déjà en place). Formats dates/nombres/monnaie via Intl.

## 10. Tenancy, sécurité, RGPD

- Chaque agrégat porte `TenantId` ; les commandes le fixent depuis l'utilisateur courant ; les lectures sont filtrées automatiquement (TenantFilter). Tests d'isolation obligatoires.
- **RGPD** : `doNotContact` sur Organisation/Contact (bloque futur envoi), traçabilité de la source, export/suppression d'un contact, base légale intérêt légitime B2B (cf. conception). Purge des pistes mortes = plus tard.

## 11. Tests

- **Domaine** (unitaire, sans DB) : machine à états `Lead`, invariants relances/réponse, `Organization`/`Contact`, VOs. C'est le gros du filet.
- **Application** : handlers avec repos in-memory (réutiliser `InMemoryLeadRepository`, en ajouter pour Organization).
- **Intégration** : repos Doctrine + **isolation tenant** (2 tenants), import CSV.
- **API** (fonctionnel) : parcours clés authentifiés (créer org → créer lead → transitions).
- **Front** : vitest (stores, utils), composants clés ; Playwright e2e en option plus tard.
- CI : ajouter (si besoin) une **testsuite d'intégration** avec le service Postgres + migration du schéma de test.

## 12. Nouveaux ADR à acter en M1

- **ADR-0012** — API : resources DTO orientées cas d'usage + State Providers/Processors délégant au bus (formaliser le pattern concret).
- **ADR-0013** — Stratégie de read models/projections (projection async vs query directe ; idempotence).
- **ADR-0014** — Intégration IA (Claude) : ACL, async, gestion coûts/erreurs/timeouts, pas de donnée sensible.
- **ADR-0015** — Import CSV & dédoublonnage (format, règles de matching).

## 13. Décisions ouvertes — **soldées** (résolutions tracées à la clôture de M1)

1. **Périmètre M1** : Rédaction assistée **incluse** dans M1 (livrée en M1.4). ✔
2. **Kanban** : colonnes + boutons de transition, **sans drag-and-drop** en V1 (micro-décision
   M1.2 n°3 — le DnD reste un report tracé en ROADMAP). ✔
3. **Read models** : **oui** — seul le journal `interaction` est projeté, tout le reste en
   queries directes fail-closed (ADR-0013, confirmé jusqu'au tableau de bord M1.5). ✔
4. **API** : **ressources orientées cas d'usage** (`/leads/{id}/contact|reply|…`) — 409 sur
   transition interdite. ✔
5. **Refresh token** : traité **avant M1** (rotation single_use + invalidation, durci en
   revue M1.1). ✔

## 14. Definition of Done — M1

- [x] Se connecter (login) et rester authentifié (token).
- [x] Créer/importer Organisations & Contacts (scoping tenant vérifié).
- [x] Créer des Pistes, les faire transiter (kanban), voir la timeline.
- [x] Planifier des relances, voir « à faire aujourd'hui », suivre objectif hebdo + série.
- [x] Générer un brouillon de mail/lettre (FR/EN/ES) éditable.
- [x] Tableau de bord avec taux de réponse / conversion / volume.
- [x] Domaine testé, CI verte (phpstan max, deptrac ×2, phpunit, front), OpenAPI à jour.
