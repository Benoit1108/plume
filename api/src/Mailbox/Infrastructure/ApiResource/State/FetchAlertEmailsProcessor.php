<?php

declare(strict_types=1);

namespace App\Mailbox\Infrastructure\ApiResource\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Mailbox\Application\Command\FetchAlertEmails\FetchAlertEmails;
use App\Mailbox\Application\Query\GetMailbox\GetMailbox;
use App\Mailbox\Application\ReadModel\MailboxView;
use App\Mailbox\Infrastructure\ApiResource\MailboxResource;
use App\Shared\Application\Command\CommandBus;
use App\Shared\Application\Query\QueryBus;
use App\Shared\Infrastructure\Doctrine\Tenancy\TenantContext;

/**
 * POST /mailbox/fetch-alerts : relève immédiate des alertes du label dédié (geste manuel des
 * Réglages, symétrique de fetch-replies). Publie un `AlertEmailReceived` par email ; le Sourcing
 * ingère de façon asynchrone (les candidats apparaissent dans « À trier » après passage du worker).
 *
 * @implements ProcessorInterface<MailboxResource, MailboxResource>
 */
final class FetchAlertEmailsProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly CommandBus $commandBus,
        private readonly QueryBus $queryBus,
        private readonly TenantContext $tenantContext,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): MailboxResource
    {
        $this->commandBus->dispatch(new FetchAlertEmails($this->tenantContext->require()->toString()));

        /** @var MailboxView|null $view */
        $view = $this->queryBus->ask(new GetMailbox());

        return null === $view ? new MailboxResource() : MailboxProvider::toResource($view);
    }
}
