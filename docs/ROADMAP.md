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

### M1 — Cœur prospection ✅ (revue de santé fin M1 appliquée) *(première version utilisable)*

> 📐 Note de conception détaillée : [`docs/design/M1-conception.md`](design/M1-conception.md)
> (découpage M1.1 → M1.5). Revue de santé fin M1.1 : [`docs/reviews/`](reviews/).
- [x] **M1.1 — Répertoire** ✅ : CRUD Organisation + Contact (unicité du nom par tenant,
      « ne pas contacter » réversible et tracé), **import CSV** (borné, dédoublonné),
      écrans responsives i18n FR/EN, pagination, pyramide de tests complète
      (domaine / application / fonctionnel Postgres avec isolation tenant).
      *Reportés : tags (non conçus), filtres segment/langue (JSONB, attendre le volume).*
- [x] **M1.2 — Piste** ✅ : Lead complet (paire de langues, origine, priorité, pause/reprise),
      machine à états exhaustive, API cas d'usage (409 sur transition interdite), garde RGPD
      via le gateway Répertoire, une piste active par organisation, **journal d'interactions**
      (première projection asynchrone, idempotente), kanban + fiche avec timeline + création
      depuis l'organisation. *Reporté : drag-and-drop (itération 2), FOLLOWED_UP (M1.3).*
- [x] **M1.3 — Relances & régularité** ✅ : `FollowUp` dans l'agrégat (cadence auto
      J+7/21/45, annulation sur réponse/terminal/pause, une seule en attente),
      **écran « Aujourd'hui »** (accueil : relances dues + à contacter + actions rapides),
      objectif hebdo + **série** calculés sur le journal, profil (Account) naissant,
      API cas d'usage relances + /today + /profile. *(Le journal était déjà M1.2.)*
- [x] **M1.4 — Rédaction assistée** ✅ : contexte `Drafting` (agrégats `Draft` + `Template`),
      port `MessageGenerator` (canned gratuit par défaut, Claude par env — ACL), génération
      **asynchrone** (worker, GENERATING → READY/FAILED, garde RGPD re-vérifiée), profil
      étendu (bio/spécialités/signature), section Brouillons sur la fiche piste (éditeur,
      **Copier**, régénérer, supprimer), page Modèles (3 gabarits seedés + CRUD), page
      Réglages, journal `draft_generated`. *Reportés : réécriture itérative type chat (V2),
      envoi réel (M2), tarifs dans le profil (futur).*
- [x] **M1.5 — Tableau de bord** ✅ : slice 100 % lecture (`Dashboard` read model DBAL
      fail-closed) — taux de réponse et conversion (gagnées/décidées) calculés **par piste
      sur le journal**, répartition du pipeline, activité des 8 dernières semaines vs
      objectif, résultats par segment ; page `/dashboard` (graphes maison accessibles).
      *Reportés → M2 : délais de réponse, valeur estimée, filtres de période, export.*

### M2 — Passerelle email 🔜
- [ ] Connexion OAuth **Gmail + Outlook**, tokens chiffrés.
- [ ] Envoi depuis la boîte de la Traductrice (signature), statut d'envoi.
- [ ] Threading `Message-ID`/`References` → captation des **réponses** → `Piste.enregistrerReponse()`.
- [ ] Gestion **opt-out** (RGPD). *Pas de tracking d'ouverture.*
- [ ] Relances contextualisées (reprennent l'historique de la Piste).
- [x] Cookies tokens **httpOnly** ✅ *(M2.0, 2026-07-14 : posés/rotés/effacés par l'API,
      `/me`, même-origine via proxy Nitro, `vue/no-v-html` bloquant)*.
- [x] `recordReply()` **idempotent** ✅ *(M2.3 — les relèves répétées sont des no-op)*.
- [x] Interpolation locale de `{{contact}}` ✅ *(M2.4 — le nom du contact ne part plus chez
      Anthropic, ADR-0014 soldé)* ; rétention du journal **tranchée et tracée** *(ADR-0017 :
      le journal suit la vie de la piste ; pas de rétention temporelle en V1)*.
- [ ] **Tableau de bord enrichi** *(reports actés M1.5)* : délais moyens de première réponse
      (pertinents une fois les réponses captées automatiquement), valeur estimée des pistes
      (`estimatedValue`, différé depuis M1.2), filtres de période, export.

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
