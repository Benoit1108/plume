# Pré-V2 — note de cadrage (avant ouverture du cadrage V2)

> Statut : **validée** (2026-07-22, décisions D1→D4 arbitrées). Prérequis : M3 clôturé + revue
> fin M3 remédiée (lots A→E). Objet : solder ce qui doit l'être **avant** la V2 — mail réel,
> dette front (ADR-0022), durcissement multi-tenant — et acter la rétrospective d'architecture.

## 1. Décisions arbitrées

| # | Décision | Choix |
|---|---|---|
| D1 | Compte mail de test | **Gmail seul d'abord** (app OAuth Google en mode test ; Outlook plus tard, les registres le permettent sans rework). Jamais le compte réel de l'utilisatrice avant validation complète. |
| D2 | Couche server-state front | **TanStack Query** (`@tanstack/vue-query`) — mature, cache + invalidation par clé + mutations. Migration progressive (Sourcing/Réglages d'abord). |
| D3 | Rendu front | **SPA** (`ssr: false`). L'app est 100 % derrière login (zéro SEO) et l'auth httpOnly n'alimente pas le rendu serveur : les deux seuls gains du SSR ne s'appliquent pas. Supprime la classe de bugs d'hydratation et le relais de cookie vers un étage Node. Réversible par route (`routeRules`) si des pages publiques arrivent en V2. |
| D4 | Isolation multi-tenant base | **RLS Postgres maintenant** (policies `tenant_id` sur les tables tenantées) + middleware Messenger tenant (SQLFilter activé dans le worker, `TenantContext` réinitialisé entre messages). La base refuse le cross-tenant quel que soit le chemin. |

## 2. Chantiers (dans l'ordre)

1. **Back multi-tenant & robustesse** : middleware Messenger tenant + RLS (D4) ; relève
   manuelle `POST /sources/poll` en asynchrone (vrai 202).
2. **Mail réel** *(dès que l'app OAuth Google de test est prête — action Benoit)* :
   `GmailAlertEmailFetcher` (lecture du seul label « Plume/Alertes » — `gmail.readonly` déjà
   dans les scopes M2.1, aucun re-consentement) ; validation **M2 en réel** (OAuth + envoi +
   captation de réponse) sur le compte de test ; **parsers fins** ProZ/TC/LinkedIn sur les vrais
   emails d'alerte reçus par ce compte. Outlook plus tard : équivalent = **dossier** (pas de
   labels dans Graph) — préciser l'ADR-0017 d'une ligne à ce moment-là.
3. **Front** : TanStack Query (D2) + endpoint `count` pour le badge (fin de l'effet de bord de
   `queue()`), types générés `openapi-typescript` + test CI de non-dérive, bascule SPA (D3),
   test vitest de **parité i18n**.
4. **Opportuniste** : harmonisation des patrons d'adaptateurs, `AbstractStringIdType`.
5. → **Cadrage V2** (ROADMAP § V2 + Futur, gardés obligatoires ; rouvrir ADR-0022).

## 3. Rétrospective d'architecture (demandée — bons/mauvais choix, limites à venir)

### Les grands non-choix — confirmés bons
- **Pas d'event sourcing** (ADR-0003) ✅ — l'audit métier est couvert par le journal
  `interaction`. Sacrifice réel assumé : les events sont **éphémères** (outbox consommée) →
  pas de reconstruction rétroactive de projections. Porte de sortie **additive** si besoin :
  archiver les events en sortie d'outbox (event store light). YAGNI aujourd'hui.
- **Pas de microservices** (ADR-0001) ✅ — frontières **exécutables** (deptrac ×2, ports,
  contrat d'events testé) : l'extraction reste possible, contrairement aux monolithes à
  frontières décoratives. Étape intermédiaire avant tout microservice : **files Messenger
  par contexte** (isolation de charge sans distribution).

### Audit « maison vs éprouvé » du back
- ✅ Solide là où le maison tue : auth (lexik+gesdinet), crypto (sodium), outbox (Messenger
  doctrine même connexion — canonique), rate limiter/scheduler/uid (Symfony), contrat OpenAPI
  (API Platform).
- ✅ Maison à raison : ports CQRS (10 lignes sur Messenger), multi-tenant (pas de bundle
  Symfony de référence ; RLS ajoute la garantie base), ACL Gmail/Graph en HTTP fin (éviter
  `google/apiclient`, obèse).
- ✅ **Le raté (CORRIGÉ, chantier 2)** : le parser RSS maison ne lisait que RSS 2.0
  (`channel->item`) ; un flux **Atom** (`feed->entry`, fréquent pour les offres) rendait **zéro
  annonce en silence**. `RssAlertSource` délègue désormais à **`laminas/laminas-feed`** (RSS 2.0
  **et** Atom, interface uniforme), **derrière le port `AlertSource` inchangé**. On garde le fetch
  maison (garde SSRF `NoPrivateNetworkHttpClient` + bornes de taille) puis `Reader::importString`
  (jamais `import($uri)`, qui échapperait à la garde SSRF). Test Atom ajouté.
- ⚠️ À surveiller : flux OAuth maison (state HMAC + échange de tokens — audité M2 ; si bug de
  refresh en réel → `league/oauth2-client` derrière `MailboxConnector`) ; CSV maison
  (`league/csv` si l'import grossit) ; recherche `LIKE` (→ **Postgres FTS natif** quand
  l'Annuaire arrive).
- 🔮 À ne jamais faire maison (futur) : **`brick/money`** dès que Tarif/Facturation arrivent ;
  PDF de facture via une lib ; MIME brut via `zbateson/mail-mime-parser` si besoin.

### Limites à l'horizon — des rendez-vous datés, pas des murs
Plafond honnête de l'archi actuelle (monolithe FrankenPHP + 1 Postgres + workers) : plusieurs
milliers de tenants pour une charge CRM. Ce qui casse en premier, avec parade locale :

| Ordre | Limite | Déclencheur | Parade |
|---|---|---|---|
| 1 | Polling mail (quotas) | dizaines de boîtes | push Gmail `watch` / Graph subscriptions (ADR-0017) |
| 2 | Scheduler/rate-limiter mono-nœud | multi-instance | lock partagé + storage Redis |
| 3 | Dashboard sur gros journal | lenteur mesurée | projections dédiées (events déjà là) |
| 4 | Contacts JSONB (ADR-0012) | feature **Annuaire V2** | sortir `Contact` en table |
| 5 | Charge inter-contextes | un contexte sature les workers | files Messenger par contexte |

### Erreurs structurelles identifiées (et leur statut)
- Front : **SSR subi** (→ D3, corrigé au chantier 3) et **types dupliqués** malgré OpenAPI
  (→ chantier 3). Les deux auraient dû être tranchés dès M1/M2.
- Back : **parser RSS mono-dialecte** (✅ corrigé au chantier 2 — laminas-feed). Rien d'autre de structurel.

## 4. Après le pré-V2 (gardés obligatoires)
- **Point D** (prérequis techniques V2) : hébergement de production (VPS vs PaaS FR), apps
  OAuth réelles (au-delà du mode test), nom définitif du produit.
- **Futur post-V2** : gestion de mission, facturation micro-entreprise (avec `brick/money`),
  enrichissement de contacts, appli mobile.
