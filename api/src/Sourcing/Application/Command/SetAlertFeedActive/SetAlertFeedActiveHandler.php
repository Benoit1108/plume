<?php

declare(strict_types=1);

namespace App\Sourcing\Application\Command\SetAlertFeedActive;

use App\Shared\Application\Clock;
use App\Shared\Application\Command\CommandHandler;
use App\Shared\Application\Event\EventBus;
use App\Sourcing\Domain\AlertFeed\AlertFeedId;
use App\Sourcing\Domain\AlertFeed\AlertFeedRepository;
use App\Sourcing\Domain\AlertFeed\Exception\AlertFeedNotFound;

final class SetAlertFeedActiveHandler implements CommandHandler
{
    public function __construct(
        private readonly AlertFeedRepository $feeds,
        private readonly Clock $clock,
        private readonly EventBus $eventBus,
    ) {
    }

    public function __invoke(SetAlertFeedActive $command): void
    {
        $feed = $this->feeds->find(AlertFeedId::fromString($command->alertFeedId))
            ?? throw AlertFeedNotFound::withId($command->alertFeedId);

        $feed->setActive($command->active, $this->clock->now());
        $this->feeds->save($feed);
        $this->eventBus->publish(...$feed->pullDomainEvents());
    }
}
