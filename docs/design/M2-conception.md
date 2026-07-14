# M2 — Passerelle email (note de cadrage)

> Statut : **validée** (2026-07-14, D1→D6 tranchées), en cours · Prérequis : M1 clôturé + revue fin M1 appliquée
> (notes ≥ 9/10). Références : ADR-0007 (passerelle OAuth, **pas de tracking d'ouverture**),
> ADR-0014 (IA — dette « interpolation {{contact}} » à solder ici), ROADMAP § M2 (dettes
> actées fin M1). Métier en FR, code en EN (glossaire : Passerelle email = `Mailbox`,
> Compte email connecté = `ConnectedMailbox`, Envoi = `OutboundMessage`, Réponse = `Reply`).

## 1. Objectif & résultat attendu

Fermer la boucle du démarchage : aujourd'hui la traductrice **copie** ses brouillons vers son
webmail et saisit les réponses **à la main** (« Réponse reçue »). Fin M2 :

1. sa boîte est **connectée** (OAuth, tokens chiffrés, révocable) ;
2. un brouillon relu **s'envoie depuis sa vraie adresse** en un clic (draft-first préservé :
   rien ne part sans relecture) ;
3. les **réponses arrivent toutes seules** sur la piste (threading), qui passe en discussion
   et annule la relance en attente — le réflexe « réponse = plus rien à faire » devient vrai ;
4. une **relance s'envoie dans le même fil** que le message d'origine.

Le pipeline, le journal, l'objectif hebdo et le tableau de bord existants se nourrissent de
ces événements sans changement de leur modèle : `EmailSent` alimente `contact()`/relances,
`ReplyReceived` existe déjà.

## 2. Périmètre & découpage en slices livrables

| Slice | Contenu | Valeur |
|---|---|---|
| **M2.0 — préambule sécurité** | Cookies tokens **httpOnly** (dette actée : les contenus d'emails tiers arrivent, la surface XSS devient réelle) + sanitisation systématique des contenus entrants affichés | Prérequis de confiance |
| **M2.1 — Boîte connectée** | OAuth (un fournisseur d'abord, cf. D1), agrégat `ConnectedMailbox`, tokens chiffrés au repos, écran Réglages § Boîte email (connecter/état/déconnecter + révocation) | La fondation |
| **M2.2 — Envoi** | Bouton **Envoyer** sur un brouillon READY (à côté de Copier), `OutboundMessage` (statut SENT/FAILED, `Message-ID` conservé), garde RGPD/opt-out, signature du profil, journal `email_sent`, piste marquée contactée (cf. D3) | Fin du copier-coller |
| **M2.3 — Réponses** | Relève par **polling** (Scheduler, cf. D2), rattachement par `In-Reply-To`/`References` → `recordReply()` rendu **idempotent** (dette), aperçu de la réponse dans la timeline (sanitisé) | Fin de la saisie manuelle |
| **M2.4 — Relances en fil + solde des dettes** | Relance envoyée dans le thread d'origine (« Aujourd'hui » : Envoyer la relance), interpolation locale de `{{contact}}` (le nom ne part plus chez Anthropic — ADR-0014), rétention/effacement du journal tracé, 2ᵉ fournisseur OAuth si D1 l'a différé | La boucle complète |

**Hors périmètre M2** (tracé) : tracking d'ouverture (**jamais** — ADR-0007), séquences de
relance multi-étapes (V2), multi-boîtes par tenant, composer HTML riche (texte + signature
en V1), ingestion d'alertes (M3 — la passerelle la prépare), tableau de bord enrichi
(reste en réserve de fin de jalon).

## 3. Contexte `Mailbox` (nouveau) — *Supporting technique*

### Agrégats & VOs
- **`ConnectedMailbox`** (racine) : `MailboxId`, `TenantId`, `provider` (`GMAIL` | `OUTLOOK`),
  `emailAddress`, tokens OAuth **chiffrés** (VO `EncryptedToken` — jamais en clair hors
  mémoire), `status` (`CONNECTED` | `REVOKED` | `ERROR`), `connectedAt`, `lastSyncAt?`,
  curseur de relève (`historyId`/`deltaLink` selon provider). Un seul par tenant en V1.
  Events : `MailboxConnected`, `MailboxRevoked`, `MailboxSyncFailed`.
- **`OutboundMessage`** (racine) : `OutboundMessageId`, `TenantId`, `leadId`, `draftId?`,
  `threadKey` (`Message-ID` racine du fil), destinataire, sujet, statut
  (`SENDING` | `SENT` | `FAILED` avec code de raison **affichable**, pattern M1.4),
  `sentAt?`. Events : `EmailSent` (riche : leadId, threadKey), `EmailSendFailed`.
  Le CORPS n'est pas dupliqué dans l'agrégat : il part du brouillon et vit dans la boîte.

### Ports (Application, par fournisseur derrière une même interface)
- `MailProvider` : `send(...)`, `fetchReplies(cursor)`, `revoke()` — implémentations
  `GmailProvider` / `OutlookProvider` (Infrastructure, ACL comme `ClaudeMessageGenerator`).
- `TokenCipher` : chiffre/déchiffre (implémentation **sodium** `secretbox`, clé dédiée
  `MAILBOX_ENCRYPTION_KEY` en env — jamais la clé JWT, jamais commitée).
- Frontières existantes réutilisées : `Drafting` reste ignorant de l'envoi — Mailbox lit le
  brouillon par un port (`DraftGateway`, tenant explicite, pattern `LeadGateway` M1.4) ;
  la Piste réagit aux **events** (`EmailSent` → `contact()`/`recordFollowUp()`,
  réponse captée → `recordReply()`), jamais par appel direct.

### Flux
1. **Connexion** : redirect OAuth (scopes minimaux : envoi + lecture) → callback → tokens
   chiffrés → `MailboxConnected`. Refresh automatique ; échec de refresh → `status: ERROR`
   visible dans Réglages (reconnexion en un clic).
2. **Envoi** (asynchrone, worker — pattern M1.4) : commande `SendDraft` → gardes (brouillon
   READY, boîte connectée, RGPD **re-vérifiée**, garde d'état contre la redélivrance —
   leçon du P0 fin M1 appliquée d'entrée) → `MailProvider::send` → `EmailSent`/`EmailSendFailed`.
3. **Relève** : Scheduler → `FetchReplies` par boîte connectée → messages entrants rattachés
   par `In-Reply-To`/`References` aux `threadKey` connus → `RegisterReply(leadId, …)` →
   `recordReply()` (idempotent), extrait **texte** sanitisé pour la timeline.

## 4. Sécurité & RGPD (le gros morceau du jalon)

- **Tokens OAuth chiffrés au repos** (sodium, clé env dédiée, rotation documentée) ; scopes
  minimaux ; révocation côté provider ET côté app (`MailboxRevoked` trace l'instant).
- **httpOnly en préambule** (M2.0) : les tokens d'app quittent le stockage lisible par JS
  avant que du contenu tiers ne s'affiche. Sanitisation stricte de tout contenu entrant
  (texte extrait, jamais de HTML injecté ; zéro `v-html`, règle existante).
- **Opt-out RGPD** : `doNotContact` bloque l'envoi à la commande ET au worker (double garde,
  pattern M1.4) ; un opt-out détecté dans une réponse reste un geste **manuel** en V1
  (bouton existant sur l'organisation) — détection automatique = M3/parsers.
- **Minimisation** : la relève ne lit que les fils initiés par l'app (threading), pas la
  boîte entière ; on ne stocke que l'extrait texte utile à la timeline.
- Échecs = **codes stables** i18n (pattern M1.4), détail technique en logs sans contenu.

## 5. API & Front (esquisse)

- API : `GET/POST /mailbox` + `/mailbox/oauth/callback`, `DELETE /mailbox` (révoque),
  `POST /drafts/{id}/send`, `POST /leads/{id}/send-follow-up` ; timeline enrichie
  (`email_sent`, `reply` avec aperçu). Rate limiting sur l'envoi (réutilise le pattern
  générations : l'envoi coûte de la réputation, pas des tokens).
- Front : Réglages § « Boîte email » (état, connecter/déconnecter, erreurs de sync) ;
  l'éditeur de brouillon gagne **Envoyer** (avec confirmation « draft-first » : récap
  destinataire/sujet) quand une boîte est connectée, sinon Copier reste seul ;
  « Aujourd'hui » : « Envoyer la relance » sur les relances dues (M2.4).

## 6. Tests

Pyramide habituelle + spécificités : adaptateurs providers testés sur **réponses HTTP
enregistrées** (MockHttpClient, pattern ClaudeMessageGeneratorTest) — aucun test ne touche
un vrai compte ; E2E avec un **FakeMailProvider** (port oblige) qui simule envoi + réponse
entrante pour jouer la boucle complète ; tests d'idempotence (redélivrance d'envoi, double
réponse) et d'isolation tenant sur toutes les nouvelles surfaces (acquis à maintenir).

## 7. ADR à acter

- **ADR-0016 — Chiffrement des tokens OAuth** (algorithme, clé dédiée, rotation, stockage).
- **ADR-0017 — Captation des réponses** (polling vs push, curseurs par provider, minimisation).
- ADR-0007 reste la référence (amender si D1/D2 s'en écartent).

## 8. Décisions — **validées** (2026-07-14)

1. **D1 — Gmail en premier**, Outlook suit (M2.4) — le port `MailProvider` couvre les deux. ✔
2. **D2 — Relève par polling** (Scheduler, ~5 min) ; push réévalué à l'hébergement prod. ✔
3. **D3 — Envoyer marque la piste** (candidature → `contact()`, relance → `recordFollowUp()`). ✔
4. **D4 — Texte + signature en V1** (pas de composer HTML). ✔
5. **D5 — M2.0 d'abord** (httpOnly + sanitisation avant tout contenu tiers). ✔
6. **D6 — Une seule boîte connectée en V1, mais le MULTI-BOÎTES est prévu** : `ConnectedMailbox`
   a sa propre identité (`MailboxId`), l'unicité par tenant est un simple invariant V1 à lever
   (pas une hypothèse structurelle — pas de PK tenant, pas de « the mailbox » dans les ports). ✔

## 9. Definition of Done — M2

- [x] M2.0 : cookies httpOnly (tokens posés/rafraîchis/effacés par l'API, extracteur cookie
      + Bearer conservé pour l'outillage, `/me` pour l'identité, même-origine en prod via le
      proxy Nitro) + garde anti-XSS outillée (`vue/no-v-html: error`) — la sanitisation des
      contenus entrants s'appliquera à leur arrivée (M2.3).
- [x] Boîte connectée/déconnectée depuis les Réglages, tokens chiffrés (ADR-0016 écrit),
      statut d'erreur visible et récupérable (reconnexion en un clic), state OAuth anti-CSRF
      lié au tenant, connecteur factice par défaut (dev/CI/E2E sans compte réel).
- [x] Envoi d'un brouillon relu depuis la vraie adresse (garde RGPD double — commande ET
      worker —, garde d'état anti-redélivrance, codes d'échec i18n, rate limiting 20/h/tenant),
      journal `email_sent`/`email_send_failed`, piste avancée (D3 : politique idempotente qui
      réactive le tenant depuis l'event — le pattern worker acté en M1.2 est désormais outillé).
- [ ] Réponses captées par threading → `recordReply()` **idempotent**, relance annulée,
      aperçu sanitisé en timeline.
- [ ] Relance envoyée dans le fil d'origine depuis « Aujourd'hui » et la fiche.
- [ ] Dettes ADR-0014 soldées : interpolation locale `{{contact}}`, rétention du journal tracée.
- [ ] Pyramide complète (adaptateurs sur réponses enregistrées, FakeMailProvider en E2E,
      idempotence + isolation tenant), CI verte, openapi/glossaire/ROADMAP à jour.
- [ ] **Revue de santé fin M2** (process acté).
