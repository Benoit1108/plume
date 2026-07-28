<?php

declare(strict_types=1);

namespace App\Notification\Application\Query\GetNotifications;

use App\Shared\Application\Query\Query;

/** Les dernières notifications du tenant courant. */
final class GetNotifications implements Query
{
    public function __construct(public readonly int $limit = 50)
    {
    }
}
