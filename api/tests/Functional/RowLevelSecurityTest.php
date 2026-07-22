<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Exception as DbalException;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Filet de sécurité RLS en base (chantier 1b-4) : prouve, via une VRAIE connexion sous le rôle
 * runtime non-propriétaire `plume_app`, que les policies isolent les tenants et échouent fermé
 * hors session tenantée. Le rôle propriétaire (connexion ORM des tests) contourne la RLS et sert
 * ici à poser/nettoyer les données de contrôle.
 */
final class RowLevelSecurityTest extends KernelTestCase
{
    private const string TENANT_A = '11111111-1111-1111-1111-111111111111';
    private const string TENANT_B = '22222222-2222-2222-2222-222222222222';

    private Connection $owner;
    private Connection $app;

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

        $this->owner->executeStatement('DELETE FROM alert_feed WHERE id IN (?, ?)', ['rls-a', 'rls-b']);
        $this->seedFeed('rls-a', self::TENANT_A);
        $this->seedFeed('rls-b', self::TENANT_B);
    }

    protected function tearDown(): void
    {
        $this->owner->executeStatement('DELETE FROM alert_feed WHERE id IN (?, ?)', ['rls-a', 'rls-b']);
        $this->app->close();
    }

    public function testTenantSeesOnlyItsOwnRows(): void
    {
        $this->setSessionTenant(self::TENANT_A);
        self::assertSame(['rls-a'], $this->visibleFeedIds(), 'tenant A ne voit que ses lignes');

        $this->setSessionTenant(self::TENANT_B);
        self::assertSame(['rls-b'], $this->visibleFeedIds(), 'tenant B ne voit que ses lignes');
    }

    public function testUnscopedSessionSeesNothing(): void
    {
        $this->setSessionTenant(''); // équivaut au clear() de TenantScope
        self::assertSame([], $this->visibleFeedIds(), 'hors tenant : fail-closed, aucune ligne');
    }

    public function testWriteCheckRejectsForeignTenant(): void
    {
        $this->setSessionTenant(self::TENANT_A);

        $this->expectException(DbalException::class);
        // WITH CHECK : insérer une ligne d'un AUTRE tenant est refusé même si on est tenanté A.
        $this->app->executeStatement(
            "INSERT INTO alert_feed (id, tenant_id, source, url, label, active, created_at) VALUES ('rls-x', ?, 'RSS', 'https://e.test/f', 'x', true, now())",
            [self::TENANT_B],
        );
    }

    private function seedFeed(string $id, string $tenantId): void
    {
        $this->owner->executeStatement(
            "INSERT INTO alert_feed (id, tenant_id, source, url, label, active, created_at) VALUES (?, ?, 'RSS', 'https://example.test/feed', 'flux', true, now())",
            [$id, $tenantId],
        );
    }

    private function setSessionTenant(string $tenantId): void
    {
        $this->app->executeStatement("SELECT set_config('app.current_tenant', ?, false)", [$tenantId]);
    }

    /** @return list<string> */
    private function visibleFeedIds(): array
    {
        /** @var list<string> $ids */
        $ids = $this->app->fetchFirstColumn("SELECT id FROM alert_feed WHERE id IN ('rls-a', 'rls-b') ORDER BY id");

        return $ids;
    }
}
