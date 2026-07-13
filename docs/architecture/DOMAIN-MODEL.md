# Modèle de domaine

Modélisation tactique DDD. Détaille le **cœur Prospection** ; survole les autres contextes (à approfondir à leur jalon).

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
├─ ValeurEstimee?       (VO: Tarif)
├─ Statut               (VO: StatutPipeline)
├─ ProchaineAction?     (date + libellé — dénormalisé pour « à faire »)
├─ Relances[]           (Entités DANS l'agrégat)
└─ dates (créée, dernier contact, dernière réponse)
```

**Méthodes métier** (chacune émet un ou des domain events) :

| Méthode | Effet | Event(s) |
|---------|-------|----------|
| `contacter()` | marque la Piste contactée | `PisteContactee` |
| `planifierRelance(date)` | ajoute une Relance | `RelancePlanifiee` |
| `enregistrerReponse()` | **annule les relances en attente**, passe en `EN_DISCUSSION` | `ReponseRecue`, `StatutChange` |
| `passerAuTest()` | → `TEST_ECHANTILLON` | `StatutChange` |
| `marquerGagnee()` / `marquerPerdue()` | états terminaux | `PisteGagnee` / `PistePerdue` |
| `mettreEnPause()` / `reprendre()` | met en veille / réactive | `StatutChange` |
| `ajouterNote(texte)` | note manuelle | `NoteAjoutee` |

### Machine à états (pipeline opinioné, figé en V1)

```
À_CONTACTER ─► CONTACTÉE ─► RELANCÉE ⭯ ─► EN_DISCUSSION ─► TEST/ÉCHANTILLON ─► GAGNÉE
                  │            │              │                    │           (→ Mission, futur)
                  └────────────┴──────────────┴────────────────────┴────► PERDUE
       (tout état actif, sauf TEST_ECHANTILLON — phase courte non interruptible) ◄─► EN_PAUSE
```

- Transition **automatique** : réponse entrante depuis `CONTACTÉE`/`RELANCÉE` → `EN_DISCUSSION` (déclenchée par la Passerelle email).
- `GAGNÉE` / `PERDUE` = terminaux → plus de relance planifiable.

### Invariants portés par `Piste`
1. Seules les transitions du graphe sont permises (sinon exception domaine).
2. Une réponse reçue **annule** toutes les relances en attente.
3. États terminaux → aucune relance planifiable, aucune transition sortante (hors réouverture explicite).
4. Pas deux relances actives à la même échéance.
5. `TenantId` de la Piste = celui de l'Organisation/Contact référencés.
6. `ValeurEstimee ≥ 0`.

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
| **Source** | `DEMARCHAGE_DIRECT` \| `PROZ` \| `LINKEDIN` \| `TRANSLATORSCAFE` \| `RSS` \| `RECOMMANDATION` \| `IMPORT`. |
| **Priorite** | `HAUTE` \| `MOYENNE` \| `BASSE`. |
| **Tarif** | Montant + devise + base (`AU_MOT_SOURCE`/`AU_MOT_CIBLE`/`A_LA_MINUTE`/`FORFAIT`) + minimum. |
| **Money** | Montant + devise. |
| **AdresseEmail** | Validée. |
| **CadenceRelance** | Suite de délais, ex. `[J+7, J+21, J+45]`. |

---

## Domain events (colonne vertébrale du découplage)

`PisteCreee` · `PisteContactee` · `RelancePlanifiee` · `RelanceEnvoyee` · `ReponseRecue` · `StatutChange` · `PisteGagnee` · `PistePerdue` · `NoteAjoutee`

Consommateurs : journal d'Interactions, KPIs du tableau de bord, progression/série, notifications.

---

## Autres contextes (survol)

### Répertoire
- **`Organisation`** (racine) **contient** ses **`Contact`** (entités) : peu nombreux, édités ensemble.
- Invariants : email unique par organisation, tenant cohérent.
- Dédoublonnage à l'ingestion (M3).

### Rédaction assistée
- Pas d'état : un **port** `GenerateurDeMessage` (ACL → Claude) + un agrégat **`Modele`** (CRUD templates).
- La génération renvoie un **Brouillon** (transient) que la Piste peut stocker en « brouillon en cours ».

### Passerelle email
- Agrégat **`CompteEmailConnecte`** (provider, tokens OAuth **chiffrés**, statut).
- Ports `EnvoiEmail` / `LectureBoite` ; adapters Gmail / Outlook.
- Entrant → service applicatif qui *matche* via `Message-ID`/`References`, puis appelle `Piste.enregistrerReponse()`.

### Sourcing (M3)
- Agrégat **`PisteCandidate`** en file de tri.
- Domain services **`ParserAlerte`** (Strategy) par source.
- Acceptation → crée Organisation (dédup) + Piste.

### Gestion de mission (futur)
- **`Mission`** : volume, deadline, tarif, livrables, statut. Lien `PisteGagnee` → `Mission`.
