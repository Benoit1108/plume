<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Prospecting\Infrastructure\Gateway\ProfileFollowUpCadenceProvider;
use App\Shared\Domain\ValueObject\TenantId;
use App\Shared\Infrastructure\Doctrine\Tenancy\TenantContext;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Uid\Uuid;

/** Lecture de la séquence de relance depuis le profil : valeur configurée, défaut si absent/abîmé. */
final class ProfileFollowUpCadenceProviderTest extends KernelTestCase
{
    private Connection $connection;
    private TenantContext $tenantContext;

    protected function setUp(): void
    {
        $c = static::getContainer();
        $connection = $c->get(Connection::class);
        $tenantContext = $c->get(TenantContext::class);
        \assert($connection instanceof Connection);
        \assert($tenantContext instanceof TenantContext);
        $this->connection = $connection;
        $this->tenantContext = $tenantContext;
        $this->connection->executeStatement('TRUNCATE TABLE profile RESTART IDENTITY CASCADE');
    }

    public function testReadsConfiguredCadence(): void
    {
        $tenant = Uuid::v7()->toRfc4122();
        $this->connection->executeStatement(
            "INSERT INTO profile (tenant_id, weekly_goal, timezone, digest_frequency, follow_up_cadence)
             VALUES (?, 5, 'Europe/Paris', 'DAILY', ?)",
            [$tenant, '[3, 14]'],
        );
        $this->tenantContext->set(TenantId::fromString($tenant));

        $cadence = (new ProfileFollowUpCadenceProvider($this->connection, $this->tenantContext))->forCurrentTenant();
        self::assertSame([3, 14], $cadence->days());
    }

    public function testFallsBackToDefaultWhenNoProfile(): void
    {
        $this->tenantContext->set(TenantId::fromString(Uuid::v7()->toRfc4122()));

        $cadence = (new ProfileFollowUpCadenceProvider($this->connection, $this->tenantContext))->forCurrentTenant();
        self::assertSame([7, 21, 45], $cadence->days());
    }
}
