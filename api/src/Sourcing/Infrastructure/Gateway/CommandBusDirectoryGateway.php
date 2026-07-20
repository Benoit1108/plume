<?php

declare(strict_types=1);

namespace App\Sourcing\Infrastructure\Gateway;

use App\Directory\Application\Command\CreateOrganization\CreateOrganization;
use App\Directory\Application\Query\OrganizationExists\OrganizationExists;
use App\Shared\Application\Command\CommandBus;
use App\Shared\Application\Query\QueryBus;
use App\Sourcing\Application\Gateway\DirectoryGateway;

/**
 * Frontière Sourcing → Répertoire : création via la commande CreateOrganization,
 * vérification d'existence via la QUERY OrganizationExists (le Répertoire possède son SQL) —
 * jamais de lecture directe des tables d'un autre contexte.
 */
final class CommandBusDirectoryGateway implements DirectoryGateway
{
    public function __construct(
        private readonly CommandBus $commandBus,
        private readonly QueryBus $queryBus,
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
        return true === $this->queryBus->ask(new OrganizationExists($organizationId, $tenantId));
    }
}
