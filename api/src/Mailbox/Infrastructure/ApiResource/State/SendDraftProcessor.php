<?php

declare(strict_types=1);

namespace App\Mailbox\Infrastructure\ApiResource\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Mailbox\Application\Command\SendDraft\SendDraft;
use App\Mailbox\Infrastructure\ApiResource\SendDraftResource;
use App\Shared\Application\Command\CommandBus;
use App\Shared\Application\IdGenerator;
use App\Shared\Infrastructure\Doctrine\Tenancy\TenantContext;

/**
 * POST /drafts/{id}/send → SendDraft (gardes synchrones : boîte, READY,
 * destinataire, RGPD) puis envoi par le worker.
 *
 * @implements ProcessorInterface<SendDraftResource, SendDraftResource>
 */
final class SendDraftProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly CommandBus $commandBus,
        private readonly TenantContext $tenantContext,
        private readonly IdGenerator $ids,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): SendDraftResource
    {
        $draftId = $uriVariables['id'] ?? null;
        if (!\is_string($draftId)) {
            throw new \LogicException('Missing draft id.');
        }

        $messageId = $this->ids->generate();
        $this->commandBus->dispatch(new SendDraft($messageId, $this->tenantContext->require()->toString(), $draftId));

        $resource = new SendDraftResource();
        $resource->id = $messageId;

        return $resource;
    }
}
