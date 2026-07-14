# ADR-0017 — Captation des réponses (relève par polling, minimisation)

- **Statut : Accepté** (2026-07-14 — M2.3, décision D2 de la note M2)
- **Contexte** : rattacher automatiquement les réponses entrantes aux pistes. Push (webhooks
  Gmail Pub/Sub / Graph) exige une URL publique validée — lourd sans hébergement stable.

## Décision

1. **Polling** par le Scheduler (toutes les 5 min, tick → une commande `FetchReplies` PAR
   boîte connectée, tenant explicite) + **relève manuelle** dans les Réglages. Push réévalué
   à l'hébergement prod.
2. **Minimisation** : on ne lit QUE les fils que l'app a initiés (`threads.get` sur les
   `threadKey` des envois `SENT` dont la piste attend encore une réponse). Jamais la boîte
   entière. On ne stocke que le `snippet` TEXTE fourni par le provider (jamais de HTML),
   borné à 280 caractères, comme aperçu au journal.
3. **Sans curseur** : une piste passée en discussion sort de la relève — c'est ce qui rend
   la relève naturellement idempotente ; `recordReply()` est de toute façon idempotent
   (dette revue fin M1, soldée ici). Une seconde réponse dans un fil déjà traité n'est pas
   recapturée en V1 (« réponse = plus rien à faire »).
4. **Frontière** : Mailbox publie `ReplyCaptured` (langage publié, ADR-0003 amendé) ; la
   Prospection réagit par sa politique (`RecordReplyOnReplyCaptured`, tenant réactivé depuis
   l'event — pattern worker M2.2). Échec provider → boîte `ERROR`, visible et récupérable.

## Conséquences

- ✅ Zéro infrastructure publique ; testable de bout en bout avec le fetcher factice.
- ⚠️ Latence ≤ 5 min (acceptable pour du démarchage) ; N appels `threads.get` par relève
  (petit N : seuls les fils en attente) — curseur `history.list` si le volume montait.

---

> **Rétention du journal `interaction` (tranché en M2.4)** : le journal suit la vie de la
> PISTE — quand la suppression de piste/organisation existera, ses interactions partiront
> avec elle (mêmes transactions). Pas de rétention temporelle en V1 mono-utilisatrice ;
> à réévaluer au registre de traitement (ouverture SaaS, V2).
