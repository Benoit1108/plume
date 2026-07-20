<?php

declare(strict_types=1);

namespace App\Sourcing\Application\Command\AddAlertFeed;

use App\Shared\Application\Clock;
use App\Shared\Application\Command\CommandHandler;
use App\Shared\Application\Event\EventBus;
use App\Shared\Application\IdGenerator;
use App\Shared\Domain\Exception\InvalidValue;
use App\Shared\Domain\ValueObject\TenantId;
use App\Sourcing\Domain\AlertFeed\AlertFeed;
use App\Sourcing\Domain\AlertFeed\AlertFeedId;
use App\Sourcing\Domain\AlertFeed\AlertFeedRepository;
use App\Sourcing\Domain\CandidateLead\Source;

final class AddAlertFeedHandler implements CommandHandler
{
    public function __construct(
        private readonly AlertFeedRepository $feeds,
        private readonly IdGenerator $ids,
        private readonly Clock $clock,
        private readonly EventBus $eventBus,
    ) {
    }

    public function __invoke(AddAlertFeed $command): void
    {
        $source = Source::tryFrom($command->source)
            ?? throw InvalidValue::because(sprintf('Unknown source "%s".', $command->source));

        $feed = AlertFeed::add(
            AlertFeedId::fromString($this->ids->generate()),
            TenantId::fromString($command->tenantId),
            $source,
            $command->url,
            $command->label,
            $this->clock->now(),
        );

        $this->feeds->save($feed);
        $this->eventBus->publish(...$feed->pullDomainEvents());
    }
}
