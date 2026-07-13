<?php

declare(strict_types=1);

namespace App\Directory\Application\Command\UpdateContact;

use App\Directory\Domain\Organization\ContactId;
use App\Directory\Domain\Organization\OrganizationId;
use App\Directory\Domain\Organization\OrganizationRepository;
use App\Shared\Application\Command\CommandHandler;
use App\Shared\Domain\ValueObject\EmailAddress;
use App\Shared\Domain\ValueObject\LanguageCode;

final class UpdateContactHandler implements CommandHandler
{
    public function __construct(private readonly OrganizationRepository $organizations)
    {
    }

    public function __invoke(UpdateContact $command): void
    {
        $organization = $this->organizations->get(OrganizationId::fromString($command->organizationId));

        $organization->updateContact(
            ContactId::fromString($command->contactId),
            $command->fullName,
            $command->role,
            null !== $command->email ? EmailAddress::fromString($command->email) : null,
            $command->phone,
            $command->linkedinUrl,
            null !== $command->preferredLanguage ? LanguageCode::fromString($command->preferredLanguage) : null,
        );

        $this->organizations->save($organization);
    }
}
