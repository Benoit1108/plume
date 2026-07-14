<?php

declare(strict_types=1);

namespace App\Mailbox\Infrastructure\ApiResource\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Mailbox\Application\Command\ConnectMailbox\ConnectMailbox;
use App\Mailbox\Application\Query\GetMailbox\GetMailbox;
use App\Mailbox\Application\ReadModel\MailboxView;
use App\Mailbox\Infrastructure\ApiResource\MailboxResource;
use App\Mailbox\Infrastructure\OAuth\OAuthStateCodec;
use App\Shared\Application\Command\CommandBus;
use App\Shared\Application\IdGenerator;
use App\Shared\Application\Query\QueryBus;
use App\Shared\Domain\Exception\InvalidValue;
use App\Shared\Infrastructure\Doctrine\Tenancy\TenantContext;

/**
 * POST /mailbox/connect {code, state} : vérifie le state (anti-CSRF, lié au
 * tenant du JWT) puis échange le code côté serveur. Gmail seul en V1 (D1).
 *
 * @implements ProcessorInterface<MailboxResource, MailboxResource>
 */
final class MailboxConnectProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly CommandBus $commandBus,
        private readonly QueryBus $queryBus,
        private readonly OAuthStateCodec $stateCodec,
        private readonly TenantContext $tenantContext,
        private readonly IdGenerator $ids,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): MailboxResource
    {
        $tenant = $this->tenantContext->require();

        if (!$this->stateCodec->isValidFor($data->state, $tenant->toString())) {
            throw InvalidValue::because('Invalid or expired OAuth state.');
        }

        $this->commandBus->dispatch(new ConnectMailbox(
            $this->ids->generate(),
            $tenant->toString(),
            'GMAIL',
            $data->code,
        ));

        /** @var MailboxView $view */
        $view = $this->queryBus->ask(new GetMailbox());

        return MailboxProvider::toResource($view);
    }
}
