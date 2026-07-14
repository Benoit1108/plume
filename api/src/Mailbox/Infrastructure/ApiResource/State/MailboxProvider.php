<?php

declare(strict_types=1);

namespace App\Mailbox\Infrastructure\ApiResource\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Mailbox\Application\Query\GetMailbox\GetMailbox;
use App\Mailbox\Application\ReadModel\MailboxView;
use App\Mailbox\Infrastructure\ApiResource\MailboxResource;
use App\Shared\Application\Query\QueryBus;

/**
 * GET /mailbox — 404 tant qu'aucune boîte n'est connectée (le front l'interprète
 * comme « proposer la connexion »).
 *
 * @implements ProviderInterface<MailboxResource>
 */
final class MailboxProvider implements ProviderInterface
{
    public function __construct(private readonly QueryBus $queryBus)
    {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): ?MailboxResource
    {
        /** @var MailboxView|null $view */
        $view = $this->queryBus->ask(new GetMailbox());

        return null === $view ? null : self::toResource($view);
    }

    public static function toResource(MailboxView $view): MailboxResource
    {
        $resource = new MailboxResource();
        $resource->provider = $view->provider;
        $resource->emailAddress = $view->emailAddress;
        $resource->status = $view->status;
        $resource->failureReason = $view->failureReason;
        $resource->connectedAt = $view->connectedAt->format(\DateTimeInterface::ATOM);
        $resource->lastSyncAt = $view->lastSyncAt?->format(\DateTimeInterface::ATOM);

        return $resource;
    }
}
