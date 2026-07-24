<?php

declare(strict_types=1);

namespace App\Sourcing\Infrastructure\ApiResource\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Shared\Infrastructure\Doctrine\Tenancy\TenantContext;
use App\Sourcing\Application\Command\PollAlertSource\PollAlertSource;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\TransportNamesStamp;

/**
 * POST /sources/poll : relève ASYNCHRONE pour le tenant courant (geste manuel de « À trier »).
 * L'I/O réseau (RSS, potentiellement lent) se fait sur le WORKER, pas dans la requête HTTP
 * (dette ADR-0022 §1 soldée) → réponse 202 immédiate ; le front rafraîchit la file au fil de
 * l'ingestion. Même chemin que le fan-out du Scheduler (PollAlertSource sur la file `io`), tenant
 * porté par le message (middleware worker).
 *
 * @implements ProcessorInterface<null, null>
 */
final class PollSourcesProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly MessageBusInterface $commandBus,
        private readonly TenantContext $tenantContext,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): null
    {
        $this->commandBus->dispatch(
            new PollAlertSource($this->tenantContext->require()->toString()),
            [new TransportNamesStamp(['io'])],
        );

        return null;
    }
}
