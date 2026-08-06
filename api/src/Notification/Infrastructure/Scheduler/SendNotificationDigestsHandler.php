<?php

declare(strict_types=1);

namespace App\Notification\Infrastructure\Scheduler;

use App\Account\Infrastructure\Mail\AccountMailer;
use App\Notification\Infrastructure\Mail\EmailDispatchLedger;
use App\Shared\Application\Clock;
use Doctrine\DBAL\Connection;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Envoie le digest email des notifications NON LUES de la période, par tenant, selon sa préférence
 * (`profile.digest_frequency`). Tâche de maintenance GLOBALE (scheduler propriétaire, cross-tenant
 * légitime comme les autres ticks) — même patron que NotifyDueFollowUps (join SQL direct entre
 * tables de contextes, mailer système d'Account).
 *
 * Tick QUOTIDIEN : DAILY couvre les 24 dernières heures ; WEEKLY, les 7 derniers jours mais
 * seulement le LUNDI. Chaque notification n'entre donc que dans UN digest (fenêtre glissante calée
 * sur la fréquence — aucun état « dernier envoi » à stocker). On saute les comptes en suppression ou
 * non vérifiés, et on n'envoie RIEN s'il n'y a aucune notification non lue sur la période.
 */
#[AsMessageHandler]
final class SendNotificationDigestsHandler
{
    public function __construct(
        private readonly Connection $connection,
        private readonly AccountMailer $mailer,
        private readonly Clock $clock,
        private readonly EmailDispatchLedger $ledger,
    ) {
    }

    public function __invoke(SendNotificationDigestsTick $tick): void
    {
        $now = $this->clock->now();

        $this->dispatch('DAILY', $now->modify('-1 day'));
        if ('1' === $now->format('N')) { // lundi
            $this->dispatch('WEEKLY', $now->modify('-7 days'));
        }
    }

    private function dispatch(string $frequency, \DateTimeImmutable $since): void
    {
        /** @var list<array<string, mixed>> $rows */
        $rows = $this->connection->fetchAllAssociative(
            <<<'SQL'
                SELECT n.tenant_id AS tenant_id, u.email AS email, n.type AS type, COUNT(*) AS cnt
                FROM notification n
                JOIN app_user u ON u.tenant_id = n.tenant_id
                JOIN profile p ON p.tenant_id = n.tenant_id
                WHERE n.read_at IS NULL
                  AND n.occurred_on >= :since
                  AND p.digest_frequency = :freq
                  AND u.deletion_requested_at IS NULL
                  AND u.email_verified = true
                  -- Préférences fines : on exclut les types dont le canal email est coupé (défaut = inclus).
                  AND COALESCE((p.notification_preferences -> n.type ->> 'email')::boolean, true) = true
                GROUP BY n.tenant_id, u.email, n.type
                ORDER BY u.email
                SQL,
            ['since' => $since->format('Y-m-d H:i:s'), 'freq' => $frequency],
        );

        /** @var array<string, array{tenant: string, counts: array<string, int>}> $byUser */
        $byUser = [];
        foreach ($rows as $row) {
            $email = \is_string($row['email'] ?? null) ? $row['email'] : '';
            $type = \is_string($row['type'] ?? null) ? $row['type'] : '';
            $tenantId = \is_string($row['tenant_id'] ?? null) ? $row['tenant_id'] : '';
            if ('' === $email || '' === $type || '' === $tenantId) {
                continue;
            }
            $byUser[$email]['tenant'] = $tenantId;
            $byUser[$email]['counts'][$type] = is_numeric($row['cnt'] ?? null) ? (int) $row['cnt'] : 0;
        }

        foreach ($byUser as $email => $entry) {
            // Un digest par tenant, par fréquence et par période : un rejeu ne réexpédie rien.
            if (!$this->ledger->claim(EmailDispatchLedger::digestKey($entry['tenant'], $frequency, $this->clock->now()))) {
                continue;
            }
            $this->mailer->sendNotificationDigest($email, $entry['counts']);
        }
    }
}
