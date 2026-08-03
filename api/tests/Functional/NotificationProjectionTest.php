<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Mailbox\Domain\Mailbox\Event\MailboxSyncFailed;
use App\Mailbox\Domain\Outbound\Event\EmailSendFailed;
use App\Mailbox\Domain\Outbound\Event\ReplyCaptured;
use App\Notification\Infrastructure\Projection\NotificationProjector;
use App\Notification\Infrastructure\Scheduler\NotifyDueFollowUpsHandler;
use App\Notification\Infrastructure\Scheduler\NotifyDueFollowUpsTick;
use App\Sourcing\Domain\CandidateLead\Event\CandidateLeadIngested;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Uid\Uuid;

/**
 * Centre de notifications — les producteurs : projection des events (idempotente, retries Messenger
 * absorbés) et tick « relance due aujourd'hui » (déterministe : une notification par relance et par
 * échéance, re-run sans doublon, comptes en suppression exclus).
 */
final class NotificationProjectionTest extends KernelTestCase
{
    private Connection $connection;

    protected function setUp(): void
    {
        $connection = static::getContainer()->get(Connection::class);
        \assert($connection instanceof Connection);
        $this->connection = $connection;
        $this->connection->executeStatement('TRUNCATE TABLE notification, lead, organization, profile, app_user RESTART IDENTITY CASCADE');
    }

    private function countFor(string $tenantId): int
    {
        $value = $this->connection->fetchOne('SELECT COUNT(*) FROM notification WHERE tenant_id = ?', [$tenantId]);

        return is_numeric($value) ? (int) $value : -1;
    }

    public function testProjectsReplyAndFailureEventsIdempotently(): void
    {
        $tenant = Uuid::v7()->toRfc4122();
        $projector = new NotificationProjector($this->connection);

        $reply = new ReplyCaptured($tenant, 'lead-1', 'thread-1', 'Merci pour votre message, ça m\'intéresse !', new \DateTimeImmutable('2026-07-28 09:00:00'));
        $reply->assignId('evt-reply-1'); // l'outbox assigne l'id ; ici on le simule (redélivrance = même id)
        $projector->onReplyCaptured($reply);
        $projector->onReplyCaptured($reply); // redélivrance Messenger → aucun doublon

        $failure = new EmailSendFailed($tenant, 'msg-1', 'lead-1', 'token_expired', new \DateTimeImmutable('2026-07-28 10:00:00'));
        $failure->assignId('evt-failure-1');
        $projector->onEmailSendFailed($failure);

        self::assertSame(2, $this->countFor($tenant));
        $types = $this->connection->fetchFirstColumn('SELECT type FROM notification WHERE tenant_id = ? ORDER BY occurred_on', [$tenant]);
        self::assertSame(['reply_received', 'email_send_failed'], $types);
    }

    public function testProjectsCandidateToTriageIdempotently(): void
    {
        $tenant = Uuid::v7()->toRfc4122();
        $projector = new NotificationProjector($this->connection);

        $ingested = new CandidateLeadIngested('cand-1', $tenant, 'LINKEDIN', 'hash-1', new \DateTimeImmutable('2026-07-30 09:00:00'));
        $ingested->assignId('evt-ingested-1');
        $projector->onCandidateLeadIngested($ingested);
        $projector->onCandidateLeadIngested($ingested); // redélivrance → aucun doublon

        self::assertSame(1, $this->countFor($tenant));
        /** @var array{type: string, payload: string} $row */
        $row = $this->connection->fetchAssociative('SELECT type, payload FROM notification WHERE tenant_id = ?', [$tenant]);
        self::assertSame('candidate_to_triage', $row['type']);
        self::assertStringContainsString('cand-1', $row['payload']);
        self::assertStringContainsString('LINKEDIN', $row['payload']);
    }

    public function testNotifiesOnlyWhenTheMailboxNeedsReconnection(): void
    {
        $tenant = Uuid::v7()->toRfc4122();
        $projector = new NotificationProjector($this->connection);

        // Incident transitoire (réseau/5xx) : la boîte est ERROR mais se rétablira → PAS de notification.
        $transient = new MailboxSyncFailed($tenant, 'mbx-1', 'sync_failed', new \DateTimeImmutable('2026-07-28 08:00:00'));
        $transient->assignId('evt-sync-1');
        $projector->onMailboxSyncFailed($transient);
        self::assertSame(0, $this->countFor($tenant));

        // Token mort → RECONNEXION requise : une notification actionnable.
        $reauth = new MailboxSyncFailed($tenant, 'mbx-1', 'reauth_required', new \DateTimeImmutable('2026-07-28 08:05:00'));
        $reauth->assignId('evt-sync-2');
        $projector->onMailboxSyncFailed($reauth);
        self::assertSame(1, $this->countFor($tenant));
        self::assertSame('mailbox_disconnected', $this->connection->fetchOne('SELECT type FROM notification WHERE tenant_id = ?', [$tenant]));
    }

    public function testNotifiesFollowUpsDueTodayOncePerDeadline(): void
    {
        $tenant = Uuid::v7()->toRfc4122();
        $deleting = Uuid::v7()->toRfc4122();
        $today = new \DateTimeImmutable('today 09:00');
        $tomorrow = $today->modify('+1 day');

        foreach ([[$tenant, 'lead-due', $today], [$tenant, 'lead-later', $tomorrow], [$deleting, 'lead-gone', $today]] as [$t, $leadId, $dueAt]) {
            $this->seedLeadWithFollowUp($t, $leadId, $dueAt);
        }
        // Le tenant `deleting` a demandé sa suppression → plus aucune notification.
        $this->connection->executeStatement(
            "INSERT INTO app_user (id, tenant_id, email, password, roles, deletion_requested_at) VALUES (?, ?, 'gone@plume.test', 'x', '[]', NOW())",
            [Uuid::v7()->toRfc4122(), $deleting],
        );

        $handler = new NotifyDueFollowUpsHandler($this->connection);
        $handler(new NotifyDueFollowUpsTick());
        $handler(new NotifyDueFollowUpsTick()); // tick horaire → re-run sans doublon

        self::assertSame(1, $this->countFor($tenant));
        self::assertSame(0, $this->countFor($deleting));

        /** @var array{type: string, payload: string} $row */
        $row = $this->connection->fetchAssociative('SELECT type, payload FROM notification WHERE tenant_id = ?', [$tenant]);
        self::assertSame('followup_due', $row['type']);
        self::assertStringContainsString('lead-due', $row['payload']);
        self::assertStringContainsString('Maison lead-due', $row['payload']); // orgName joint
    }

    private function seedLeadWithFollowUp(string $tenantId, string $leadId, \DateTimeImmutable $dueAt): void
    {
        // Profil au fuseau UTC : « aujourd'hui » du tick == « today » PHP quel que soit le moment
        // du run (sinon la fenêtre 22 h-minuit UTC ferait diverger Europe/Paris — test flaky).
        $this->connection->executeStatement(
            "INSERT INTO profile (tenant_id, weekly_goal, timezone) VALUES (?, 5, 'UTC') ON CONFLICT (tenant_id) DO NOTHING",
            [$tenantId],
        );
        $orgId = 'org-'.$leadId;
        // Nom unique par piste (index unique tenant_id + lower(name) sur organization).
        $this->connection->executeStatement(
            "INSERT INTO organization (id, tenant_id, name, type, working_languages, segments, do_not_contact, contacts)
             VALUES (?, ?, ?, 'publisher', '[]', '[]', false, '[]')",
            [$orgId, $tenantId, 'Maison '.$leadId],
        );
        $this->connection->executeStatement(
            "INSERT INTO lead (id, tenant_id, organization_id, segment, status, language_pair, source, priority, created_at, follow_ups, next_follow_up_at, next_follow_up_label)
             VALUES (?, ?, ?, 'EDITION', 'FOLLOWED_UP', 'en>fr', 'DIRECT', 'MEDIUM', NOW(), '[]', ?, 'Relance 1')",
            [$leadId, $tenantId, $orgId, $dueAt->format('Y-m-d H:i:s')],
        );
    }
}
