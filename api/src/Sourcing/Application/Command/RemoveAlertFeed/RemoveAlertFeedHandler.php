<?php

declare(strict_types=1);

namespace App\Sourcing\Application\Command\RemoveAlertFeed;

use App\Shared\Application\Clock;
use App\Shared\Application\Command\CommandHandler;
use App\Shared\Application\Event\EventBus;
use App\Sourcing\Domain\AlertFeed\AlertFeedId;
use App\Sourcing\Domain\AlertFeed\AlertFeedRepository;
use App\Sourcing\Domain\AlertFeed\Exception\AlertFeedNotFound;

final class RemoveAlertFeedHandler implements CommandHandler
{
    public function __construct(
        private readonly AlertFeedRepository $feeds,
        private readonly Clock $clock,
        private readonly EventBus $eventBus,
    ) {
    }

    public function __invoke(RemoveAlertFeed $command): void
    {
        $feed = $this->feeds->find(AlertFeedId::fromString($command->alertFeedId))
            ?? throw AlertFeedNotFound::withId($command->alertFeedId);

        $feed->markRemoved($this->clock->now());
        $this->feeds->remove($feed);
        $this->eventBus->publish(...$feed->pullDomainEvents());
    }
}
