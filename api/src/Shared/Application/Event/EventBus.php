<?php

declare(strict_types=1);

namespace App\Shared\Application\Event;

use App\Shared\Domain\DomainEvent;

interface EventBus
{
    public function publish(DomainEvent ...$events): void;
}
