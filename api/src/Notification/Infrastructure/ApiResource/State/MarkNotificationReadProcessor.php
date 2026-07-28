<?php

declare(strict_types=1);

namespace App\Notification\Infrastructure\ApiResource\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Notification\Application\Command\MarkNotificationRead\MarkNotificationRead;
use App\Shared\Application\Command\CommandBus;
use App\Shared\Infrastructure\Doctrine\Tenancy\TenantContext;

/**
 * POST /notifications/{id}/read — marquage lu, idempotent (204 même déjà lue / inconnue :
 * rien à divulguer, le prédicat tenant borne l'écriture).
 *
 * @implements ProcessorInterface<null, null>
 */
final class MarkNotificationReadProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly CommandBus $commandBus,
        private readonly TenantContext $tenantContext,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): null
    {
        $id = $uriVariables['id'] ?? null;
        if (\is_string($id) && '' !== $id) {
            $this->commandBus->dispatch(new MarkNotificationRead($this->tenantContext->require()->toString(), $id));
        }

        return null;
    }
}
