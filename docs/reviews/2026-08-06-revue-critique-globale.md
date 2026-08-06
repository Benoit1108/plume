# Revue critique globale — 2026-08-06

> **Statut : REMÉDIATION COMPLÈTE (lots A→F).** 1 P1 · 11 P2 · 7 P3, tous traités. Aucun P0, aucune
> faille exploitable à distance sans compte.
>
> | Lot | Contenu | Commit |
> |---|---|---|
> | **A** | UX-P1 (défilement mobile, deux causes) · UX-P2c (contrastes) · TEST-P2a/b (garde-fous étendus) | `9cd3ddf` |
> | **B** | SEC-P2a (énumération) · SEC-P2b (démo) · CSV · state OAuth · /docs · CSP | `c6f584a` |
> | **C** | UX-P2a (erreur ≠ vide) · UX-P2b (402/429/403/422 parlants) | `26c89ed` |
> | **D** | UX-P2d (lien d'évitement, focus) · UX-P2e (priorité) · délai en heures | `58bcb1b` |
> | **E** | BACK-P2a (emails idempotents) · PERF-P2a (index) · OPS-P2a (sauvegardes) | `da742d5` |
> | **F** | Design : hiérarchie du tableau de bord, signature de marque dans l'app | `02b2826` |
>
> Deux P3 restent OUVERTS, assumés : l'avis `npm audit` **low** sur esbuild (serveur de dev, Windows
> uniquement, transitif via `fontless` — rien à corriger côté produit), et l'affinage de la CSP du
> SPA au premier déploiement réel (déjà annoté dans `Caddyfile.prod`). Le troncage de la table
> « segments » sur mobile a été laissé tel quel : le conteneur défile, c'est le comportement voulu.

Demandée après les lots « navigation », « alignement », « densité » et « onglets » : pousser au
maximum les retours critiques (sécurité, UX/UI, design, archi, tests, exploitation).

## Méthode — ce qui a été MESURÉ, pas supposé

La revue précédente (2026-08-03) reposait sur de la lecture de code. Celle-ci exécute :

| Vérification | Outil | Portée |
|---|---|---|
| Vulnérabilités de dépendances | `composer audit`, `npm audit --omit=dev` | back + front |
| Accessibilité automatique | axe-core (wcag2a/aa, 21a/aa) | **18 pages authentifiées × 2 thèmes** |
| Contrastes AA réels | mesureur du dépôt (fond effectif alpha compris) | 17 pages authentifiées × 2 thèmes |
| Débordement horizontal | mesure DOM à 390 px | 10 pages |
| Énumération de comptes | sondes HTTP sur l'API de dev | inscription + connexion |
| Bridage du compte démo | session démo réelle → appels d'écriture | endpoints produit |
| Couverture des routes | inventaire `debug:router` croisé aux tests fonctionnels | 71 routes |
| Index base | `pg_indexes` sur les tables chaudes | 9 tables |
| SSRF / XXE | lecture du client HTTP + du parseur laminas | ingestion RSS |

Les captures et rapports bruts ont servi à rédiger les constats ; chaque finding cite un fichier et,
quand c'est mesuré, la valeur obtenue.

## Ce qui est SOLIDE (vérifié, pas supposé)

Une revue critique n'a de valeur que si elle distingue ce qui tient de ce qui ne tient pas.

- **Accessibilité automatique : 0 violation** sur les 18 pages authentifiées, en clair **et** en sombre.
  La discipline des lots a11y précédents tient au-delà des pages qu'ils couvraient.
- **Contrastes : 0 échec en thème clair** (17 pages), 2 en sombre (cf. UX-P2c).
- **Typographie française : 0 écart** sur les 53 chaînes à ponctuation double (espaces avant `? : !`).
- **Toutes les routes de l'API apparaissent dans `tests/Functional/`** (71/71).
- **SSRF réellement gardé** : `NoPrivateNetworkHttpClient` (services.yaml:255) — le docblock de
  `RssAlertSource` ne ment pas. **XXE bloqué** : laminas-feed refuse tout `DOCTYPE`.
- **Isolation tenant** : 41 occurrences de `tenant_id` dans les read models, **aucun read model sans
  filtre** ; les 5 gateways cross-contexte passent tous un tenant explicite.
- **State OAuth** : HMAC + TTL 600 s + nonce + `hash_equals`, lié au tenant (`OAuthStateCodec`).
- **Logs** : aucun PII dans les contextes ; Sentry `send_default_pii: false` ; SQL Doctrine non
  journalisé en prod (`kernel.debug` faux).
- **Compte démo** : plafond global (50 actifs), TTL 2 h, purge horaire réutilisant la purge RGPD.
- **Index base** : couverture sérieuse (tenant+statut, relances dues, dédup, unicité par événement).
- **`composer audit` : aucun avis de sécurité.**

---

## P1 — à corriger avant la mise en production

### UX-P1 — `/leads` fait défiler TOUTE la page horizontalement sur mobile (1 545 px)

`app/pages/leads/index.vue:184`. Mesuré à 390 px : `document.scrollWidth = 1935` pour un viewport de
390. Le kanban est pourtant correctement construit (`overflow-x-auto` + `snap-x`, colonnes à `78vw`) :
le scroller interne clippe bien (largeur 358, contenu 2 518).

**La cause est ailleurs et invisible à la lecture** : chaque carte contient
`<span class="sr-only">{{ priorityLabel(...) }}</span>`. `sr-only` vaut `position: absolute` ; le
`NuxtLink` parent n'est pas positionné, donc le bloc contenant de ce span est le **bloc conteneur
initial** — il échappe au clip du scroller et étend la zone de défilement du document. Résultat : la
barre de navigation, l'en-tête et le titre défilent hors écran quand on fait glisser la page, sur
l'écran le plus utilisé du produit.

→ **Correctif : ajouter `relative` au `NuxtLink` de la carte** (une classe). Vérifier ensuite
`scrollWidth === clientWidth` à 390 px, et **ajouter cette mesure au garde-fou E2E** — c'est
exactement le genre de régression qu'aucune relecture n'attrape.

---

## P2 — à traiter avant l'ouverture publique

### SEC-P2a — Énumération de comptes à la connexion (vérifiée)

Sondes exécutées sur l'API :

| Requête | Réponse |
|---|---|
| `login_check` — compte **existant non vérifié**, mot de passe **faux** | `401 email_not_verified` |
| `login_check` — compte **inexistant**, mot de passe faux | `401 Identifiants invalides.` |
| `register` — email déjà pris | `409 email_taken` |

`AccountStatusChecker::checkPreAuth` est évalué **avant** la vérification du mot de passe (comportement
Symfony) : n'importe qui distingue un email inscrit d'un email libre **sans connaître le mot de passe**.
Idem pour `account_deleted`. L'incohérence est le vrai sujet : le mot de passe oublié répond
délibérément 204 dans tous les cas (anti-énumération), mais la connexion et l'inscription livrent
l'information. Débit limité (5/15 min par IP), donc exploitation lente — mais suffisante pour valider
une liste ciblée.

→ Repousser les vérifications d'état **après** la validation du mot de passe (checker `postAuth`), ou
renvoyer un code générique et n'expliquer qu'après authentification réussie. Décider explicitement du
sort de `409 email_taken` (compromis UX assumé → l'écrire dans l'ADR, sinon 204 + email « ce compte
existe déjà »).

### SEC-P2b — Le compte démo public peut faire émettre des requêtes HTTP sortantes par le serveur (vérifié)

Session démo réelle (`POST /api/v1/demo` → cookies) :

| Appel | Résultat |
|---|---|
| `POST /api/v1/mailbox/oauth/start` | **403 `demo_restricted`** ✅ |
| `POST /api/v1/sources` (URL de flux arbitraire) | **201** ❌ |
| `POST /api/v1/sources/poll` | **202** ❌ (relève asynchrone → le serveur va chercher l'URL) |

`DemoRestrictionListener` (Account/Infrastructure/Http) bloque la boîte email et l'envoi, mais pas les
sources. Un visiteur **anonyme** obtient donc une primitive de requête sortante attribuable au serveur
Plume, vers l'hôte de son choix (12 relèves/h/tenant, 50 tenants démo simultanés). Les IP privées sont
refusées (`NoPrivateNetworkHttpClient`) : ce n'est pas un SSRF interne, mais c'est un réflecteur et une
fuite d'IP serveur. Le tenant démo dispose déjà d'annonces factices : bloquer ne coûte aucune démo.

→ Ajouter `/api/v1/sources` (écritures) et `/api/v1/sources/poll` à la liste noire `ROLE_DEMO`.

### UX-P2a — Une erreur de chargement s'affiche comme « vous n'avez rien »

Le composant `QueryError` existe et est utilisé sur 5 écrans (today, dashboard, leads, candidates,
catalogue). Il **manque** sur : `pages/organizations/index.vue`, `pages/organizations/[id].vue`,
`pages/templates/index.vue`, `components/settings/MailboxSection.vue`,
`components/settings/AlertFeedsSection.vue`, et les sections du Compte. Ces écrans testent
`isPending` puis `!items.length` : quand la requête **échoue**, `isPending` retombe à faux, les données
sont `undefined` → l'utilisatrice lit « Aucune organisation. Créez votre première cible. » alors que
son répertoire est intact et que l'API est en panne. Message faux, et invitation à re-saisir des
données existantes.

→ Appliquer `QueryError` partout où une query alimente un état vide (le patron est déjà écrit).

### UX-P2b — 402 (lecture seule) et 429 (débit) affichent « Une erreur est survenue »

`app/utils/core/apiError.ts` ne traite que le 409. Or ce sont précisément les deux cas où
l'utilisatrice a besoin d'une consigne : « votre essai est terminé, abonnez-vous pour reprendre » et
« trop de tentatives, réessayez dans X minutes ». Le bandeau d'abonnement donne le contexte global,
mais l'action qui échoue, elle, ne dit rien d'utile.

→ Étendre `errorToastTitle` : 402 → message d'abonnement + lien vers `/settings?tab=billing`,
429 → délai d'attente (l'API renvoie `Retry-After`), 422 → afficher le `detail` de l'API.

### UX-P2c — Deux contrastes AA échouent en thème sombre (mesurés)

| Écran | Élément | Ratio | Exigé |
|---|---|---|---|
| `/today` | lien « Ajouter un flux » (onboarding), 12 px | **4,18** | 4,5 |
| `/account?tab=security` | badge « Cet appareil », 10 px | **4,30** | 4,5 |

Les deux sont du `plume-400` (#9B87D9) sur fond élevé sombre. Le reste du produit passe (0 échec en
clair, 2 en sombre sur 17 pages).

→ Monter d'un cran la teinte pour ces usages (ou passer le badge en `neutral`), puis **étendre le
garde-fou de contraste aux pages authentifiées** (cf. TEST-P2a).

### UX-P2d — Ni lien d'évitement, ni gestion du focus au changement de page

`app/layouts/default.vue` : la barre latérale expose 7 liens répétés sur chaque page, sans
« Aller au contenu ». Au clavier, chaque navigation impose de re-traverser toute la nav. Et en SPA, le
changement de route ne déplace pas le focus ni n'annonce la nouvelle page : un lecteur d'écran reste
sur l'ancien contexte. axe ne le détecte pas (la règle `bypass` est satisfaite par le repère `<main>`).

→ Lien d'évitement en premier élément focusable + déplacement du focus sur le `<h1>` après chaque
navigation (ou région `aria-live` annonçant le titre).

### UX-P2e — La priorité d'une piste n'est portée que par une couleur

`app/pages/leads/index.vue:169-174` : pastille de 8 px colorée, `aria-hidden`, doublée d'un
`sr-only` (donc correcte pour les lecteurs d'écran) et d'un `title` (inaccessible au tactile). Pour une
personne daltonienne **voyante**, la priorité est illisible : c'est WCAG 1.4.1 (usage de la couleur).

→ Ajouter une forme ou une lettre (H/M/B) à la pastille, ou afficher le libellé sur la carte.

### BACK-P2a — Bilan hebdo et digest email : aucune idempotence, un retry renvoie les emails

`SendWeeklyReportsHandler` (« aucun état ‘dernier envoi’ », assumé dans le docblock) et
`SendNotificationDigestsHandler` (lit les notifications non lues, ne les marque pas) s'exécutent sur
un transport Messenger configuré `max_retries: 3`. Un échec au milieu de la boucle (SMTP qui expire au
tenant n°10) rejoue le message : les tenants 1 à 9 reçoivent **deux fois** le même email. Le scheduler
`stateful` rejoue en plus la dernière occurrence manquée.

C'est l'exception dans un produit par ailleurs rigoureux : les notifications de relance et de client
dormant, elles, portent une clé d'idempotence (`dormant:<lead>:<YYYY-MM>`).

→ Même patron : marquer l'envoi (`profile.last_weekly_report_on` / `notification.digested_at`), ou
traiter un message par tenant pour que le retry soit borné à un destinataire.

### OPS-P2a — Les sauvegardes ne sont ni chiffrées, ni externalisées, ni testées

`docs/ops/deployment-checklist.md:79` annonce « Sauvegardes DB **chiffrées** + testées (restauration) ».
`scripts/backup-db.sh` fait un `pg_dump | gzip` vers `/opt/plume/backups`, **sur le même VPS**, sans
chiffrement, avec rotation à 14 jours. La documentation décrit donc une garantie que le code ne fournit
pas : un dump en clair contenant tout le carnet d'adresses des clientes reste sur la machine exposée, et
la perte du serveur emporte les sauvegardes avec lui.

→ Chiffrer (`age`/`gpg` avec une clé publique dont la privée n'est PAS sur le VPS), pousser vers un
stockage objet hors machine, et planifier une **restauration de vérification** (mensuelle) dont le
résultat est visible quelque part.

### TEST-P2a — Les garde-fous a11y/contraste ne couvrent que 3 pages publiques

`e2e/a11y.spec.ts` et `e2e/contrast.spec.ts` : `PAGES = ['/', '/login', '/register']`. Toute
l'application authentifiée est hors garde-fou. Le scan mené pour cette revue montre **0 violation axe**
et **2 écarts de contraste** : le moment est idéal pour figer cet état — le coût d'une régression est
d'autant plus grand qu'on croit le sujet couvert.

→ Étendre les deux specs aux pages authentifiées (l'infrastructure de login existe déjà), en gardant le
temps d'exécution raisonnable (une passe par thème, pages regroupées).

### TEST-P2b — Aucune mesure de débordement horizontal en CI

Le lot « vitrine 390 px » a corrigé le scroll horizontal ; UX-P1 montre qu'il est revenu ailleurs sans
que rien ne le signale.

→ Assertion `scrollWidth === clientWidth` à 390 px sur les écrans principaux (3 lignes par page).

### PERF-P2a — `refresh_tokens.username` sans index alors que chaque authentification y fait 3 requêtes

`pg_indexes` : la table ne porte que sa PK et l'unique sur `refresh_token`. Or depuis le lot
« densité », chaque connexion **et chaque rafraîchissement** exécutent un DELETE des sessions expirées,
un SELECT des ids et parfois un DELETE du surplus, tous filtrés sur `username` — donc trois parcours
séquentiels sur une table qui grossit avec le nombre de sessions vivantes de tous les comptes.

→ `CREATE INDEX ON refresh_tokens (username)` (migration d'une ligne). *(Dette introduite par le lot
densité : à assumer comme telle.)*

---

## P3 — durcissements et finitions

- **CSV : pas de neutralisation des formules.** `AdminAccountsExportController` et l'export RGPD
  écrivent des cellules issues de saisies utilisateur sans préfixer celles qui commencent par
  `= + - @`. Le cas exploitable est étroit (les emails valides limitent la charge utile) mais le
  correctif est d'une ligne et l'export admin est ouvert dans le tableur de l'exploitant.
- **`npm audit` : 1 avis « low »** (esbuild < 0.28, serveur de dev, Windows uniquement), transitif via
  `fontless`. Sans impact prod ; à suivre.
- **`/api/v1/docs` reste lisible par tout compte authentifié en prod** (`security.yaml`, `ROLE_USER`).
  Contrat d'API complet livré à n'importe quel visiteur ayant créé un compte gratuit.
- **State OAuth rejouable dans sa fenêtre de 10 minutes** (signé mais pas à usage unique). Le code
  d'autorisation étant consommé côté fournisseur, l'impact est théorique.
- **CSP du SPA sans `form-action` ni `object-src`** (non couverts par `default-src`), et pas de
  `Cross-Origin-Opener-Policy` / `Resource-Policy`. `Caddyfile.prod`, où la CSP est déjà annotée
  « à affiner au 1er déploiement ».
- **Tableau de bord : « 0 j » pour tout délai inférieur à 24 h.** Le KPI « délai moyen de 1re réponse »
  perd son information là où elle est la plus flatteuse (répondre en 4 h s'affiche « 0 j »).
  → Basculer en heures sous 1 jour.
- **Segments du tableau de bord tronqués à droite sur mobile** (colonne « Gagnées » coupée dans un
  conteneur défilant sans indice visuel).

---

## Design (point 8 — relecture `frontend-design`)

**Le socle est bon et n'a rien de générique** : Fraunces (display) + IBM Plex Sans + IBM Plex Mono,
palette « plume » sur mesure, fonds « atelier » chauds (#F6F4F2 / #232027) plutôt que du blanc/noir,
décisions de contraste **documentées avec leurs mesures** dans `main.css`. Peu de projets tiennent ce
niveau de justification. Le motion est sobre et neutralisé sous `prefers-reduced-motion`.

Trois critiques, dans l'ordre où elles se voient :

1. **L'application n'a qu'une seule texture.** Tout écran est une pile de
   `border border-default rounded-xl p-4 bg-elevated/40`. C'est cohérent, mais Aujourd'hui, Tableau de
   bord, Réglages et Compte se ressemblent au point qu'on ne les distingue qu'au titre. La signature de
   marque (halo d'encre, plume) s'arrête à la vitrine et à `/login` : **le produit lui-même n'a aucun
   moment signature**, alors que c'est là que la traductrice passe ses journées.
   → Choisir UN écran porteur (« Aujourd'hui », qui a un rôle unique et émotionnel : quoi faire
   maintenant) et lui donner une identité propre — pas plus d'ornement, mais une composition qui n'est
   pas la même grille de cartes.
2. **Le tableau de bord ne hiérarchise pas.** Six KPI de même taille, même graisse, même carte : rien ne
   dit lequel compte. Or le produit a une thèse (« la régularité paie ») ; le taux de réponse et la
   série méritent un traitement dominant, le reste peut descendre d'un cran.
3. **Les états vides sont corrects mais neutres** — et, quand ils mentent (cf. UX-P2a), ils desservent.
   Un état vide est une invitation ; un état d'erreur est une consigne. Les distinguer visuellement
   ferait d'une pierre deux coups avec UX-P2a.

---

## Ce que je ferais, dans l'ordre

1. **UX-P1** (une classe) + la mesure de débordement en CI (TEST-P2b) — même lot, ~30 min.
2. **SEC-P2b** (bridage démo) puis **SEC-P2a** (énumération) : les deux seuls sujets qui changent de
   nature une fois le produit public.
3. **UX-P2a/b** (erreur ≠ vide, 402/429 parlants) : ce sont les défauts que la première utilisatrice
   réelle rencontrera, et ils lui feront croire à une perte de données.
4. **BACK-P2a** (idempotence des emails) et **OPS-P2a** (sauvegardes) : ce qui fait mal en exploitation.
5. **TEST-P2a** (figer l'a11y/contraste authentifiés), **UX-P2c/d/e**, **PERF-P2a**.
6. **P3** au fil de l'eau ; le **design** (3 points ci-dessus) mérite son propre cadrage, pas un lot de
   correctifs.
