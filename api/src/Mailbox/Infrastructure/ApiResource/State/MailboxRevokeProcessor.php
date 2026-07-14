<?php

declare(strict_types=1);

namespace App\Mailbox\Infrastructure\ApiResource\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Mailbox\Application\Command\RevokeMailbox\RevokeMailbox;
use App\Mailbox\Infrastructure\ApiResource\MailboxResource;
use App\Shared\Application\Command\CommandBus;
use App\Shared\Infrastructure\Doctrine\Tenancy\TenantContext;

/**
 * DELETE /mailbox : révoque le consentement (provider + app) et efface les tokens.
 *
 * @implements ProcessorInterface<MailboxResource, null>
 */
final class MailboxRevokeProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly CommandBus $commandBus,
        private readonly TenantContext $tenantContext,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): null
    {
        $this->commandBus->dispatch(new RevokeMailbox($this->tenantContext->require()->toString()));

        return null;
    }
}
