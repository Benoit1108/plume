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
 * GET /mailbox — ressource singleton : répond TOUJOURS 200, avec `status: NONE`
 * tant qu'aucune boîte n'est connectée. (Un 404 ici ferait cracher un
 * console.error navigateur à chaque visite des Réglages — garde E2E.).
 *
 * @implements ProviderInterface<MailboxResource>
 */
final class MailboxProvider implements ProviderInterface
{
    public function __construct(private readonly QueryBus $queryBus)
    {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): MailboxResource
    {
        /** @var MailboxView|null $view */
        $view = $this->queryBus->ask(new GetMailbox());

        return null === $view ? new MailboxResource() : self::toResource($view);
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
