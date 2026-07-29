# Notification — Centre de notifications (Generic)

Notifie l'utilisatrice des moments qui comptent, in-app. **Projection** pure (pas d'agrégat) :
le contexte ne fait qu'**observer** les événements des autres contextes et matérialiser des lignes.

Voir [ADR-0028](../../../docs/architecture/decisions/0028-centre-notifications-projection.md).

## Livré

- **`Infrastructure/Projection/NotificationProjector`** : consomme des domain events sur `event.bus`
  (ASYNCHRONE, tenant posé par message → RLS) et projette dans la table `notification` (DBAL pur,
  **hors ORM**, même patron que `interaction`). **Idempotent** via `ON CONFLICT (event_id) DO NOTHING`.
  Types : `reply_received` (`ReplyCaptured`), `email_send_failed` (`EmailSendFailed`).
- **`Infrastructure/Scheduler/`** : `NotifyDueFollowUps` (relances dues — **fenêtre de rattrapage** 7 j +
  filtre de statut `NOT IN WON/LOST/PAUSED`) ; `PurgeOldNotifications` (quotidien : notifications **lues**
  > 90 j + jetons de reset expirés).
- **`Application/`** : `GetNotifications` (query, read model `NotificationFeed`/`NotificationView`),
  `MarkNotificationRead` / `MarkAllNotificationsRead` (commands, port `NotificationMarker`).
- **`Infrastructure/ApiResource/`** : `NotificationResource` (provider liste + processors *mark-read* /
  *mark-all-read*). Badge « cloche » côté front (région `aria-live`).

## À brancher (mêmes patrons)

Boîte déconnectée, candidats à trier, **digests email** : un abonnement de plus dans le projecteur ou un
tick supplémentaire — rien à changer dans les contextes émetteurs.
