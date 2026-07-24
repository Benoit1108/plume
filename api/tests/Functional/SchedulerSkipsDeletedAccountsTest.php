<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Mailbox\Application\Command\FetchAlertEmails\FetchAlertEmails;
use App\Mailbox\Application\Command\FetchReplies\FetchReplies;
use App\Mailbox\Infrastructure\Scheduler\FetchAllAlertEmailsHandler;
use App\Mailbox\Infrastructure\Scheduler\FetchAllAlertEmailsTick;
use App\Mailbox\Infrastructure\Scheduler\FetchAllRepliesHandler;
use App\Mailbox\Infrastructure\Scheduler\FetchAllRepliesTick;
use App\Sourcing\Application\Command\PollAlertSource\PollAlertSource;
use App\Sourcing\Infrastructure\Scheduler\PollAllSourcesHandler;
use App\Sourcing\Infrastructure\Scheduler\PollAllSourcesTick;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Uid\Uuid;

/**
 * RGPD — garantie « on cesse toute relève de fond pour un compte en cours de suppression » : les
 * trois ticks du scheduler (réponses, emails d'alerte, sources) énumèrent uniquement les tenants
 * dont l'app_user n'est PAS marqué `deletion_requested_at`. Sinon on continuerait à lire la boîte
 * d'un compte qui a demandé son effacement.
 */
final class SchedulerSkipsDeletedAccountsTest extends KernelTestCase
{
    private const ACTIVE = '11111111-1111-1111-1111-111111111111';
    private const DELETING = '22222222-2222-2222-2222-222222222222';

    private function seed(Connection $c): void
    {
        $c->executeStatement('TRUNCATE TABLE app_user, connected_mailbox, alert_feed RESTART IDENTITY CASCADE');

        foreach ([[self::ACTIVE, 'active@plume.test', null], [self::DELETING, 'gone@plume.test', '2026-07-24 10:00:00']] as [$tenant, $email, $deletedAt]) {
            $c->executeStatement(
                'INSERT INTO app_user (id, tenant_id, email, password, roles, deletion_requested_at) VALUES (?, ?, ?, ?, ?, ?)',
                [Uuid::v7()->toRfc4122(), $tenant, $email, 'x', '[]', $deletedAt],
            );
            $c->executeStatement(
                "INSERT INTO connected_mailbox (id, tenant_id, provider, email_address, status, connected_at)
                 VALUES (?, ?, 'gmail', ?, 'CONNECTED', '2026-07-24 10:00:00')",
                [Uuid::v7()->toRfc4122(), $tenant, $email],
            );
            $c->executeStatement(
                "INSERT INTO alert_feed (id, tenant_id, source, url, label, active, created_at)
                 VALUES (?, ?, 'rss', 'https://example.test/feed', 'Flux', true, '2026-07-24 10:00:00')",
                [Uuid::v7()->toRfc4122(), $tenant],
            );
        }
    }

    /** @return list<object> */
    private function dispatchedBy(callable $run): array
    {
        $bus = new class implements MessageBusInterface {
            /** @var list<object> */
            public array $seen = [];

            public function dispatch(object $message, array $stamps = []): Envelope
            {
                $this->seen[] = $message;

                return new Envelope($message);
            }
        };
        $run($bus);

        return $bus->seen;
    }

    public function testTicksSkipTenantsScheduledForDeletion(): void
    {
        $connection = static::getContainer()->get(Connection::class);
        \assert($connection instanceof Connection);
        $this->seed($connection);

        $replies = $this->dispatchedBy(fn (MessageBusInterface $bus) => (new FetchAllRepliesHandler($connection, $bus))(new FetchAllRepliesTick()));
        self::assertCount(1, $replies);
        self::assertInstanceOf(FetchReplies::class, $replies[0]);
        self::assertSame(self::ACTIVE, $replies[0]->tenantId);

        $alerts = $this->dispatchedBy(fn (MessageBusInterface $bus) => (new FetchAllAlertEmailsHandler($connection, $bus))(new FetchAllAlertEmailsTick()));
        self::assertCount(1, $alerts);
        self::assertInstanceOf(FetchAlertEmails::class, $alerts[0]);
        self::assertSame(self::ACTIVE, $alerts[0]->tenantId);

        $sources = $this->dispatchedBy(fn (MessageBusInterface $bus) => (new PollAllSourcesHandler($connection, $bus))(new PollAllSourcesTick()));
        self::assertCount(1, $sources);
        self::assertInstanceOf(PollAlertSource::class, $sources[0]);
        self::assertSame(self::ACTIVE, $sources[0]->tenantId);
    }
}
