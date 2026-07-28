<?php

declare(strict_types=1);

namespace App\Notification\Application\ReadModel;

/** Port de lecture du centre de notifications (SQL direct fail-closed tenant, ADR-0013). */
interface NotificationFeed
{
    /** @return list<NotificationView> les plus récentes d'abord */
    public function latest(int $limit): array;
}
