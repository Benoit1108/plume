<?php

declare(strict_types=1);

namespace App\Notification\Infrastructure\Scheduler;

use Doctrine\DBAL\Connection;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Rétention (revue globale perf P1-2) : le centre de notifications grossissait sans limite. Purge
 * quotidienne des notifications LUES au-delà de 90 jours (les non-lues restent : l'utilisatrice ne
 * les a pas encore vues). Nettoie aussi les jetons de reset de mot de passe EXPIRÉS jamais réclamés.
 * Tâche de maintenance globale (scheduler propriétaire) — pas de logique tenantée.
 */
#[AsMessageHandler]
final class PurgeOldNotificationsHandler
{
    public function __construct(private readonly Connection $connection)
    {
    }

    public function __invoke(PurgeOldNotificationsTick $tick): void
    {
        $this->connection->executeStatement(
            "DELETE FROM notification WHERE read_at IS NOT NULL AND occurred_on < NOW() - INTERVAL '90 days'",
        );
        $this->connection->executeStatement(
            'DELETE FROM password_reset_token WHERE expires_at < NOW()',
        );
        // Registre d'idempotence des emails périodiques : au-delà de 90 jours, plus rien à
        // dédoublonner (la plus longue période couverte est la semaine).
        $this->connection->executeStatement(
            "DELETE FROM email_dispatch WHERE sent_at < NOW() - INTERVAL '90 days'",
        );
    }
}
