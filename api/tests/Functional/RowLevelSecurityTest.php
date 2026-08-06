<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Exception as DbalException;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Filet de sécurité RLS en base (chantier 1b-4) : prouve, via une VRAIE connexion sous le rôle
 * runtime non-propriétaire `plume_app`, que les policies isolent les tenants et échouent fermé
 * hors session tenantée. Le rôle propriétaire (connexion ORM des tests) contourne la RLS et sert
 * ici à poser/nettoyer les données de contrôle.
 *
 * Couvre deux tables aux profils DIFFÉRENTS (revue « 10/10 sécurité ») : `alert_feed` (agrégat mappé
 * ORM) et `notification` (projection écrite en DBAL pur, hors métadonnées ORM). La garantie
 * STRUCTURELLE sur les douze tables tenantées est portée par RlsCoverageTest.
 */
final class RowLevelSecurityTest extends KernelTestCase
{
    private const string TENANT_A = '11111111-1111-1111-1111-111111111111';
    private const string TENANT_B = '22222222-2222-2222-2222-222222222222';

    /** Identifiants des lignes de contrôle, par table (alert_feed.id est une chaîne, notification.id un uuid). */
    private const array ROW_IDS = [
        'alert_feed' => ['rls-a', 'rls-b', 'rls-x'],
        'notification' => [
            '33333333-3333-3333-3333-333333333331',
            '33333333-3333-3333-3333-333333333332',
            '33333333-3333-3333-3333-333333333333',
        ],
    ];

    private Connection $owner;
    private Connection $app;

    /** @return iterable<string, array{string}> */
    public static function tenantScopedTables(): iterable
    {
        yield 'agrégat mappé ORM' => ['alert_feed'];
        yield 'projection DBAL pure' => ['notification'];
    }

    protected function setUp(): void
    {
        self::bootKernel();
        $owner = self::getContainer()->get(Connection::class);
        \assert($owner instanceof Connection);
        $this->owner = $owner;

        // Connexion dédiée sous plume_app (non-propriétaire → SOUMIS à la RLS), même base de test.
        $params = $owner->getParams();
        $appUser = \is_string($_ENV['APP_DB_USER'] ?? null) ? $_ENV['APP_DB_USER'] : 'plume_app';
        $appPassword = \is_string($_ENV['APP_DB_PASSWORD'] ?? null) ? $_ENV['APP_DB_PASSWORD'] : 'plume_app';
        $this->app = DriverManager::getConnection([
            'driver' => $params['driver'] ?? 'pdo_pgsql',
            'host' => $params['host'] ?? 'database',
            'port' => $params['port'] ?? 5432,
            'dbname' => (string) $owner->getDatabase(),
            'user' => $appUser,
            'password' => $appPassword,
        ]);

        foreach (self::ROW_IDS as $table => $ids) {
            $this->cleanUp($table, $ids);
            $this->seed($table, $ids[0], self::TENANT_A);
            $this->seed($table, $ids[1], self::TENANT_B);
        }
    }

    protected function tearDown(): void
    {
        foreach (self::ROW_IDS as $table => $ids) {
            $this->cleanUp($table, $ids);
        }
        $this->app->close();
    }

    #[DataProvider('tenantScopedTables')]
    public function testTenantSeesOnlyItsOwnRows(string $table): void
    {
        [$idA, $idB] = self::ROW_IDS[$table];

        $this->setSessionTenant(self::TENANT_A);
        self::assertSame([$idA], $this->visibleIds($table), \sprintf('%s : le tenant A ne voit que ses lignes', $table));

        $this->setSessionTenant(self::TENANT_B);
        self::assertSame([$idB], $this->visibleIds($table), \sprintf('%s : le tenant B ne voit que ses lignes', $table));
    }

    #[DataProvider('tenantScopedTables')]
    public function testUnscopedSessionSeesNothing(string $table): void
    {
        $this->setSessionTenant(''); // équivaut au clear() de TenantScope
        self::assertSame([], $this->visibleIds($table), \sprintf('%s : hors tenant, fail-closed', $table));
    }

    #[DataProvider('tenantScopedTables')]
    public function testWriteCheckRejectsForeignTenant(string $table): void
    {
        $this->setSessionTenant(self::TENANT_A);

        $this->expectException(DbalException::class);
        // WITH CHECK : insérer une ligne d'un AUTRE tenant est refusé même si on est tenanté A.
        $this->insert($this->app, $table, self::ROW_IDS[$table][2], self::TENANT_B);
    }

    #[DataProvider('tenantScopedTables')]
    public function testForeignTenantRowsCannotBeDeleted(string $table): void
    {
        // USING s'applique aussi aux DELETE : un tenant ne peut pas effacer les lignes d'un autre
        // (une policy limitée à FOR SELECT laisserait passer cette suppression).
        $this->setSessionTenant(self::TENANT_A);
        $deleted = $this->app->executeStatement(\sprintf('DELETE FROM %s WHERE id = ?', $table), [self::ROW_IDS[$table][1]]);

        self::assertSame(0, $deleted, \sprintf('%s : aucune ligne du tenant B ne doit être supprimable par A', $table));
        self::assertTrue($this->existsAsOwner($table, self::ROW_IDS[$table][1]), \sprintf('%s : la ligne du tenant B est toujours là', $table));
    }

    /** @param list<string> $ids */
    private function cleanUp(string $table, array $ids): void
    {
        $this->owner->executeStatement(
            \sprintf('DELETE FROM %s WHERE id IN (?, ?, ?)', $table),
            [$ids[0], $ids[1], $ids[2]],
        );
    }

    private function seed(string $table, string $id, string $tenantId): void
    {
        $this->insert($this->owner, $table, $id, $tenantId);
    }

    private function insert(Connection $connection, string $table, string $id, string $tenantId): void
    {
        match ($table) {
            'alert_feed' => $connection->executeStatement(
                "INSERT INTO alert_feed (id, tenant_id, source, url, label, active, created_at) VALUES (?, ?, 'RSS', 'https://example.test/feed', 'flux', true, now())",
                [$id, $tenantId],
            ),
            'notification' => $connection->executeStatement(
                "INSERT INTO notification (id, event_id, tenant_id, type, payload, occurred_on) VALUES (?, ?, ?, 'reply_received', '{}'::jsonb, now())",
                [$id, 'rls-'.$id, $tenantId],
            ),
            default => throw new \LogicException(\sprintf('Table non prise en charge : %s', $table)),
        };
    }

    private function setSessionTenant(string $tenantId): void
    {
        $this->app->executeStatement("SELECT set_config('app.current_tenant', ?, false)", [$tenantId]);
    }

    /** @return list<string> */
    private function visibleIds(string $table): array
    {
        $ids = self::ROW_IDS[$table];
        /** @var list<string> $visible */
        $visible = $this->app->fetchFirstColumn(
            \sprintf('SELECT id::text FROM %s WHERE id IN (?, ?) ORDER BY id', $table),
            [$ids[0], $ids[1]],
        );

        return $visible;
    }

    private function existsAsOwner(string $table, string $id): bool
    {
        return (bool) $this->owner->fetchOne(\sprintf('SELECT COUNT(*) FROM %s WHERE id = ?', $table), [$id]);
    }
}
