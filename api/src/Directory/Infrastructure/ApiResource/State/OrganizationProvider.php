<?php

declare(strict_types=1);

namespace App\Directory\Infrastructure\ApiResource\State;

use ApiPlatform\Metadata\CollectionOperationInterface;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Directory\Application\Query\GetOrganization\GetOrganization;
use App\Directory\Application\Query\ListOrganizations\ListOrganizations;
use App\Directory\Domain\Organization\Contact;
use App\Directory\Domain\Organization\Organization;
use App\Directory\Infrastructure\ApiResource\ContactResource;
use App\Directory\Infrastructure\ApiResource\OrganizationResource;
use App\Shared\Application\Query\QueryBus;
use App\Shared\Domain\Exception\NotFound;
use App\Shared\Domain\ValueObject\LanguageCode;
use App\Shared\Domain\ValueObject\Segment;

/**
 * Lecture (collection + item) : délègue au QueryBus, mappe le domaine vers les DTO.
 *
 * @implements ProviderInterface<OrganizationResource>
 */
final class OrganizationProvider implements ProviderInterface
{
    public function __construct(private readonly QueryBus $queryBus)
    {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        if ($operation instanceof CollectionOperationInterface) {
            $filters = \is_array($context['filters'] ?? null) ? $context['filters'] : [];
            $type = isset($filters['type']) && \is_string($filters['type']) ? $filters['type'] : null;
            $search = isset($filters['q']) && \is_string($filters['q']) ? $filters['q'] : null;

            /** @var Organization[] $organizations */
            $organizations = $this->queryBus->ask(new ListOrganizations($type, $search));

            return array_map(static fn (Organization $o): OrganizationResource => self::toResource($o), $organizations);
        }

        $id = $uriVariables['id'] ?? null;
        if (!\is_string($id)) {
            return null;
        }

        try {
            /** @var Organization $organization */
            $organization = $this->queryBus->ask(new GetOrganization($id));
        } catch (NotFound) {
            return null;
        }

        return self::toResource($organization);
    }

    public static function toResource(Organization $organization): OrganizationResource
    {
        $resource = new OrganizationResource();
        $resource->id = $organization->id()->toString();
        $resource->name = $organization->name();
        $resource->type = $organization->type()->value;
        $resource->website = $organization->website();
        $resource->country = $organization->country()?->toString();
        $resource->workingLanguages = array_map(static fn (LanguageCode $l): string => $l->toString(), $organization->workingLanguages());
        $resource->segments = array_map(static fn (Segment $s): string => $s->value, $organization->segments());
        $resource->notes = $organization->notes();
        $resource->doNotContact = $organization->doNotContact();
        $resource->contacts = array_map(static fn (Contact $c): ContactResource => self::toContactResource($c), $organization->contacts());

        return $resource;
    }

    public static function toContactResource(Contact $contact): ContactResource
    {
        $resource = new ContactResource();
        $resource->id = $contact->id()->toString();
        $resource->fullName = $contact->fullName();
        $resource->role = $contact->role();
        $resource->email = $contact->email()?->toString();
        $resource->phone = $contact->phone();
        $resource->linkedinUrl = $contact->linkedinUrl();
        $resource->preferredLanguage = $contact->preferredLanguage()?->toString();
        $resource->doNotContact = $contact->doNotContact();

        return $resource;
    }
}
