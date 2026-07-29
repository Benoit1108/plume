<?php

declare(strict_types=1);

namespace App\Notification\Infrastructure\Scheduler;

use Doctrine\DBAL\Connection;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Notifie les relances DUES AUJOURD'HUI (au fuseau du profil de chaque tenant, défaut Europe/Paris).
 * Une relance en attente est dénormalisée sur `lead.next_follow_up_at` — un simple scan suffit.
 *
 * Tâche de maintenance GLOBALE (scheduler propriétaire, cross-tenant légitime comme les purges) en
 * un seul INSERT…SELECT. Idempotence par identifiant DÉTERMINISTE `followup_due:<lead>:<date>` +
 * ON CONFLICT DO NOTHING : le tick est HORAIRE (attrape le passage de minuit dans chaque fuseau)
 * mais chaque relance n'est notifiée qu'UNE fois par échéance. Les comptes en cours de suppression
 * sont exclus (RGPD, comme les autres ticks).
 */
#[AsMessageHandler]
final class NotifyDueFollowUpsHandler
{
    public function __construct(private readonly Connection $connection)
    {
    }

    public function __invoke(NotifyDueFollowUpsTick $tick): void
    {
        $this->connection->executeStatement(<<<'SQL'
            INSERT INTO notification (id, event_id, tenant_id, type, payload, occurred_on)
            SELECT
                gen_random_uuid(),
                'followup_due:' || l.id || ':' || to_char(l.next_follow_up_at, 'YYYY-MM-DD'),
                l.tenant_id,
                'followup_due',
                jsonb_build_object('leadId', l.id, 'orgName', o.name, 'label', l.next_follow_up_label),
                NOW()
            FROM lead l
            JOIN organization o ON o.id = l.organization_id AND o.tenant_id = l.tenant_id
            LEFT JOIN profile p ON p.tenant_id = l.tenant_id
            WHERE l.next_follow_up_at IS NOT NULL
              -- RATTRAPAGE (revue globale) : fenêtre [échéance ≤ aujourd'hui ET > il y a 7 j] au lieu
              -- d'une égalité stricte sur la date → une relance n'est plus PERDUE si le scheduler a
              -- été indisponible ou si l'échéance tombe la nuit. L'event_id déterministe
              -- `followup_due:<lead>:<date>` garantit UNE seule notification par échéance.
              AND l.next_follow_up_at::date <= (NOW() AT TIME ZONE COALESCE(p.timezone, 'Europe/Paris'))::date
              AND l.next_follow_up_at::date > (NOW() AT TIME ZONE COALESCE(p.timezone, 'Europe/Paris'))::date - 7
              -- Aligné sur le tableau « Aujourd'hui » : pas de relance sur une piste close/en pause.
              AND l.status NOT IN ('WON', 'LOST', 'PAUSED')
              AND l.tenant_id NOT IN (SELECT tenant_id FROM app_user WHERE deletion_requested_at IS NOT NULL)
            ON CONFLICT (event_id) DO NOTHING
            SQL);
    }
}
