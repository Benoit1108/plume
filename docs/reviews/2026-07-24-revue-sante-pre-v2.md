# Revue de santé — fin de phase pré-V2 (2026-07-24)

Revue complète après la phase pré-V2 (chantiers 1→4 : RLS multi-tenant, mail réel, front
SPA/TanStack, `AbstractStringIdType`, poll async, durcissement OpenAPI). 4 audits adversariaux
parallèles (back+archi, sécurité, front, docs). Méthode : lecture du code, pas de confiance aux
commentaires. Rien signalé qui n'ait été vérifié.

## Notes par axe

| Axe | Note | Synthèse |
|---|---|---|
| **Back + archi** | **8,5 / 10** | Socle multi-tenant mûr et discipliné ; RLS bien conçue ; chantier 4 exemplaire. 1 P1 (FetchReplies) + angles morts d'outillage. |
| **Sécurité** | **8 / 10** | Security-conscious, 2 lignes fail-closed réelles, crypto soignée, **aucun P0**. Même P1 + backlog de durcissement. |
| **Front** | **7,5 / 10** | Migration TanStack maîtrisée, SPA/auth solides. 1 **P0** (perte de saisie), 1 P1 (import), sous-invalidation croisée récurrente. |
| **Docs** | **6,5 / 10** | Docs d'archi profondes excellentes, mais **récidive « docs de tête périmées »** (README/CLAUDE/ADR-0022 faux sur l'état actuel). |

**Point marquant : convergence.** Les audits back ET sécurité, indépendamment, pointent le même
défaut n°1 (`FetchReplies`). Le front isole le seul risque de perte de données. Les docs confirment
la récidive attendue.

---

## P0 — À corriger impérativement

### P0-1 (front) — L'éditeur de brouillon écrase la saisie non enregistrée pendant qu'un autre brouillon se génère
`components/LeadDraftsSection.vue` (polling `hasGenerating` toutes les 1,5 s) + `LeadDraftEditor.vue:38-42` (`watch(draft)` réinitialise `editSubject`/`editBody`).
**Scénario** : brouillon A en génération → polling actif ; l'utilisatrice ouvre B (READY) et rédige ; à chaque tick, `syncEditorWithList()` réassigne un nouvel objet → le `watch` **efface son texte en cours**, sans avertissement. Atteint le cœur du produit (relecture draft-first).
**Reco** : ne resynchroniser l'éditeur que sur vrai changement d'id/statut ; ne jamais écraser un champ « dirty ».

---

## P1 — Sérieux

### P1-1 (back + sécurité, convergent) — `FetchReplies` tourne en synchrone dans le scheduler (propriétaire), RLS + filtre contournés
`Mailbox/Infrastructure/Scheduler/FetchAllRepliesHandler.php:29` dispatche `new FetchReplies($tenantId)` **sans** `TransportNamesStamp(['async'])`, via l'interface `CommandBus` (sans stamp). `FetchReplies` étant un `Command` non routé → traité **inline** dans le process scheduler (`compose.yaml:77`, rôle `plume` propriétaire). Ses jumeaux `FetchAllAlertEmailsHandler:33` et `PollAllSourcesHandler:34` partent bien en async (worker `plume_app`).
**Conséquence** : la relève des réponses (chemin le PLUS sensible — déchiffrement des refresh tokens OAuth + lecture des aperçus, **tous tenants**) s'exécute **RLS contournée + SQLFilter désactivé + `app.current_tenant=''`**. Sauvée d'une fuite uniquement par les prédicats explicites `findForTenant`/`forTenant` — la « ligne de défense unique » que l'ADR-0023 disait éliminer. Contredit ADR-0023 §5 et ADR-0022 §1 (« déjà async »). Aussi : I/O réseau dans la transaction, pas d'isolation de panne par tenant.
**Reco** : dispatcher `FetchReplies` en `async` (injecter `MessageBusInterface` + `TransportNamesStamp(['async'])`, comme les jumeaux) → worker `plume_app`, tenant activé, RLS appliquée, panne isolée. + test « un tick de scheduler ne fait que du fan-out ».

### P1-2 (front) — L'import CSV n'invalide aucune query : le répertoire paraît vide de l'import
`pages/organizations/import.vue:23-38` — succès affiché mais aucune invalidation ; `staleTime=30s` → la liste en cache ne se rafraîchit pas → l'utilisatrice voit l'ancien contenu, croit l'import raté.
**Reco** : `queryClient.invalidateQueries({ queryKey: queryKeys.organizations })` après import.

### P1-3 (docs) — Récidive « docs de tête périmées » : README/CLAUDE/ADR-0022 faux sur l'état actuel
- `CLAUDE.md` § État actuel : présente les chantiers 2 et 3 comme « à suivre » (tout est fait) ; chantier 4 absent.
- `README.md` § État : « Prochaine étape : M3 » alors que M3 + pré-V2 sont livrés ; `Sourcing/ # à venir`.
- `ADR-0022` : présente 5 dettes sur 8 (§1,§2,§6,§7,§8) comme « reportées V2 » alors qu'elles sont **soldées** → le cadrage V2 repartirait de prémisses fausses.
**Reco** : resynchroniser les 3 + marquer les dettes soldées (statut/commit par point).

---

## P2 — Améliorations (backlog)

**Front (invalidations & robustesse)**
- Sous-invalidation croisée : création d'organisation n'invalide pas `organizations` ; `today.quickAction` + transitions de piste + promotion de candidat n'invalident ni `dashboard` ni `today` (KPI/listes périmés). → **matrice « quelle mutation invalide quels écrans »** centralisée.
- Garde-fou « Contacter sans contact » **contournable** : présent sur la fiche, absent du drag kanban (`leads/index`) et du quick-contact (`today`). Appliquer les 3.
- Pas de gestion `isError` (retry:false) → une lecture en échec affiche l'état « vide/introuvable » (trompeur, ex. fiche piste). Bandeau + réessayer sur les écrans de détail.
- Mutation optimiste kanban mute l'objet du cache en place (anti-pattern TanStack → préférer `setQueryData`) ; badge `sourcing-pending` figé hors `/candidates` ; `dashboard.vue` clé `['dashboard']` en dur ; `Segment` dupliqué (drafting/directory) + non dérivé du contrat ; timer polling brouillon pouvant survivre à l'unmount.

**Back / sécurité (durcissement)**
- **Source de démo sans garde d'env** (`PollAlertSourceHandler:35` + `services.yaml:186`) : en V2, un vrai tenant sans flux qui clique « Relever » reçoit 2 fausses annonces dans sa file → **garder derrière `APP_ENV !== prod`**.
- **Test RLS mono-table** : ne couvre qu'`alert_feed`. → test balayant `information_schema` : toute table à `tenant_id` doit avoir RLS + policy (ferme le risque n°1 = policy oubliée sur nouvelle table).
- **deptrac aveugle à l'Infra cross-contexte** : rien n'empêche `Sourcing\Infrastructure` d'accéder à `Prospecting\Domain\Lead`. → règle interdisant Infra d'un contexte → Domain d'un autre (n'autoriser que `\Event\` + ports Application).
- **`trusted_proxies` absent** : tout passe par le proxy same-origin → `getClientIp()` = IP du proxy → rate-limiting token/login **par IP effondré** (seau global, auto-DoS en V2). → configurer `trusted_proxies`/`trusted_headers`.
- **`html_entity_decode(strip_tags(...))`** (cleaners RSS/reply/parser) : re-décode après strip → produit du texte à échapper, pas du HTML sûr. **Inoffensif aujourd'hui** (0 `v-html`, interpolation échappée) mais latent. → ne pas re-décoder + documenter l'invariant.
- Reset tenant seulement sur `terminate` (résiduel si mode worker FrankenPHP activé) → reset aussi en `kernel.request`. Lecture RSS bufferisée avant la borne de taille → streamer. Hygiène secrets prod (checklist `APP_SECRET`/passphrases). `messenger_messages` PII hors RLS (documenté). Scope Gmail `readonly` large (documenté, RGPD).

**Docs**
- `DOMAIN-MODEL.md` + `GLOSSAIRE.md` : amendement ADR-0008 (`returnToContact`/`LeadReturnedToContact`) absent ; **`win()/lose()` faux** (code = `markWon()/markLost()`).
- `Sourcing/README.md` : parser LinkedIn listé « à faire » (fait) ; `OVERVIEW.md` réf ADR SPA trompeuse ; `Makefile` aide worker imprécise.
- **Décisions non tracées** : bascule full-SPA `ssr:false` (structurante, ADR-0022 §8 disait « à acter explicitement ») → mérite un ADR court ; poll async / `AbstractStringIdType` / durcissement OpenAPI → mise à jour d'ADR-0022.

---

## Plan de remédiation proposé (lots)

- **Lot A — P0 + P1 (correctness & sécurité)** : FetchReplies async (P1-1), éditeur brouillon anti-écrasement (P0-1), invalidation import CSV (P1-2). *Le strict nécessaire.*
- **Lot B — invalidations & garde-fous front** : matrice d'invalidation (org create, today/dashboard sur mutations de piste), garde-fou « Contacter » sur kanban + today, `isError` sur les détails, `setQueryData` kanban, clé `dashboard`, `Segment` dérivé.
- **Lot C — durcissement back/sécu** : garde d'env source démo, test RLS `information_schema`, règle deptrac Infra cross-contexte, `trusted_proxies`, ordre strip/decode, reset tenant `kernel.request`.
- **Lot D — docs** : resync README/CLAUDE/ADR-0022 (dettes soldées), DOMAIN-MODEL/GLOSSAIRE (amendement 0008 + `markWon/markLost`), Sourcing README, OVERVIEW, Makefile, **ADR full-SPA**.

Cible : ≥ 9/10 sur les 4 axes après remédiation, comme les revues précédentes.
