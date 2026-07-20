<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Sourcing\Infrastructure\Scheduler\PurgeRawAlertsHandler;
use App\Sourcing\Infrastructure\Scheduler\PurgeRawAlertsTick;
use App\Tests\Support\FixedClock;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Uid\Uuid;

/** Purge du brut (D6) : le contenu des annonces rejetées de longue date est supprimé. */
final class PurgeRawAlertsTest extends KernelTestCase
{
    private Connection $connection;
    private string $tenant;

    protected function setUp(): void
    {
        $connection = static::getContainer()->get(Connection::class);
        \assert($connection instanceof Connection);
        $this->connection = $connection;
        $this->connection->executeStatement('TRUNCATE TABLE candidate_lead, raw_alert RESTART IDENTITY CASCADE');
        $this->tenant = Uuid::v7()->toRfc4122();
    }

    private function seed(string $suffix, string $status, string $ingestedAt): void
    {
        $rawId = 'raw-'.$suffix;
        $this->connection->insert('raw_alert', [
            'id' => $rawId,
            'tenant_id' => $this->tenant,
            'source' => 'RSS',
            'payload' => '<item>'.$suffix.'</item>',
            'fetched_at' => $ingestedAt,
        ]);
        $this->connection->insert('candidate_lead', [
            'id' => 'cand-'.$suffix,
            'tenant_id' => $this->tenant,
            'source' => 'RSS',
            'dedup_hash' => 'hash-'.$suffix,
            'status' => $status,
            'title' => 'Annonce '.$suffix,
            'raw_ref' => $rawId,
            'ingested_at' => $ingestedAt,
        ]);
    }

    private function rawExists(string $suffix): bool
    {
        return false !== $this->connection->fetchOne('SELECT 1 FROM raw_alert WHERE id = :id', ['id' => 'raw-'.$suffix]);
    }

    public function testPurgesRawOfOldRejectedCandidatesOnly(): void
    {
        $this->seed('old-rejected', 'REJECTED', '2026-05-01 10:00:00');   // < cutoff → purgé
        $this->seed('recent-rejected', 'REJECTED', '2026-07-15 10:00:00'); // > cutoff → conservé
        $this->seed('old-pending', 'PENDING', '2026-05-01 10:00:00');      // pas rejeté → conservé

        // Clock fixe → cutoff = 2026-06-20.
        $handler = new PurgeRawAlertsHandler($this->connection, new FixedClock(new \DateTimeImmutable('2026-07-20 12:00:00')));
        ($handler)(new PurgeRawAlertsTick());

        self::assertFalse($this->rawExists('old-rejected'), 'le brut rejeté ancien est purgé');
        self::assertTrue($this->rawExists('recent-rejected'), 'le brut rejeté récent est conservé');
        self::assertTrue($this->rawExists('old-pending'), 'le brut d\'une annonce non rejetée est conservé');

        // La candidate rejetée demeure (dedupHash), mais sa référence au brut est détachée (NULL).
        self::assertNotFalse(
            $this->connection->fetchOne('SELECT 1 FROM candidate_lead WHERE id = :id', ['id' => 'cand-old-rejected']),
            'la candidate rejetée reste en base',
        );
        self::assertNull(
            $this->connection->fetchOne('SELECT raw_ref FROM candidate_lead WHERE id = :id', ['id' => 'cand-old-rejected']),
        );
    }
}
