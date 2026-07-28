<?php

declare(strict_types=1);

namespace App\Notification\Infrastructure\ApiResource\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Notification\Application\Query\GetNotifications\GetNotifications;
use App\Notification\Application\ReadModel\NotificationView;
use App\Notification\Infrastructure\ApiResource\NotificationResource;
use App\Shared\Application\Query\QueryBus;

/**
 * GET /notifications — les plus récentes d'abord (tenant courant, fail-closed dans le read model).
 *
 * @implements ProviderInterface<NotificationResource>
 */
final class NotificationsProvider implements ProviderInterface
{
    public function __construct(private readonly QueryBus $queryBus)
    {
    }

    /** @return list<NotificationResource> */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): array
    {
        /** @var list<NotificationView> $views */
        $views = $this->queryBus->ask(new GetNotifications());

        return array_map(static function (NotificationView $view): NotificationResource {
            $resource = new NotificationResource();
            $resource->id = $view->id;
            $resource->type = $view->type;
            $resource->payload = $view->payload;
            $resource->readAt = $view->readAt;
            $resource->occurredOn = $view->occurredOn;

            return $resource;
        }, $views);
    }
}
