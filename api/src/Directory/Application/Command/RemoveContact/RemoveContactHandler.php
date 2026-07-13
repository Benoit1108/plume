<?php

declare(strict_types=1);

namespace App\Directory\Application\Command\RemoveContact;

use App\Directory\Domain\Organization\ContactId;
use App\Directory\Domain\Organization\OrganizationId;
use App\Directory\Domain\Organization\OrganizationRepository;
use App\Shared\Application\Command\CommandHandler;

final class RemoveContactHandler implements CommandHandler
{
    public function __construct(private readonly OrganizationRepository $organizations)
    {
    }

    public function __invoke(RemoveContact $command): void
    {
        $organization = $this->organizations->get(OrganizationId::fromString($command->organizationId));
        $organization->removeContact(ContactId::fromString($command->contactId));
        $this->organizations->save($organization);
    }
}
