<?php

declare(strict_types=1);

namespace App\Notification\Infrastructure\ApiResource\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Notification\Application\Command\MarkAllNotificationsRead\MarkAllNotificationsRead;
use App\Shared\Application\Command\CommandBus;
use App\Shared\Infrastructure\Doctrine\Tenancy\TenantContext;

/**
 * POST /notifications/read-all — tout marquer lu (tenant courant), idempotent.
 *
 * @implements ProcessorInterface<null, null>
 */
final class MarkAllNotificationsReadProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly CommandBus $commandBus,
        private readonly TenantContext $tenantContext,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): null
    {
        $this->commandBus->dispatch(new MarkAllNotificationsRead($this->tenantContext->require()->toString()));

        return null;
    }
}
