<?php

declare(strict_types=1);

namespace App\Sourcing\Infrastructure\ApiResource\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Shared\Application\Command\CommandBus;
use App\Shared\Infrastructure\Doctrine\Tenancy\TenantContext;
use App\Sourcing\Application\Command\AddAlertFeed\AddAlertFeed;
use App\Sourcing\Application\Command\RemoveAlertFeed\RemoveAlertFeed;
use App\Sourcing\Application\Command\SetAlertFeedActive\SetAlertFeedActive;
use App\Sourcing\Infrastructure\ApiResource\Input\AlertFeedInput;

/**
 * POST /sources (ajout) · POST /sources/{id}/{activate|deactivate} · DELETE /sources/{id}
 * → commande correspondante.
 *
 * @implements ProcessorInterface<AlertFeedInput|null, null>
 */
final class AlertFeedProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly CommandBus $commandBus,
        private readonly TenantContext $tenantContext,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): null
    {
        $id = \is_string($uriVariables['id'] ?? null) ? $uriVariables['id'] : '';

        switch ($operation->getName()) {
            case 'source_add':
                \assert($data instanceof AlertFeedInput);
                $this->commandBus->dispatch(new AddAlertFeed(
                    $this->tenantContext->require()->toString(),
                    $data->source,
                    $data->url,
                    $data->label,
                ));
                break;
            case 'source_activate':
                $this->commandBus->dispatch(new SetAlertFeedActive($id, true));
                break;
            case 'source_deactivate':
                $this->commandBus->dispatch(new SetAlertFeedActive($id, false));
                break;
            case 'source_remove':
                $this->commandBus->dispatch(new RemoveAlertFeed($id));
                break;
        }

        return null;
    }
}
