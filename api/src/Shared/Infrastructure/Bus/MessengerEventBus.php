<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Bus;

use App\Shared\Application\Event\EventBus;
use App\Shared\Application\IdGenerator;
use App\Shared\Domain\DomainEvent;
use Symfony\Component\Messenger\MessageBusInterface;

final class MessengerEventBus implements EventBus
{
    public function __construct(
        private readonly MessageBusInterface $eventBus,
        private readonly IdGenerator $ids,
    ) {
    }

    public function publish(DomainEvent ...$events): void
    {
        foreach ($events as $event) {
            // L'outbox assigne l'identifiant (UUID v7) juste avant la sérialisation transactionnelle :
            // stable à travers les retries Messenger → idempotence des projections préservée.
            $event->assignId($this->ids->generate());
            $this->eventBus->dispatch($event);
        }
    }
}
