# Roadmap

Jalons de la V1 (`M0`→`M3`), puis `V2` et `Futur`. La V1 est **livrable par incréments** : la Traductrice a de la valeur dès **M1**, sans attendre M3.

Légende : ✅ acté · 🔜 V1 · 🕓 V2 · 💤 Futur

---

## V1

### M0 — Socle ✅
Fondations techniques.
- [x] Monorepo scaffoldé (`api/` Symfony 7.4 LTS, `app/` Nuxt).
- [x] Docker Compose local : FrankenPHP + Postgres 17 + worker Messenger + Scheduler.
- [x] Squelette hexagonal par bounded context (`Domain`/`Application`/`Infrastructure`) + tranche `Lead` de référence.
- [x] Bus CQRS (Messenger : command sync + event async) + **outbox transactionnel** (transport doctrine).
- [x] Persistance : mapping Doctrine de l'agrégat `Lead` (XML hors `src/`, VO en types DBAL), `DoctrineLeadRepository`.
- [x] Multi-tenancy : `TenantId`, `TenantContext`, `TenantFilter` enregistré et **activé par requête** (au JWT authentifié).
- [x] Qualité : PHPStan (max), CS-Fixer, **Deptrac** (couches DDD), PHPUnit, CI GitHub Actions.
- [x] Auth JWT **access + refresh** : provider entité `User`, claim `tenant_id`, refresh token (gesdinet 2.0) — vérifiés end-to-end.

> **M0 — vérifié en local (Docker)** : `composer install` (Symfony 7.4.14 / PHP 8.5.8),
> clés JWT générées, kernel qui boote, API servie sur `https://localhost:8443/api/v1`
> (docs publiques `200`, entrypoint protégé `401 JWT Token not found`), PHPUnit +
> PHPStan (max) + php-cs-fixer verts, front (Nuxt 4 + Nuxt UI + i18n) build + lint verts.
> Démarrage : `make up` puis `make jwt-keys` (clés hors dépôt).
>
> **M0 clôturé** ✅ — outbox transactionnel (events insérés dans la transaction de la commande
> via le transport doctrine async) + mapping Doctrine de `Lead` (VO en types DBAL) remplaçant
> le repo en mémoire ; persistance + outbox vérifiés end-to-end.
>
> Refresh token JWT : ✅ fait (gesdinet 2.0, entité concrète + mapping XML, `/api/v1/token/refresh`).

### M1 — Cœur prospection 🔜 *(première version utilisable)*

> 📐 Note de conception détaillée : [`docs/design/M1-conception.md`](design/M1-conception.md).
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
