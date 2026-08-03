<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Drafting\Infrastructure\Budget\DoctrineAiBudget;
use App\Tests\Support\FixedClock;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Garde-fou de coût IA (compteur durable `ai_usage`) : accumulation mensuelle, plafond, coupe-circuit,
 * isolation par mois. Compteur GLOBAL hors tenant (pas de RLS à traverser).
 */
final class AiBudgetTest extends KernelTestCase
{
    private Connection $connection;

    protected function setUp(): void
    {
        $connection = static::getContainer()->get(Connection::class);
        \assert($connection instanceof Connection);
        $this->connection = $connection;
        $this->connection->executeStatement('TRUNCATE TABLE ai_usage');
    }

    private function budget(bool $enabled, int $monthlyBudget, string $now = '2026-08-15 10:00:00'): DoctrineAiBudget
    {
        return new DoctrineAiBudget($this->connection, new FixedClock(new \DateTimeImmutable($now)), $enabled, $monthlyBudget);
    }

    public function testAccumulatesUsageAndEnforcesTheMonthlyCap(): void
    {
        $budget = $this->budget(enabled: true, monthlyBudget: 1000);

        self::assertTrue($budget->allowsGeneration()); // compteur vide

        $budget->record(300, 200); // 500
        $budget->record(200, 100); // +300 = 800
        self::assertTrue($budget->allowsGeneration()); // 800 < 1000

        $budget->record(100, 150); // +250 = 1050
        self::assertFalse($budget->allowsGeneration()); // 1050 >= 1000 → repli gratuit

        $snapshot = $budget->snapshot();
        self::assertSame(1050, $snapshot['periodTokens']);
        self::assertSame(3, $snapshot['calls']);
        self::assertSame(1000, $snapshot['monthlyTokenBudget']);
        self::assertTrue($snapshot['enabled']);
    }

    public function testKillSwitchBlocksRegardlessOfBudget(): void
    {
        $budget = $this->budget(enabled: false, monthlyBudget: 0); // 0 = illimité, mais coupe-circuit baissé
        self::assertFalse($budget->allowsGeneration());
    }

    public function testZeroBudgetMeansUnlimited(): void
    {
        $budget = $this->budget(enabled: true, monthlyBudget: 0);
        $budget->record(9_000_000, 9_000_000);
        self::assertTrue($budget->allowsGeneration());
    }

    public function testUsageIsIsolatedPerMonth(): void
    {
        $this->budget(enabled: true, monthlyBudget: 1000, now: '2026-08-31 23:00:00')->record(900, 0);
        // Mois suivant : compteur repart de zéro (fenêtre mensuelle).
        $september = $this->budget(enabled: true, monthlyBudget: 1000, now: '2026-09-01 00:30:00');
        self::assertSame(0, $september->snapshot()['periodTokens']);
        self::assertTrue($september->allowsGeneration());
    }
}
