<?php

declare(strict_types=1);

namespace App\Notification\Infrastructure\Scheduler;

use Doctrine\DBAL\Connection;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Notifie les CLIENTS GAGNÉS DORMANTS à réactiver (V2.4) : une piste `WON` sans aucune interaction
 * depuis le seuil du tenant (`profile.dormant_client_threshold_days`, défaut 120 j ; 0 = désactivé).
 *
 * Tâche de maintenance GLOBALE (scheduler propriétaire, cross-tenant légitime comme les purges), un
 * seul INSERT…SELECT. Idempotence par identifiant DÉTERMINISTE `dormant:<lead>:<YYYY-MM>` +
 * ON CONFLICT DO NOTHING : au plus UN rappel par client et par MOIS (le tick est quotidien). Les
 * comptes en cours de suppression sont exclus (RGPD). Aligné sur la section « À réactiver » d'Aujourd'hui.
 */
#[AsMessageHandler]
final class NotifyDormantClientsHandler
{
    public function __construct(private readonly Connection $connection)
    {
    }

    public function __invoke(NotifyDormantClientsTick $tick): void
    {
        $this->connection->executeStatement(<<<'SQL'
            INSERT INTO notification (id, event_id, tenant_id, type, payload, occurred_on)
            SELECT
                gen_random_uuid(),
                'dormant:' || l.id || ':' || to_char(NOW(), 'YYYY-MM'),
                l.tenant_id,
                'client_dormant',
                jsonb_build_object('leadId', l.id, 'orgName', o.name),
                NOW()
            FROM lead l
            JOIN organization o ON o.id = l.organization_id AND o.tenant_id = l.tenant_id
            LEFT JOIN profile p ON p.tenant_id = l.tenant_id
            WHERE l.status = 'WON'
              AND COALESCE(p.dormant_client_threshold_days, 120) > 0
              AND COALESCE(
                    (SELECT MAX(i.occurred_on) FROM interaction i WHERE i.tenant_id = l.tenant_id AND i.lead_id = l.id),
                    l.created_at
                  ) < NOW() - (COALESCE(p.dormant_client_threshold_days, 120) || ' days')::interval
              AND l.tenant_id NOT IN (SELECT tenant_id FROM app_user WHERE deletion_requested_at IS NOT NULL)
            ON CONFLICT (event_id) DO NOTHING
            SQL);
    }
}
