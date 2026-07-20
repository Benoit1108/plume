<?php

declare(strict_types=1);

namespace App\Sourcing\Infrastructure\ApiResource\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Shared\Application\Command\CommandBus;
use App\Shared\Infrastructure\Doctrine\Tenancy\TenantContext;
use App\Sourcing\Application\Command\PollAlertSource\PollAlertSource;

/**
 * POST /sources/poll : relève immédiate pour le tenant courant (geste manuel de « À trier »).
 * Comme la relève email M2, l'I/O se fait dans la requête (commande sync) et rend 202.
 *
 * @implements ProcessorInterface<null, null>
 */
final class PollSourcesProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly CommandBus $commandBus,
        private readonly TenantContext $tenantContext,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): null
    {
        $this->commandBus->dispatch(new PollAlertSource($this->tenantContext->require()->toString()));

        return null;
    }
}
