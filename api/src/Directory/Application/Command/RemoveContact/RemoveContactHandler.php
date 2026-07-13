<?php

declare(strict_types=1);

namespace App\Directory\Application\Command\RemoveContact;

use App\Directory\Domain\Organization\ContactId;
use App\Directory\Domain\Organization\OrganizationId;
use App\Directory\Domain\Organization\OrganizationRepository;
use App\Shared\Application\Clock;
use App\Shared\Application\Command\CommandHandler;
use App\Shared\Application\Event\EventBus;

final class RemoveContactHandler implements CommandHandler
{
    public function __construct(
        private readonly OrganizationRepository $organizations,
        private readonly EventBus $eventBus,
        private readonly Clock $clock,
    ) {
    }

    public function __invoke(RemoveContact $command): void
    {
        $organization = $this->organizations->get(OrganizationId::fromString($command->organizationId));
        $organization->removeContact(ContactId::fromString($command->contactId), $this->clock->now());
        $this->organizations->save($organization);
        $this->eventBus->publish(...$organization->pullDomainEvents());
    }
}
