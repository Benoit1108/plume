<?php

declare(strict_types=1);

namespace App\Directory\Infrastructure\ApiResource\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Directory\Application\Query\GetOrganization\GetOrganization;
use App\Directory\Application\ReadModel\OrganizationView;
use App\Directory\Infrastructure\ApiResource\ContactResource;
use App\Shared\Application\Query\QueryBus;
use App\Shared\Domain\Exception\NotFound;

/**
 * Charge un contact (pour PATCH/DELETE) au sein de son organisation (scoping tenant via le query).
 *
 * @implements ProviderInterface<ContactResource>
 */
final class ContactProvider implements ProviderInterface
{
    public function __construct(private readonly QueryBus $queryBus)
    {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): ?ContactResource
    {
        $organizationId = $uriVariables['organizationId'] ?? null;
        $contactId = $uriVariables['id'] ?? null;
        if (!\is_string($organizationId) || !\is_string($contactId)) {
            return null;
        }

        try {
            /** @var OrganizationView $organization */
            $organization = $this->queryBus->ask(new GetOrganization($organizationId));
        } catch (NotFound) {
            return null;
        }

        foreach ($organization->contacts as $contact) {
            if ($contact->id === $contactId) {
                return OrganizationProvider::toContactResource($contact);
            }
        }

        return null;
    }
}
