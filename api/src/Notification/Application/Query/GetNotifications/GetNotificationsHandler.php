<?php

declare(strict_types=1);

namespace App\Notification\Application\Query\GetNotifications;

use App\Notification\Application\ReadModel\NotificationFeed;
use App\Notification\Application\ReadModel\NotificationView;
use App\Shared\Application\Query\QueryHandler;

final class GetNotificationsHandler implements QueryHandler
{
    public function __construct(private readonly NotificationFeed $feed)
    {
    }

    /** @return list<NotificationView> */
    public function __invoke(GetNotifications $query): array
    {
        return $this->feed->latest(max(1, min(100, $query->limit)));
    }
}
