<?php

declare(strict_types=1);

namespace App\Directory\Application\Command\AddContact;

use App\Directory\Domain\Organization\Contact;
use App\Directory\Domain\Organization\ContactId;
use App\Directory\Domain\Organization\OrganizationId;
use App\Directory\Domain\Organization\OrganizationRepository;
use App\Shared\Application\Clock;
use App\Shared\Application\Command\CommandHandler;
use App\Shared\Application\Event\EventBus;
use App\Shared\Domain\ValueObject\EmailAddress;
use App\Shared\Domain\ValueObject\LanguageCode;

final class AddContactHandler implements CommandHandler
{
    public function __construct(
        private readonly OrganizationRepository $organizations,
        private readonly EventBus $eventBus,
        private readonly Clock $clock,
    ) {
    }

    public function __invoke(AddContact $command): void
    {
        $organization = $this->organizations->get(OrganizationId::fromString($command->organizationId));

        $organization->addContact(
            new Contact(
                ContactId::fromString($command->contactId),
                $command->fullName,
                $command->role,
                null !== $command->email ? EmailAddress::fromString($command->email) : null,
                $command->phone,
                $command->linkedinUrl,
                null !== $command->preferredLanguage ? LanguageCode::fromString($command->preferredLanguage) : null,
            ),
            $this->clock->now(),
        );

        $this->organizations->save($organization);
        $this->eventBus->publish(...$organization->pullDomainEvents());
    }
}
