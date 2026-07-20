<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Mailbox\Domain\Mailbox\Event\AlertEmailReceived;
use App\Shared\Application\Event\EventBus;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Uid\Uuid;

/**
 * Bout-en-bout M3.2 : un `AlertEmailReceived` (Mailbox) est parsé et ingéré dans la file
 * de tri (Sourcing) — sans appel direct inter-contextes. En env test les events sont
 * consommés en ligne, donc la politique s'exécute immédiatement.
 */
final class AlertEmailIngestionTest extends KernelTestCase
{
    private Connection $connection;
    private EventBus $eventBus;
    private string $tenant;

    protected function setUp(): void
    {
        $container = static::getContainer();
        $connection = $container->get(Connection::class);
        $eventBus = $container->get(EventBus::class);
        \assert($connection instanceof Connection);
        \assert($eventBus instanceof EventBus);
        $this->connection = $connection;
        $this->eventBus = $eventBus;
        $this->connection->executeStatement('TRUNCATE TABLE candidate_lead, raw_alert RESTART IDENTITY CASCADE');
        $this->tenant = Uuid::v7()->toRfc4122();
    }

    private function publish(string $externalId): void
    {
        $this->eventBus->publish(new AlertEmailReceived(
            $this->tenant,
            'jobs-noreply@linkedin.com',
            'Traducteur EN>FR — sous-titrage',
            "Une offre : https://example.test/job/42\nLinkedIn",
            $externalId,
            new \DateTimeImmutable('2026-07-20 10:00:00'),
        ));
    }

    /** @return array<string, mixed>|false */
    private function candidate(): array|false
    {
        return $this->connection->fetchAssociative(
            'SELECT source, title, raw_ref FROM candidate_lead WHERE tenant_id = :t',
            ['t' => $this->tenant],
        );
    }

    private function rowCount(string $table): int
    {
        $value = $this->connection->fetchOne("SELECT COUNT(*) FROM {$table} WHERE tenant_id = :t", ['t' => $this->tenant]);

        return is_numeric($value) ? (int) $value : 0;
    }

    public function testAlertEmailBecomesACandidateWithRawKept(): void
    {
        $this->publish('msg-1');

        $candidate = $this->candidate();
        self::assertNotFalse($candidate);
        self::assertSame('LINKEDIN', $candidate['source']);
        self::assertSame('Traducteur EN>FR — sous-titrage', $candidate['title']);
        self::assertNotNull($candidate['raw_ref']); // email brut conservé (RawAlert)

        self::assertSame(1, $this->rowCount('raw_alert'));
    }

    public function testRepeatedAlertEmailIsDeduplicated(): void
    {
        $this->publish('msg-1');
        $this->publish('msg-1'); // même id de message => dédoublonné

        self::assertSame(1, $this->rowCount('candidate_lead'));
    }
}
