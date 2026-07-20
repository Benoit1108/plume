# Guide de recette — Plume (V1, jalons M1 → M3)

> Objectif : **recetter entièrement l'outil** de bout en bout, sans rien connaître du code.
> Chaque scénario donne le **but**, les **étapes**, les **données à utiliser** et le **résultat
> attendu**. Coche au fur et à mesure. Le métier est en **français**, l'app est bilingue **FR/EN**.
>
> Deux façons de recetter :
> - **avec le jeu de démonstration** (`make seed`) — recommandé : tout est déjà rempli (Marie
>   Lefèvre, éditeurs, pistes réparties sur tout le pipeline, annonces à trier) ;
> - **à froid** (base vide) — pour vérifier les écrans vides et la création pas à pas.

---

## 0. Vocabulaire (rappel)

| Terme | Sens |
|---|---|
| **Piste** | une opportunité suivie dans le pipeline (de « À contacter » à « Gagnée »/« Perdue »). |
| **Organisation** | une cible : maison d'édition, studio audiovisuel, agence. |
| **Contact** | une personne dans une organisation. |
| **Relance** | un rappel planifié (cadence J+7 / J+21 / J+45). |
| **Annonce (à trier)** | une opportunité captée automatiquement (RSS / email), en attente de décision. |
| **Segment** | Édition · Audiovisuel · Technique · Autre. |

---

## 1. Prérequis & mise en route

> Environnement de développement local (Docker). Tout tourne sans aucun compte externe réel.

- [ ] **Démarrer la stack** : `make up` (Postgres + API `https://localhost:8443` + worker).
- [ ] **Clés JWT** (une seule fois) : `make jwt-keys`.
- [ ] **Migrer la base** : `make migrate`.
- [ ] **Charger le jeu de démonstration** : `make seed`
      → crée l'utilisatrice **`recette@plume.fr`** / **`recette-2026`** avec toutes les données.
- [ ] **Lancer le front** : `cd app && npm run dev` → ouvrir **http://localhost:3000**.

> Créer un utilisateur vierge (recette à froid) :
> `docker compose exec php php bin/console app:user:create <email>` (mot de passe demandé).

**Comptes de recette**
| Compte | Usage |
|---|---|
| `recette@plume.fr` / `recette-2026` | jeu de démonstration complet (`make seed`) |
| `test@plume.fr` / `secret123` | compte dev simple |

---

## 2. Ce que contient le jeu de démonstration

Après `make seed`, le tenant `recette@plume.fr` contient :

- **Profil** : Marie Lefèvre, traductrice **EN↔FR / ES→FR** (bio + signature + spécialités
  renseignées), objectif hebdomadaire par défaut.
- **~13 organisations** avec contacts, réparties par segment :
  - *Édition* : Éditions du Phare, Gallimard Jeunesse, Éditions Actes Sud, Nord-Sud Verlag ;
  - *Audiovisuel* : Studio Dubbing Paris, Titra Films, VSI Group ;
  - *Technique (agences)* : LinguaTech Solutions, TransPerfect, Agence Traduco ;
  - *Autre* : Média Docs & Cie ;
  - **Éditions Fermées** : marquée **« Ne pas contacter »** (démonstration RGPD).
- **~11 pistes actives** réparties sur **tout le pipeline** (À contacter → Test/Échantillon)
  + des pistes **gagnées/perdues**, avec journal d'interactions et quelques brouillons.
- **4 annonces « À trier »** (une par source) : ProZ (roman jeunesse EN>FR), RSS (sous-titrage
  ES>FR), LinkedIn (localisation EN>FR), TranslatorsCafe (manuel technique EN>FR).
- **4 modèles** de message (candidature édition/AV, relance, technique).

---

## 3. Parcours de recette

### A. Connexion & Compte

- [ ] **A1 — Connexion.** Aller sur `/login`, saisir `recette@plume.fr` / `recette-2026`.
      → Redirection vers l'accueil **« Aujourd'hui »**. Le menu latéral apparaît.
- [ ] **A2 — Mauvais mot de passe.** Se déconnecter, retenter avec un mot de passe faux.
      → Message d'erreur clair, pas de connexion. (Après plusieurs essais : limitation de débit.)
- [ ] **A3 — Nom d'affichage.** Menu **Compte** → renseigner Prénom/Nom → enregistrer.
      → Toast de succès ; le nom s'affiche dans l'interface.
- [ ] **A4 — Changer le mot de passe.** Compte → changer le mot de passe (l'actuel est requis).
      → Succès. **Vérif sécurité** : les autres sessions sont **déconnectées** (les jetons de
      rafraîchissement sont révoqués) — se reconnecter avec le nouveau mot de passe.
- [ ] **A5 — Déconnexion.** → Retour à `/login`, les cookies de session sont effacés.

### B. Répertoire (organisations & contacts)

- [ ] **B1 — Lister.** Menu **Répertoire**. → La liste des organisations s'affiche (paginée).
- [ ] **B2 — Rechercher / filtrer.** Chercher « Titra », filtrer par **segment** (Audiovisuel).
      → La liste se restreint ; l'URL reflète le filtre.
- [ ] **B3 — Créer une organisation.** « Nouvelle organisation » : nom **« Éditions Test »**,
      type **Éditeur**, pays FR, langues `fr, en`, segment Édition. Ajouter un contact
      (nom + rôle + email). → Créée, visible dans la liste et sur sa fiche.
- [ ] **B4 — Nom en double.** Recréer **« Éditions Test »**. → **Refus** (nom déjà utilisé,
      message 409) — l'unicité est insensible à la casse.
- [ ] **B5 — Modifier un contact.** Sur une fiche, modifier puis retirer un contact. → Reflété.
- [ ] **B6 — Ne pas contacter (RGPD).** Ouvrir **Éditions Fermées** → elle est marquée
      **« Ne pas contacter »**. Tenter d'en faire une piste / de générer un message
      (voir C/E). → **Bloqué** avec message dédié. Réautoriser puis re-vérifier (réversible, tracé).
- [ ] **B7 — Import CSV.** « Importer » : déposer un CSV (nom, type, pays, langues, segments,
      contact…). → **Rapport d'import** : importées / ignorées (doublons de nom) / en échec
      (avec le n° de ligne). Bornes : fichier ≤ 1 Mo, ≤ 1000 lignes.

### C. Pipeline / Pistes

- [ ] **C1 — Kanban.** Menu **Pistes**. → Colonnes par statut (À contacter, Contactée, Relancée,
      En discussion, Test/Échantillon, En pause, Gagnée, Perdue) avec les pistes du seed.
- [ ] **C2 — Créer une piste.** Depuis une organisation **sans piste active** (ex. « Éditions
      Test » créée en B3) : nouvelle piste (paire `en>fr`, priorité, segment). → Apparaît en
      **« À contacter »**.
- [ ] **C3 — Une seule piste active par organisation.** Tenter une 2e piste active sur la même
      organisation. → **Refus** (409, invariant « 1 piste active/org »).
- [ ] **C4 — Glisser-déposer.** Déplacer une carte « À contacter » → « Contactée ».
      → La transition s'applique (les colonnes atteignables s'allument pendant le glissé) ;
      un déplacement **illégal** est refusé avec un message.
- [ ] **C5 — Fiche piste & journal.** Ouvrir une piste : timeline d'interactions, actions
      (contacter, relancer, réponse reçue, test, gagnée/perdue, pause/reprise, note).
- [ ] **C6 — Cycle complet.** Sur une piste : Contacter → Relancer → Réponse reçue (passe
      **En discussion**) → Test → **Gagnée**. → Chaque étape journalisée ; une réponse **annule**
      la relance en attente.
- [ ] **C7 — Pause / reprise.** Mettre une piste en pause puis la reprendre. → Le statut d'avant
      la pause est **restauré**.

### D. Relances & régularité — écran « Aujourd'hui »

- [ ] **D1 — Accueil « Aujourd'hui ».** `/` : pistes **à contacter**, relances **dues**, widget
      **objectif hebdomadaire + série 🔥**.
- [ ] **D2 — Cadence de relance.** Contacter une piste → une relance **J+7** est planifiée
      automatiquement ; la faire → suivante à **J+21**, puis **J+45**.
- [ ] **D3 — Une seule relance en attente.** Replanifier une relance. → L'ancienne est
      **remplacée**, jamais empilée.
- [ ] **D4 — Objectif & série.** Réglages → changer l'objectif hebdomadaire (1–99). Réaliser des
      actes (contacts / relances). → La progression et la **série** (semaines consécutives à
      l'objectif) se mettent à jour.

### E. Rédaction assistée (brouillons + modèles)

> Sans clé API, la génération utilise un **générateur local déterministe** (gratuit) — parfait
> pour recetter. Avec `ANTHROPIC_API_KEY`, c'est l'IA Claude (voir § 4).

- [ ] **E1 — Générer un brouillon.** Sur une fiche piste → **Brouillons** → générer une
      candidature. → Statut **En génération** puis **Prêt à relire** ; le corps intègre
      l'organisation, la paire de langues et la **signature** du profil.
- [ ] **E2 — Éditer + sauvegarder.** Modifier le corps → Enregistrer. → « Brouillon enregistré ».
- [ ] **E3 — Copier.** « Copier le message » → le presse-papier contient le **corps édité**
      (pont vers le webmail).
- [ ] **E4 — Régénérer / supprimer.** → Un nouveau brouillon ; suppression possible.
- [ ] **E5 — Modèles.** Menu **Modèles** : 4 gabarits seedés (variables `{{contact}}`,
      `{{organisation}}`, `{{langues}}`, `{{bio}}`, `{{signature}}`…). Créer / modifier / supprimer.
- [ ] **E6 — Garde RGPD.** Générer pour une organisation **« Ne pas contacter »**. → **Refus**
      (code d'erreur traduit), aucun appel de génération.

### F. Passerelle email (Gmail / Outlook)

> Sans identifiants OAuth réels, un **connecteur factice** joue tout le flux (connexion, envoi,
> relève) **sans compte réel** — idéal pour recetter. Avec de vrais identifiants : voir § 4.

- [ ] **F1 — Connecter une boîte.** Réglages → **Boîte email** → « Connecter Gmail » (ou Outlook).
      → Flux OAuth (factice en dev) → boîte **Connectée** (adresse + fournisseur affichés).
- [ ] **F2 — Envoyer un brouillon.** Sur une piste au brouillon **Prêt à relire** → « Envoyer »
      (confirmation *draft-first*). → Journal **email envoyé** ; la piste **avance**
      (candidature → Contactée, relance → relance faite).
- [ ] **F3 — Relève des réponses.** Réglages → « Relever maintenant » (ou attendre le Scheduler
      ~5 min). → Une réponse captée fait passer la piste **En discussion** (aperçu au journal).
      La relève ne lit **que les fils initiés par l'app** (minimisation).
- [ ] **F4 — Relance dans le fil.** Rédiger une relance depuis « Aujourd'hui » → envoyée **dans
      le fil** d'origine.
- [ ] **F5 — Révoquer.** Réglages → « Déconnecter » (confirmation). → Boîte déconnectée, jetons
      effacés (aucun token en clair, jamais).

### G. Tableau de bord

- [ ] **G1 — Vue d'ensemble.** Menu **Tableau de bord** : **taux de réponse**, **conversion**
      (gagnées / décidées), **activité hebdomadaire** (8 semaines + ligne d'objectif),
      **répartition du pipeline**, **résultats par segment**.
- [ ] **G2 — Cohérence.** Les chiffres correspondent aux pistes/journal du seed (comptes en clair).
- [ ] **G3 — Drill-down segment.** Cliquer un segment → renvoie vers les Pistes filtrées.

### H. Sourcing — « À trier » (RSS + email)

- [ ] **H1 — File « À trier ».** Menu **À trier** : les 4 annonces du seed (badge de source, titre,
      organisation présumée, paire de langues, extrait, lien). Le **badge de navigation** indique
      le nombre en attente.
- [ ] **H2 — Relever une source.** Bouton **« Relever les annonces »**. → De nouvelles annonces de
      démonstration entrent dans la file (source factice, sans réseau). **Idempotent** : re-relever
      ne crée pas de doublon (dédoublonnage).
- [ ] **H3 — Accepter.** Sur une annonce → **Accepter** : renseigner l'organisation (nouvelle),
      la paire de langues, le segment, la priorité. → Une **Organisation** + une **Piste** sont
      créées (la piste porte la **provenance fine** : PROZ, RSS…) ; l'annonce quitte la file.
- [ ] **H4 — Fusionner.** Sur une annonce dont l'organisation **existe déjà** → **Fusionner** :
      choisir l'organisation existante. → Rattachée ; si une piste active existe, l'annonce y est
      **rattachée** (note « annonce rattachée »), sinon une piste est créée. Pas de doublon.
- [ ] **H5 — Rejeter.** → L'annonce est écartée (confirmation) et **ne réapparaît pas** à la
      prochaine relève (anti-réapparition).
- [ ] **H6 — Re-tri interdit.** Deux décisions sur la même annonce (double-clic). → La seconde est
      refusée (409) — une annonce triée est figée.
- [ ] **H7 — Gérer les flux RSS.** Réglages → **Sources** : ajouter un flux RSS (URL + nom),
      l'**activer/désactiver**, le **retirer**. Seuls les flux **actifs** sont relevés.
- [ ] **H8 — Alertes email.** *(plomberie livrée)* La Passerelle lit un **label dédié**
      « Plume/Alertes » et fait entrer les emails d'alerte dans « À trier » (provenance déduite de
      l'expéditeur). En dev, une alerte de démonstration est injectée par la relève factice.

### I. Transverses (à vérifier tout du long)

- [ ] **I1 — Bilingue FR/EN.** Basculer la langue (sélecteur). → **Tout** le texte bascule, y
      compris messages d'erreur et toasts.
- [ ] **I2 — Thème clair / sombre.** Basculer. → Lisibilité et contrastes corrects (WCAG AA).
- [ ] **I3 — Confirmations.** Toute action destructive (rejeter, supprimer, déconnecter la boîte)
      demande **confirmation**.
- [ ] **I4 — Accessibilité clavier.** Naviguer au clavier (tabulation, Échap sur les modales) ;
      après un tri, le focus revient en tête de page.
- [ ] **I5 — Responsive.** Réduire la fenêtre (mobile) : le menu se replie, le kanban défile.
- [ ] **I6 — Isolation multi-tenant.** Avec un **2e compte** (créé à froid), vérifier qu'il **ne
      voit aucune** donnée du compte `recette@plume.fr` (organisations, pistes, annonces, flux).

---

## 4. Passer des données factices aux services réels

Par défaut, tout fonctionne **sans compte externe** (générateurs/connecteurs factices). Pour
brancher les vrais services, renseigner `api/.env.local` (jamais commité) puis redémarrer :

| Fonction | Variable(s) | Effet |
|---|---|---|
| **Génération IA (Claude)** | `ANTHROPIC_API_KEY` (+ `DRAFTING_MODEL`) | remplace le générateur local par l'IA. |
| **Gmail** | `GOOGLE_CLIENT_ID` / `GOOGLE_CLIENT_SECRET` / `GOOGLE_REDIRECT_URI` | OAuth Gmail réel. |
| **Outlook** | `MICROSOFT_CLIENT_ID` / `MICROSOFT_CLIENT_SECRET` / `MICROSOFT_REDIRECT_URI` | OAuth Outlook réel. |
| **Chiffrement des jetons** | `MAILBOX_ENCRYPTION_KEY` | requis en prod (jetons chiffrés au repos). |
| **Flux RSS réels** | via l'écran **Réglages → Sources** (pas de variable) | ajouter l'URL du flux. |

> **Alertes email réelles** (lecture du label par Gmail/Outlook + parsers fins ProZ/LinkedIn/
> TranslatorsCafe) : **suivi** — la plomberie est livrée, les adaptateurs réels seront branchés
> avec de vrais échantillons d'emails.

---

## 5. Checklist récapitulative

- [ ] **A** Connexion & Compte (A1–A5) — dont révocation des sessions au changement de mot de passe.
- [ ] **B** Répertoire (B1–B7) — dont unicité de nom, RGPD « ne pas contacter », import CSV.
- [ ] **C** Pipeline (C1–C7) — dont 1 piste active/org, glisser-déposer, cycle complet.
- [ ] **D** Relances & « Aujourd'hui » (D1–D4) — cadence, objectif, série.
- [ ] **E** Rédaction assistée (E1–E6) — génération, édition, copie, garde RGPD.
- [ ] **F** Passerelle email (F1–F5) — connexion, envoi, relève, révocation.
- [ ] **G** Tableau de bord (G1–G3).
- [ ] **H** Sourcing (H1–H8) — file, relève, tri, dédoublonnage, flux, alertes email.
- [ ] **I** Transverses (I1–I6) — i18n, thème, confirmations, a11y, responsive, multi-tenant.
- [ ] **§4** Bascule vers les services réels vérifiée si besoin.

> Anomalie rencontrée ? Noter : **écran**, **étapes**, **attendu**, **observé**, **compte**,
> **langue/thème**. Les parcours nominaux sont aussi joués automatiquement par la suite **E2E**
> (`app/e2e/`) à chaque commit.
