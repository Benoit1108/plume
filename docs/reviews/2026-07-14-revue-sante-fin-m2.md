# Revue de santé — fin M2 (2026-07-14)

> Process acté : revue complète à la clôture de chaque jalon. Méthode : 4 audits indépendants
> en parallèle (DDD/hexa back, sécurité, front, docs/process), findings vérifiés fichier:ligne,
> P0 et P1 structurants revérifiés à la main avant publication. Périmètre : jalon M2 — passerelle
> email (contexte `Mailbox` : OAuth Gmail+Outlook, tokens chiffrés, envoi asynchrone, captation
> des réponses par polling ; M2.0 cookies httpOnly) + non-régression M1.
> Métriques au jour de la revue : 201 tests back, 55 front (coverage seuils intacts), 12 E2E,
> CI verte, phpstan max 0 erreur, deptrac (couches + contextes) 0 violation.

## 1. Verdict

| Domaine | Note | Cible | vs fin M1 (post-remédiation) |
|---|---|---|---|
| Back — DDD/hexa/domaine/CQRS/tests | **7,5/10** | 9 | ↘ (1 P0) |
| Sécurité | **8,5/10** | 9 | ↘ (1 P1 sur le worker) |
| Front | **8,7/10** | 9 | ≈ |
| Docs/process | **7/10** | 9 | ↘ (récidive « docs de tête ») |

**Le socle sensible de M2 est objectivement solide** — les quatre audits le confirment :
chiffrement authentifié des tokens avec clé dédiée et fail-closed (ADR-0016), anti-CSRF OAuth
signé et lié au tenant (testé), minimisation RGPD réelle (relève sur nos seuls fils, aperçu
texte borné, jamais de HTML), dette « nom du contact chez Anthropic » soldée, cookies httpOnly
complets et anti-XSS effectif sur les contenus tiers affichés. Les acquis M1 ont tenu (Domain +
Application purs, outbox, isolation tenant testée sur les nouvelles surfaces).

**Sous la cible de 9/10** → 1 P0, 4 P1, remédiation nécessaire avant M3.

## 2. Le point dur : 1 P0

**`EmailSendConsumer` envoie sans garde d'idempotence — email dupliqué à la redélivrance**
(`api/src/Mailbox/Infrastructure/Consumer/EmailSendConsumer.php:53-99`, vérifié à la main).

Le consumer **ne charge jamais l'`OutboundMessage`** avant d'appeler `->send()` : la garde d'état
(`SENDING` requis) vit uniquement dans `OutboundMessage::markSent()`, donc **après** l'envoi
physique. Messenger livre *at-least-once* → scénario réel :

1. le worker envoie l'email (Gmail/Outlook), l'email **part réellement** ;
2. le worker est tué (OOM/déploiement) ou le commit de `MarkEmailSent` échoue (blip DB) ;
3. `settle()` n'absorbe que `OutboundMessageNotFound|NotSending` — tout autre échec propage ;
4. Messenger redélivre (`max_retries: 3`) → le consumer **ré-envoie un second email au prospect**.

C'est exactement la classe de bug traitée en P0 fin M1 pour `Draft` : la leçon a été appliquée
à l'**agrégat** (`OutboundMessage` a ses gardes, testées) mais **pas à l'ordonnancement du
consumer**. Impact : email de démarchage dupliqué (réputation, gêne RGPD).
Correctif : charger l'`OutboundMessage` et exiger `SENDING` **avant** `->send()` (no-op si déjà
réglé) ; la fenêtre résiduelle « envoi réussi / crash avant markSent » demande une clé
d'idempotence provider ou un marqueur « tentative » — à tracer.

## 3. Findings consolidés (dédupliqués entre audits)

### P1 — avant M3

| # | Finding | Où |
|---|---|---|
| 1 | **Chemin d'écriture worker `RecordReply` sans garde de tenant** (back + sécurité, indépendamment). La commande `RecordReply` ne porte pas de tenantId ; `RecordReplyHandler` charge le Lead par UUID via `DoctrineLeadRepository::get()`, dont le commentaire promet une isolation par SQLFilter **inactif hors HTTP** (le worker ne réactive pas le filtre). La policy `set()` le `TenantContext` mais cela n'active pas le filtre Doctrine. Seul chemin worker non gardé — ses 4 jumeaux (`CompleteDraft`/`FailDraft`/`MarkEmailSent`/`MarkEmailFailed`) ont la ceinture-bretelles. Non exploitable aujourd'hui (leadId tenant-dérivé), mais casse le « fail-closed systématique ». | `RecordReply.php:11`, `RecordReplyHandler.php:24`, `DoctrineLeadRepository.php:28` (commentaire trompeur) |
| 2 | **`RecordFollowUp` / `ContactLead` : même faille** sur le chemin async D3 (chargent le Lead sans tenant explicite ; `ContactLead` a un garde-fou indirect via la gateway RGPD scopée, pas `RecordFollowUp`). | `RecordFollowUpHandler.php:24`, `ContactLeadHandler.php:27` |
| 3 | **Adaptateurs Outlook livrés sans aucun test.** `OutlookMailSender` (envoi en 2 temps createReply/send) et `OutlookReplyFetcher` (filtre OData, conversationId) n'ont aucun test MockHttpClient ; le fonctionnel route vers les Fake. Fonctionnalité phare M2.4 non exercée — un bug de mapping Graph passerait en prod. | `OutlookMailSender.php`, `OutlookReplyFetcher.php` |
| 4 | **`SendDraft` non idempotent par brouillon** : pas de contrainte unique `(tenant_id, draft_id)` ; un double-clic/rejeu réseau sur `/drafts/{id}/send` crée 2 envois → 2 emails. | `SendDraftHandler.php:59`, `OutboundMessage.orm.xml` |
| 5 | **Docs « de tête » périmées — RÉCIDIVE de fin M1** : README (« prochaine étape : M2 » alors que livré, structure « Mailbox à venir »), `api/src/README.md` (Mailbox « à peupler »), `api/src/Mailbox/README.md` (stub avec **faux noms de ports** `OutboundMailer`/`MailboxReader`), DOMAIN-MODEL § Passerelle resté à la conception abstraite (`CompteEmailConnecte`, aucun event), ROADMAP dont le titre dit « ✅ » avec les 5 livrables cochés `[ ]`. | `README.md`, `api/src/README.md`, `api/src/Mailbox/README.md`, `DOMAIN-MODEL.md:120`, `ROADMAP.md:66` |
| 6 | **i18n : clé `mailbox.statuses.NONE` manquante** — le badge d'état affiche le libellé brut « mailbox.statuses.NONE » (FR et EN) sur l'écran principal de la fonctionnalité, à l'état par défaut (aucune boîte). Seule interpolation dynamique sans repli. | `settings.vue:157`, `i18n/locales/{fr,en}.json` |
| 7 | **README sans section « Activer la passerelle email »** : les variables `GOOGLE_*`/`MICROSOFT_*`/`MAILBOX_ENCRYPTION_KEY` et le comportement « sans identifiants → connecteur factice » ne vivent que dans `.env`/`services.yaml`/ADR — la porte d'entrée n'en dit rien (alors qu'elle documente l'IA). | `README.md` |

### P2 — dette tracée (sélection ; listes complètes dans les 4 rapports)

- **Duplication DRY** : `mintAccessToken` répliqué ×4 (Gmail/Outlook × sender/fetcher) + endpoints
  token ×2 → extraire un `TokenMinter` par provider (même odeur que l'hydratation DBAL soldée fin M1).
- **Tests manquants** : `FetchRepliesHandler` (bascule ERROR), policies (`AdvanceLeadOnEmailSent`,
  `RecordReplyOnReplyCaptured` branches Conflict/NotFound), `EmailSendConsumer::settle`/recipient-unavailable ;
  aucun E2E Outlook (Gmail seulement). Le trou de test sur la chaîne de relève amplifie le P1 #1.
- **Sécurité fine** : contraintes `NotBlank`/`Choice` sur `code`/`state`/`provider` **inertes**
  (groupes de validation jamais activés — backstoppées par les processors, mais contrat non garanti) ;
  `/mailbox/fetch-replies` sans rate limiter (l'envoi en a un) ; « fail-fast au boot » d'ADR-0016 est
  en réalité fail-on-first-use (service paresseux) ; nom de l'organisation cible envoyé à Anthropic
  (périmètre ADR-0014, à acter au registre des traitements).
- **Domaine** : `ConnectedMailbox::markSyncFailed` sans `guardOperational` (REVOKED→ERROR possible) ;
  `syncCursor` champ mort ; `ConnectMailboxHandler` jette la cause sans chaînage `previous`.
- **Front** : callback OAuth sans `role="status"`/`aria-live` ni `<h1>` ; 409 non distingués sur
  `settings.save`/`connectMailbox` et 3 mutations de `leads/[id].vue` (régression partielle de
  `errorToastTitle`) ; `LeadDraftsSection.vue` à 469 lignes (extraire le slideover éditeur — dette M1
  qui repointe) ; param de route `[provider]` mort + commentaire « Google » trompeur.
- **Docs** : enum `MailProviderName` et event `EmailSendRequested` non nommés au glossaire ; pas d'ADR
  pour la bascule httpOnly (M2.0) ni pour le découpage 3 ports+registres ; `MAILBOX_FAKE_REDIRECT_URI`
  absente du `.env` ; noms métier périmés (`enregistrerReponse()`) résiduels ; OVERVIEW §sécurité
  légèrement décorrélé du réel M2 (Vault vs env, label vs relève par fil).
- **Réponses suivantes d'un fil invisibles** : une piste passée IN_DISCUSSION sort de la relève →
  seule la 1re réponse du prospect entre au journal (décision produit à confirmer/tracer, déjà notée fin M1).

## 4. Plan de remédiation proposé (3 lots)

- **Lot A — back critique/sécurité** : P0 (garde d'idempotence `OutboundMessage` dans le consumer avant
  `->send()`) ; P1 #1/#2 (tenant explicite + vérif au chargement sur `RecordReply`/`RecordFollowUp`/
  `ContactLead`, commentaire `DoctrineLeadRepository` corrigé) ; P1 #4 (index unique `(tenant_id, draft_id)`) ;
  P1 #3 (tests MockHttpClient Outlook sender+fetcher) ; tests de la chaîne de relève + redélivrance envoi.
- **Lot B — front** : P1 #6 (clé `mailbox.statuses.NONE`), 409 distingués partout, a11y callback,
  extraction du slideover éditeur, E2E Outlook, param `[provider]` nettoyé.
- **Lot C — docs/process** : resynchroniser README (+ section passerelle)/`api/src/README`/
  `Mailbox/README` (vrais ports)/DOMAIN-MODEL/ROADMAP ; DoD M2 corrigée ; ADR httpOnly + registres ;
  glossaire (`MailProviderName`, `EmailSendRequested`) ; `.env` (`MAILBOX_FAKE_REDIRECT_URI`).

Projection après remédiation : back ≥ 9 (P0 + les 4 P1 concentrent l'écart), sécurité ≥ 9,5
(le seul P1 est le chemin worker), front ≥ 9, docs ≥ 9.

## 5. Ce qui est objectivement solide (à préserver)

- Crypto des tokens : sodium secretbox authentifié, clé dédiée, fail-closed, **jamais de clair** en
  base/log/réponse (read model sans colonnes token, vérifié), effacé à la révocation.
- OAuth : state signé HMAC lié tenant+provider, TTL, `client_secret` strictement serveur, scopes minimaux ;
  cross-tenant rejeté (testé).
- RGPD : minimisation de la relève réelle (nos fils, snippet texte 280, zéro HTML) ; doNotContact
  re-vérifiée **par le worker** au moment de l'envoi, double niveau org+contact ; dette contact soldée.
- Frontières DDD réelles (Domain/Application purs, deptrac contextes 0 violation) ; events = langage
  publié ; gardes d'état de `OutboundMessage` appliquées d'emblée (leçon P0 fin M1 tenue au niveau agrégat).
- Front M2.0 exemplaire (aucun token en JS, anti-XSS effectif sur contenus tiers), i18n 319 clés
  strictement paritaires, seuils de coverage jamais baissés.

---

## Post-scriptum — remédiation appliquée (2026-07-15, 3 lots)

**Lot A — back critique/sécu.** P0 soldé : `EmailSendConsumer` charge l'`OutboundMessage` et
exige `SENDING` **avant** `->send()` — une redélivrance d'un envoi réglé n'expédie plus de
second email (verrouillé par un test de redélivrance) ; fenêtre résiduelle envoi/crash-avant-markSent
documentée (clé d'idempotence provider → M3). P1 : `RecordReply`/`RecordFollowUp`/`ContactLead`
portent un `tenantId` **vérifié au chargement** (le worker le fournit, HTTP s'appuie sur le
SQLFilter) — plus de chemin d'écriture async sans garde tenant ; commentaire trompeur de
`DoctrineLeadRepository::get()` corrigé. `SendDraft` **anti double-envoi** (garde applicative +
index partiel unique `(tenant_id, draft_id)` hors FAILED ; double POST → 409, testé).
Adaptateurs **Outlook testés** (MockHttpClient), après refactor DRY des 4 `mintAccessToken` en
un `AccessTokenMinter` par fournisseur (+ son test). P2 : `markSyncFailed` auto-porte son
invariant, `syncCursor` justifié. **209 tests back.**

**Lot B — front.** Clé i18n `mailbox.statuses.NONE` + badge du fournisseur dans les Réglages ;
409 métier distingués sur les mutations restantes ; callback OAuth en `role="status"`/`aria-live`
+ `<h1>`, `aria-label` sur la recherche du Répertoire ; **`LeadDraftEditor` extrait**
(LeadDraftsSection 469 → 272 lignes). **E2E Outlook** ajouté — il a débusqué et fait corriger un
vrai bug (`reconnect()` gardait l'ancien fournisseur) ; smoke Répertoire rendu robuste (recherche,
tenant e2e partagé). **55 front, 13 E2E.**

**Lot C — docs/process.** README (état M2 + structure + section « Activer la passerelle email »),
`api/src/README` (Mailbox livré), `api/src/Mailbox/README` (vrais ports), DOMAIN-MODEL §
Passerelle (agrégats/events réels), ROADMAP (5 livrables cochés), DoD M2 corrigée, glossaire
(`MailProviderName`, `EmailSendRequested`, `AccessTokenMinter`), `.env` (`MAILBOX_FAKE_REDIRECT_URI`),
**ADR-0018** (auth httpOnly) et **ADR-0019** (adaptateurs par fournisseur) écrits + indexés.

### Notes après remédiation

| Domaine | Avant | Après |
|---|---|---|
| Back (DDD/domaine/CQRS/tests) | 7,5 | **9,5** — P0 + les 4 P1 soldés, chemin worker verrouillé, Outlook testé, bug de bascule de fournisseur corrigé |
| Sécurité | 8,5 | **9,5** — dernier chemin worker gardé, double-envoi bordé ; restes assumés (fenêtre d'idempotence provider, org→Anthropic) tracés M3 |
| Front | 8,7 | **9** — i18n/a11y/409 soldés, composant dégraissé, E2E Outlook |
| Docs/process | 7 | **9,5** — docs de tête resynchronisées, 2 ADRs manquants écrits |

**Objectif ≥ 9/10 partout : atteint.** Restes assumés et datés (ROADMAP § M3) : fenêtre
d'idempotence provider (envoi réussi/crash avant markSent → clé d'idempotence), réponses
suivantes d'un fil non recapturées (« réponse = fin »), nom d'organisation transmis à Anthropic
(à acter au registre des traitements), push vs polling à l'hébergement prod.
