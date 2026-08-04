<?php

declare(strict_types=1);

namespace App\Notification\Infrastructure\Scheduler;

use App\Account\Infrastructure\Mail\AccountMailer;
use App\Shared\Application\Clock;
use Doctrine\DBAL\Connection;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Bilan HEBDOMADAIRE par email (V2.4) : récap des 7 derniers jours par tenant (démarches, réponses,
 * objectif), pour entretenir la régularité. Opt-out via `profile.weekly_report_enabled`.
 *
 * Tick QUOTIDIEN mais n'agit que le LUNDI (fenêtre glissante 7 j, aucun état « dernier envoi »).
 * Chiffres AGRÉGÉS uniquement (jamais de contenu de piste — minimisation, comme le digest).
 * N'envoie RIEN lors d'une semaine sans activité (démarche ou réponse) : un recap vide serait du bruit.
 * Exclut les comptes en suppression / non vérifiés. Maintenance globale (scheduler propriétaire).
 */
#[AsMessageHandler]
final class SendWeeklyReportsHandler
{
    public function __construct(
        private readonly Connection $connection,
        private readonly AccountMailer $mailer,
        private readonly Clock $clock,
    ) {
    }

    public function __invoke(SendWeeklyReportsTick $tick): void
    {
        $now = $this->clock->now();
        if ('1' !== $now->format('N')) { // lundi uniquement
            return;
        }
        $since = $now->modify('-7 days')->format('Y-m-d H:i:s');

        /** @var list<array<string, mixed>> $rows */
        $rows = $this->connection->fetchAllAssociative(
            <<<'SQL'
                SELECT
                    u.email AS email,
                    p.weekly_goal AS goal,
                    COUNT(*) FILTER (WHERE i.type IN ('contacted', 'followed_up')) AS outreach,
                    COUNT(*) FILTER (WHERE i.type = 'reply') AS replies
                FROM profile p
                JOIN app_user u ON u.tenant_id = p.tenant_id
                JOIN interaction i ON i.tenant_id = p.tenant_id AND i.occurred_on >= :since
                WHERE p.weekly_report_enabled = true
                  AND u.deletion_requested_at IS NULL
                  AND u.email_verified = true
                GROUP BY u.email, p.weekly_goal
                HAVING COUNT(*) FILTER (WHERE i.type IN ('contacted', 'followed_up', 'reply')) > 0
                ORDER BY u.email
                SQL,
            ['since' => $since],
        );

        foreach ($rows as $row) {
            $email = \is_string($row['email'] ?? null) ? $row['email'] : '';
            if ('' === $email) {
                continue;
            }
            $this->mailer->sendWeeklyReport(
                $email,
                is_numeric($row['outreach'] ?? null) ? (int) $row['outreach'] : 0,
                is_numeric($row['replies'] ?? null) ? (int) $row['replies'] : 0,
                is_numeric($row['goal'] ?? null) ? (int) $row['goal'] : 0,
            );
        }
    }
}
