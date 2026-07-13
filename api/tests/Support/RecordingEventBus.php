<?php

declare(strict_types=1);

namespace App\Tests\Support;

use App\Shared\Application\Event\EventBus;
use App\Shared\Domain\DomainEvent;

final class RecordingEventBus implements EventBus
{
    /** @var DomainEvent[] */
    public array $events = [];

    public function publish(DomainEvent ...$events): void
    {
        foreach ($events as $event) {
            $this->events[] = $event;
        }
    }

    /** @param class-string<DomainEvent> $class */
    public function countOf(string $class): int
    {
        return \count(array_filter($this->events, static fn (DomainEvent $event): bool => $event instanceof $class));
    }
}
