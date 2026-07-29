# ADR-0028 — Centre de notifications : projection DBAL sur événements, hors ORM

- **Statut** : Accepté (2026-07-29, V2.x). Prolonge [ADR-0013](0013-read-models-v1.md) (read models projetés).
- **Contexte** : notifier l'utilisatrice in-app des moments qui comptent (une **réponse** est arrivée, un
  **envoi a échoué**, une **relance est due**). Contrainte d'architecture : **ne pas coupler** les contextes
  émetteurs (Mailbox, Prospecting) à une notion de « Notification » — la frontière cross-contexte n'autorise
  que les **events publiés** et les ports.

## Décision

- `notification` est une **table de projection** (DBAL pur, **hors métadonnées ORM** : `schema_filter`, le
  diff Doctrine ne la touche jamais), même patron que le journal `interaction` : lecture/écriture SQL direct,
  **fail-closed** sur `tenant_id`, **RLS** activée (tenant posé par message côté worker).
- `NotificationProjector` **s'abonne aux domain events** existants sur `event.bus` (langage publié d'autres
  contextes, autorisé) et matérialise une ligne — **aucun nouvel agrégat**, aucun couplage inverse :
  - `ReplyCaptured` → `reply_received` (le moment le plus précieux du produit) ;
  - `EmailSendFailed` → `email_send_failed` (le message ne partira pas tout seul).
- **Idempotence** : insertion `ON CONFLICT (event_id) DO NOTHING` — les retries Messenger et les rejeux ne
  créent **jamais** de doublon (l'`event_id` du domain event fait clé).
- **Notifications planifiées** (pas d'event déclencheur) : `NotifyDueFollowUpsTick` (scheduler) → handler
  avec **fenêtre de rattrapage** (relances dues aujourd'hui **et jusqu'à 7 j en arrière**, pour ne rien
  perdre un jour sans run) **et filtre de statut** (`NOT IN (WON, LOST, PAUSED)` — pas de notification pour
  une piste terminée ou en pause).
- **Purge** : `PurgeOldNotifications` (quotidien) efface les notifications **lues de plus de 90 j** (et au
  passage les jetons de reset expirés). Le centre ne gonfle pas indéfiniment.
- **Exposition** : `NotificationResource` (API Platform) — provider (liste), processors *mark-read* et
  *mark-all-read* ; badge « cloche » côté front (région `aria-live`).

## Conséquences

- ✅ **Découplage total** : les contextes émetteurs ignorent la Notification ; ajouter un type = **un
  abonnement de plus** dans le projecteur (ou un tick), rien à changer ailleurs.
- ✅ Table hors ORM → migrations explicites, jamais de `DROP` proposé par le diff.
- ⚠️ Projection **asynchrone** = cohérence à terme : une notification peut apparaître avec un léger délai
  (acceptable pour un centre de notifications).
- Types couverts : `reply_received`, `email_send_failed`, relances dues, **`mailbox_disconnected`** (reconnexion
  requise — filtrée au cas actionnable). « Candidats à trier » reste à brancher (même patron).
- ✅ **Digests email (livré)** : préférence par tenant `profile.digest_frequency` (NONE/DAILY/WEEKLY, défaut
  DAILY) ; tick quotidien `SendNotificationDigests` → email bilingue récapitulant les notifications **non
  lues de la période** (fenêtre 24 h / 7 j calée sur la fréquence — aucun état « dernier envoi » à stocker),
  comptes en suppression/non vérifiés exclus, rien envoyé si rien à résumer. Sans PII (comptages par type).
