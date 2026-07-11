# Roadmap

Jalons de la V1 (`M0`→`M3`), puis `V2` et `Futur`. La V1 est **livrable par incréments** : la Traductrice a de la valeur dès **M1**, sans attendre M3.

Légende : ✅ acté · 🔜 V1 · 🕓 V2 · 💤 Futur

---

## V1

### M0 — Socle 🔜
Fondations techniques.
- [x] Monorepo scaffoldé (`api/` Symfony 8, `app/` Nuxt).
- [x] Docker Compose local : FrankenPHP + Postgres 17 + worker Messenger + Scheduler.
- [x] Squelette hexagonal par bounded context (`Domain`/`Application`/`Infrastructure`) + tranche `Lead` de référence.
- [x] Bus CQRS (Messenger : command sync + event async) *(relais outbox à finaliser)*.
- [x] Multi-tenancy : `TenantId`, `TenantContext`, `TenantFilter` *(listener + enregistrement du filtre à finaliser)*.
- [x] Qualité : PHPStan (max), CS-Fixer, PHPUnit (test de domaine), CI GitHub Actions.
- [ ] Auth JWT access + refresh : config posée (provider mémoire) → **basculer sur provider entité + claims `tenant_id`**.

> **M0 — à finaliser au premier `composer install`** (réseau requis) :
> 1. `make build && make install` (composer dans le conteneur) puis `make front-install`.
> 2. `make jwt-keys` (génère la paire de clés Lexik).
> 3. Contexte `Account` : entité `SecurityUser` + provider, listener `JWTCreatedEvent` (claim `tenant_id`) alimentant le `TenantContext`.
> 4. Enregistrer le `TenantFilter` (doctrine `filters`) + listener `kernel.request` qui l'active avec le tenant courant.
> 5. Relais **transactional outbox** (events stockés → transport `async`).
> 6. Mapping Doctrine des agrégats (XML en `Infrastructure/Persistence`) + première migration.

### M1 — Cœur prospection 🔜 *(première version utilisable)*
- [ ] **Répertoire** : CRUD Organisation + Contact, tags, **import CSV**.
- [ ] **Piste** : création, machine à états, kanban + liste.
- [ ] **Interactions** : journal (projection) + saisie manuelle (note, appel).
- [ ] **Relances** : planification par statut, écran « à faire aujourd'hui », cadences.
- [ ] **Régularité** : objectif hebdomadaire, progression, **série** (choix : central dès M1).
- [ ] **Rédaction assistée** : génération mail + lettre (FR/EN/ES), ton par segment, réécriture, Modèles.
- [ ] **Tableau de bord** : à contacter, relances dues, taux de réponse, conversion.

### M2 — Passerelle email 🔜
- [ ] Connexion OAuth **Gmail + Outlook**, tokens chiffrés.
- [ ] Envoi depuis la boîte de la Traductrice (signature), statut d'envoi.
- [ ] Threading `Message-ID`/`References` → captation des **réponses** → `Piste.enregistrerReponse()`.
- [ ] Gestion **opt-out** (RGPD). *Pas de tracking d'ouverture.*
- [ ] Relances contextualisées (reprennent l'historique de la Piste).

### M3 — Ingestion 🔜
- [ ] **Parsers** par source (Strategy) : alertes ProZ, LinkedIn, TranslatorsCafe ; RSS.
- [ ] File de tri (Piste candidate + Organisation) → écran accepter/rejeter/fusionner.
- [ ] **Dédoublonnage** Organisations/Contacts.
- [ ] Conservation de l'email brut (reprocessing).

---

## V2 🕓
- [ ] Pipeline **configurable** (statuts personnalisables).
- [ ] **Séquences** de relance multi-étapes.
- [ ] **Annuaire** pré-rempli (éditeurs FR, labos AV via ATAA, agences).
- [ ] Ouverture SaaS : inscription publique, billing/abonnement, multi-utilisateurs par workspace.
- [ ] RGPD : registre de traitement + DPA activés.

---

## Futur 💤
- [ ] **Gestion de mission** (Core n°2) : volume, deadline, tarif, livrables, calendrier ; lien Piste gagnée → Mission.
- [ ] **Facturation** : devis, factures (mentions micro-entreprise, art. 293 B), suivi paiement, **plafonds de CA**, export compta.
- [ ] Enrichissement auto de contacts, aide à la négociation tarifaire, réponses assistées.
- [ ] Application mobile (l'auth JWT le permet).

---

## Décisions différées
- **Hébergement de production** : local Docker Compose d'abord ; VPS vs PaaS FR tranché au premier déploiement.
- **Nom définitif** du produit (« Plume » = code provisoire).
