# Revue de santé — fin M1.1 (2026-07-13)

> **But** : état des lieux complet avant M1.2. Quatre audits indépendants (architecture DDD,
> sécurité/API, frontend, docs/process) + une série de tests comportementaux contre l'API
> réelle. Chaque constat est vérifié (fichier:ligne ou test HTTP), rien n'est spéculatif.

## Verdict global

| Axe | Note | Résumé |
|---|---|---|
| Architecture backend (DDD/hexa/CQRS) | **7 / 10** | Ossature solide et vérifiable, dette de finition (exceptions, events, read models) |
| Sécurité / API REST / données | **6,5 / 10** | Fondations saines, mais pas prêt prod : rate limiting, 500 métier, import non borné |
| Frontend (Nuxt) | **6,5 / 10** | Socle propre (auth, TS strict, config), trous UX/a11y/tests |
| Docs / CI / process | **6 / 10** | Excellent squelette, mais docs d'entrée périmées et DoD M1.1 non tenue |

**Direction : bonne.** Les choix structurants (hexagonal, CQRS, outbox, tenancy par SQLFilter,
DTO API, CI avec deptrac + diff OpenAPI) sont corrects **et réellement tenus** — c'est vérifié,
pas déclaratif. Ce qui manque est périphérique et corrigeable à faible coût *maintenant* ;
ce sera cher après M2.

## Tests comportementaux (preuves)

| Test HTTP | Résultat | Verdict |
|---|---|---|
| POST org, pays `France` | 422 propre (Assert) | ✅ |
| POST org, type invalide | 422 propre (Assert) | ✅ |
| POST org, langue `français` | **500** (VO non mappé) | 🔴 |
| POST contact, email en doublon | **500** (`DuplicateContactEmail`) | 🔴 |
| POST 2× même nom d'organisation | 201 + 201 (doublon accepté) | 🟠 incohérent avec l'import |
| GET sans token | 401 | ✅ |
| GET /contacts/{id} (route parasite ?) | 404 — absente | ✅ |
| Import CSV 60 000 lignes | **Fatal error mémoire (128 Mo)**, ~710 orgs créées avant le crash | 🔴 |
| Refresh token rejoué | 200 (réutilisable, pas de rotation) | 🟡 |

*(Base de dev nettoyée après les tests : les 710 organisations parasites ont été supprimées.)*

---

## P0 — corriger avant M1.2 (bugs réels, risques immédiats)

1. **Import CSV non borné → épuisement mémoire + import partiel** (prouvé).
   `OrganizationImportResource::$content` n'a que `NotBlank` ; le processor dispatche sans plafond.
   → `Assert\Length(max)` sur le contenu (~1 Mo) + plafond de lignes (ex. 1 000) + message 422 clair.
2. **Exceptions domaine → HTTP 500** (prouvé : langue invalide, email en doublon).
   Aucun `exception_to_status` (api_platform.yaml), aucune base d'exception domaine commune.
   → hiérarchie `Shared\Domain\Exception` (`DomainError`, `NotFound`, `Conflict`, `InvalidValue`),
   mapping 409/422/404, et contraintes Assert manquantes en couche API
   (`website`/`linkedinUrl` : Url ; `workingLanguages`/`preferredLanguage` : format `[a-z]{2}` ;
   `segments` : Choice ; `name` : Length max 255).
3. **`catch (\RuntimeException)` des providers attrape `HandlerFailedException`** :
   toute panne (DB down, bug mapping) pendant un GET devient un **404 silencieux**
   (`OrganizationProvider.php`, `ContactProvider.php`).
   → exceptions `OrganizationNotFound` dédiées + catch ciblé.
4. **Aucun rate limiting sur `/login_check` et `/token/refresh`** (brute force libre).
   → `login_throttling` sur le firewall login + limiteur sur le refresh.
5. **Front : suppression de contact sans confirmation, mutations sans feedback d'erreur**
   (`[id].vue` : `deleteContact` direct ; `saveOrg`/`addContact`/`saveContact`/`toggleDoNotContact`
   sans catch → échecs silencieux). → confirmation + `useToast()` + gestion d'erreur systématique.
6. **Front : lignes de tableau inaccessibles au clavier** (`@click` sur `<tr>`, index.vue).
   → `NuxtLink` sur le nom (la version mobile en `<button>` est déjà correcte).
7. **gitleaks non bloquant** (`continue-on-error: true`) sur un dépôt public. → le rendre bloquant.
8. **CLAUDE.md / README périmés** : « dépôt non initialisé », « code non initialisé »,
   « commandes TBD » — 30 commits plus tard. Le document contractuel ment ; la commande de seed
   (`app:user:create`) n'est documentée nulle part (blocage garanti d'un nouveau dev).
   → réécrire les sections État/Commandes/Git, documenter le chemin de démarrage complet.

## P1 — dette structurante (dans la foulée, avant les features M1.2)

9. **Unicité du nom d'organisation à trancher** : l'import dédoublonne, le POST direct accepte
   les doublons, aucune contrainte DB. Décision métier requise (cf. « Décisions » ci-dessous),
   puis invariant + index unique `(tenant_id, lower(name))` ou politique d'import documentée.
10. **Orchestration d'import au mauvais étage** : politique métier (dédoublonnage, boucle,
    erreurs) logée dans un processor API Platform. → commande d'Application `ImportOrganizations`
    + query d'existence dédiée (le dédoublonnage actuel hydrate *tous* les agrégats et est
    non atomique — TOCTOU).
11. **Événements de domaine manquants** : 5 mutations sur 7 n'émettent rien
    (`updateProfile`, `updateContact`, `removeContact`, `markDoNotContact` — fait RGPD à tracer).
    Les futurs read models/projections M1 en dépendent. Handlers concernés : injecter l'EventBus.
12. **Queries → agrégats** (violation de la règle CLAUDE.md, sans trace de décision) :
    les handlers de query retournent des entités Doctrine managées. → DTO de lecture
    (`OrganizationView`) **ou** ADR assumant l'écart en V1.
13. **Base de données** : aucun index `tenant_id` (toutes les requêtes filtrent dessus),
    types incohérents (`app_user.tenant_id` UUID vs VARCHAR ailleurs), colonnes JSON → JSONB,
    table `messenger_messages` hors migrations. → migration dédiée.
14. **Refresh tokens** : pas de rotation (`single_use`), pas d'invalidation au logout,
    pas de purge planifiée (`Schedule.php` vide). Front : refresh concurrents non mutualisés
    (casserait dès l'activation de `single_use`). → activer la rotation + endpoint
    d'invalidation appelé au logout + `gesdinet:jwt:clear` planifié + mutex de refresh côté front.
15. **Tests — combler la pyramide** :
    - Application : handlers testés avec repo in-memory (l'`InMemoryLeadRepository` existe,
      inutilisé ; créer l'équivalent Organization) ;
    - Intégration : **isolation tenant** (argument central du SaaS, jamais testée) + repository
      Doctrine + endpoints API (le service postgres de la CI n'est utilisé par rien) ;
    - Front : `useApi` (401→refresh→retry, concurrents), store auth, `useDirectory` —
      le seul test actuel couvre du code mort ; ajouter des seuils de coverage.
16. **Pagination** : le contrat OpenAPI annonce `page`, le provider retourne tout.
    → pagination réelle ou `paginationEnabled: false` explicite + filtres `type`/`q` déclarés
    en `QueryParameter`.
17. **PATCH `doNotContact: false` silencieusement ignoré** (cliquet voulu ?) :
    contractualiser — champ non dénormalisable ou erreur explicite.
18. **Tenancy — défense en profondeur** : filtre fail-open (paramètre absent = aucune
    contrainte), worker/CLI sans tenant. Théorique aujourd'hui, réel dès la première projection.
    → exception si filtre interrogé sans tenant + stamp Messenger `tenant_id`.
19. **Messenger : pas de `failure_transport`** — events perdus après 3 retries, contradictoire
    avec l'objectif outbox. → transport `failed`.
20. **i18n à trancher** : deux régimes coexistent (login via `t()`, tout le Répertoire en dur) ;
    l'EN est inatteignable (aucun sélecteur). FR-only assumé en V1 ou migration complète —
    le coût croît à chaque écran.
21. **Front — dette groupée** : labels type/segment dupliqués en 4 endroits
    (→ `utils/directory-labels.ts`), recherche sans debounce (1 requête/frappe),
    drawer mobile artisanal (pas d'Échap/focus trap → `USlideover`), labels de formulaire
    non liés (`for`/`id` — s'aligner sur `UFormField` comme le login), `href` du site web
    non validé (schéma `https?://`).

## P2 — hygiène (fil de l'eau)

22. **LICENSE absente** sur dépôt public (tous droits réservés implicite) — licence ou passage
    en privé (décision).
23. **Protection de branche** + mettre la règle git de CLAUDE.md en accord avec la pratique
    (trunk-based assumé, CI verte obligatoire — ou retour aux PR).
24. CodeQL (gratuit sur repo public) ; hooks pre-commit (cs-fixer/phpstan — le commit correctif
    `7279d1e` l'illustre) ; `timeout-minutes` sur les jobs CI.
25. Deps front en `"latest"` → pinner `^x.y.z` (Dependabot ne peut pas proposer de bump sinon).
26. Port `Clock` en Application (`new \DateTimeImmutable()` dans les handlers — bloquant pour
    tester les relances M1.3) ; `IdGenerator` dans la foulée si peu coûteux.
27. **Code mort** : `Organization::rename()`, `Account\Preferences`/`Theme`,
    `InMemoryLeadRepository` (docblock mensonger), `stores/preferences.ts`, `utils/locale.ts`
    (+ son test), clés i18n mortes.
28. **Glossaire** : valeurs `Segment` contradictoires (doc dit `EDITION…`, code dit
    `PUBLISHING…`), `OrganizationType`, `doNotContact`, VOs partagés et vocabulaire d'import
    absents. Machine à états : `SAMPLE_TEST` ↔ `PAUSED` diverge de DOMAIN-MODEL.md.
29. **Notes/ADRs** : ADR manquant « collections JSON dans l'agrégat » (la note M1.1 prévoyait
    du one-to-many — revirement non tracé) ; ADR-0009 dit « Nuxt 3 » ; note M1.1 : import prévu
    multipart/rapport différent/dédoublonnage plus riche — écarts à consigner ; ROADMAP à cocher.
30. Commentaires périmés : `messenger.yaml` (« outbox à câbler »), `TenantFilter`
    (« listener à câbler »), `ContactLeadHandler` (« dispatch_after_current_bus » — faux,
    et piège si le transport change), READMEs de contextes, scories `phpunit.dist.xml`
    (APP_ENV dev, serverVersion=16).
31. Divers sécurité : `/docs` public (assumer par ADR ou restreindre), mot de passe de
    `app:user:create` en argument CLI (→ question interactive), PII (emails) dans les messages
    d'exception, port 5432 exposé sur l'hôte, cookie `plume_email` (PII inutile), headers
    de sécurité Caddy, cookies front sans `secure`/`maxAge`.

## Décisions à trancher (métier / produit)

| # | Question | Options |
|---|---|---|
| D1 | Le **nom d'organisation** est-il unique par tenant ? | Invariant + index unique **ou** politique d'import seule |
| D2 | **i18n** en V1 ? | FR-only assumé (geler l'EN) **ou** migration complète avant M1.2 |
| D3 | **« Ne pas contacter »** : réversible ? | Cliquet RGPD (champ non modifiable en écriture) **ou** bascule libre |
| D4 | **Dépôt public** sans licence | Ajouter une licence **ou** passer en privé |
| D5 | **Workflow git** | Trunk-based assumé (adapter CLAUDE.md + protection) **ou** retour PR |

## Points forts (à préserver)

- Pureté du domaine **réelle** (zéro import framework dans Domain *et* Application, vérifié)
  avec deptrac en CI pour que ça dure.
- Outbox transactionnel correctement implémenté (transaction + transport doctrine même
  connexion) — rare.
- Multi-tenancy saine sur la surface actuelle : tenant issu du claim JWT signé, jamais du
  payload ; piège `find()` identifié et évité.
- Hygiène des secrets irréprochable : clés JWT jamais dans l'historique git (vérifié),
  aucun credential dans le code.
- CI au-dessus de la moyenne : moindre privilège, diff OpenAPI bloquant, deptrac, audits deps,
  Dependabot complet ; 30/30 commits conventionnels propres.
- Front : middleware d'auth global présent, TS strict réel, zéro `v-html`, config Nuxt
  exemplaire et commentée ; parser CSV défensif et testé.
- Culture de la décision : 11 ADRs, notes de conception écrites avant le code, glossaire.

## Plan proposé

- **Lot 1 (P0)** — correctifs immédiats, une session : bornes d'import, exceptions → HTTP,
  contraintes Assert, rate limiting, confirmations/toasts front, lien clavier, gitleaks
  bloquant, CLAUDE.md/README à jour.
- **Lot 2 (P1)** — dette structurante, avant d'écrire les features M1.2 (le pipeline Lead
  héritera de tous ces patterns) : décisions D1-D3 puis invariants, événements, import en
  Application, migration index/JSONB, refresh tokens, pyramide de tests, i18n.
- **Lot 3 (P2)** — au fil de l'eau, sans bloquer M1.2.

---

*Revue menée le 2026-07-13 (fin M1.1, avant M1.2). À rejouer en fin de M1.*
