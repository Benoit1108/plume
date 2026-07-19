<?php

declare(strict_types=1);

namespace App\Sourcing\Infrastructure\Gateway;

use App\Directory\Application\Command\CreateOrganization\CreateOrganization;
use App\Shared\Application\Command\CommandBus;
use App\Sourcing\Application\Gateway\DirectoryGateway;
use Doctrine\DBAL\Connection;

/**
 * Frontière Sourcing → Répertoire : création via la commande CreateOrganization,
 * vérification d'existence par lecture SQL directe, tenant EXPLICITE et fail-closed
 * (le SQLFilter ne s'applique pas au DBAL).
 */
final class CommandBusDirectoryGateway implements DirectoryGateway
{
    public function __construct(
        private readonly CommandBus $commandBus,
        private readonly Connection $connection,
    ) {
    }

    public function createOrganization(string $organizationId, string $tenantId, string $name, string $type, ?string $website, array $segments): void
    {
        $this->commandBus->dispatch(new CreateOrganization(
            $organizationId,
            $tenantId,
            $name,
            $type,
            $website,
            null,
            [],
            $segments,
            null,
        ));
    }

    public function organizationExists(string $organizationId, string $tenantId): bool
    {
        $found = $this->connection->fetchOne(
            'SELECT 1 FROM organization WHERE id = :id AND tenant_id = :tenant LIMIT 1',
            ['id' => $organizationId, 'tenant' => $tenantId],
        );

        return false !== $found;
    }
}
