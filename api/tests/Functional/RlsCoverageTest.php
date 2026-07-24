<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Garde-fou RLS (revue pré-V2) : TOUTE table métier portant `tenant_id` DOIT avoir la RLS activée
 * ET une policy d'isolation. Sans ce test, une future migration qui crée une table tenantée en
 * oubliant sa policy passerait la CI (le SQLFilter applicatif masque l'absence de filet base en
 * dev) et partirait en prod sans protection RLS — le risque n°1 documenté par ADR-0023.
 */
final class RlsCoverageTest extends KernelTestCase
{
    /** Exclusions ASSUMÉES (ADR-0023 §4) : lu avant le tenant, au login → jamais de RLS. */
    private const array EXCLUDED = ['app_user'];

    public function testEveryTenantScopedTableHasRlsAndPolicy(): void
    {
        self::bootKernel();
        $connection = self::getContainer()->get(Connection::class);
        \assert($connection instanceof Connection);

        /** @var list<string> $tables */
        $tables = $connection->fetchFirstColumn(
            <<<'SQL'
                SELECT c.relname
                FROM pg_class c
                JOIN pg_namespace n ON n.oid = c.relnamespace
                WHERE n.nspname = 'public'
                  AND c.relkind = 'r'
                  AND EXISTS (
                      SELECT 1 FROM information_schema.columns col
                      WHERE col.table_schema = 'public' AND col.table_name = c.relname AND col.column_name = 'tenant_id'
                  )
                SQL,
        );

        $scoped = array_values(array_filter($tables, static fn (string $t): bool => !\in_array($t, self::EXCLUDED, true)));
        self::assertNotEmpty($scoped, 'au moins une table tenantée attendue');

        foreach ($scoped as $table) {
            $rlsEnabled = $connection->fetchOne(
                'SELECT relrowsecurity FROM pg_class WHERE relname = ? AND relnamespace = ?::regnamespace',
                [$table, 'public'],
            );
            self::assertTrue((bool) $rlsEnabled, \sprintf('RLS non activée sur "%s" (ENABLE ROW LEVEL SECURITY manquant ?)', $table));

            $policyCount = $connection->fetchOne(
                'SELECT COUNT(*) FROM pg_policies WHERE schemaname = ? AND tablename = ?',
                ['public', $table],
            );
            self::assertGreaterThan(0, is_numeric($policyCount) ? (int) $policyCount : 0, \sprintf('Aucune policy RLS sur "%s"', $table));
        }
    }
}
