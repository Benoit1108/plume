<?php

declare(strict_types=1);

namespace App\Directory\Infrastructure\ApiResource\State;

use ApiPlatform\Metadata\CollectionOperationInterface;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\Pagination\TraversablePaginator;
use ApiPlatform\State\ProviderInterface;
use App\Directory\Application\Query\GetOrganization\GetOrganization;
use App\Directory\Application\Query\ListOrganizations\ListOrganizations;
use App\Directory\Application\ReadModel\ContactView;
use App\Directory\Application\ReadModel\OrganizationPage;
use App\Directory\Application\ReadModel\OrganizationView;
use App\Directory\Infrastructure\ApiResource\ContactResource;
use App\Directory\Infrastructure\ApiResource\OrganizationResource;
use App\Shared\Application\Query\QueryBus;
use App\Shared\Domain\Exception\NotFound;

/**
 * Lecture (collection paginée + item) : délègue au QueryBus, mappe les vues vers les DTO.
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
            /** @var array<string, mixed> $filters */
            $filters = \is_array($context['filters'] ?? null) ? $context['filters'] : [];

            /** @var OrganizationPage $page */
            $page = $this->queryBus->ask(new ListOrganizations(
                type: $this->stringFilter($filters, 'type'),
                search: $this->stringFilter($filters, 'q'),
                page: $this->intFilter($filters, 'page', 1),
                itemsPerPage: $this->intFilter($filters, 'itemsPerPage', 30),
            ));

            return new TraversablePaginator(
                new \ArrayIterator(array_map(static fn (OrganizationView $view): OrganizationResource => self::toResource($view), $page->items)),
                $page->page,
                $page->itemsPerPage,
                $page->total,
            );
        }

        $id = $uriVariables['id'] ?? null;
        if (!\is_string($id)) {
            return null;
        }

        try {
            /** @var OrganizationView $organization */
            $organization = $this->queryBus->ask(new GetOrganization($id));
        } catch (NotFound) {
            return null;
        }

        return self::toResource($organization);
    }

    public static function toResource(OrganizationView $view): OrganizationResource
    {
        $resource = new OrganizationResource();
        $resource->id = $view->id;
        $resource->name = $view->name;
        $resource->type = $view->type;
        $resource->website = $view->website;
        $resource->country = $view->country;
        $resource->workingLanguages = $view->workingLanguages;
        $resource->segments = $view->segments;
        $resource->notes = $view->notes;
        $resource->doNotContact = $view->doNotContact;
        $resource->contacts = array_map(static fn (ContactView $contact): ContactResource => self::toContactResource($contact), $view->contacts);

        return $resource;
    }

    public static function toContactResource(ContactView $view): ContactResource
    {
        $resource = new ContactResource();
        $resource->id = $view->id;
        $resource->fullName = $view->fullName;
        $resource->role = $view->role;
        $resource->email = $view->email;
        $resource->phone = $view->phone;
        $resource->linkedinUrl = $view->linkedinUrl;
        $resource->preferredLanguage = $view->preferredLanguage;
        $resource->doNotContact = $view->doNotContact;

        return $resource;
    }

    /** @param array<string, mixed> $filters */
    private function stringFilter(array $filters, string $key): ?string
    {
        $value = $filters[$key] ?? null;

        return \is_string($value) && '' !== $value ? $value : null;
    }

    /** @param array<string, mixed> $filters */
    private function intFilter(array $filters, string $key, int $default): int
    {
        $value = $filters[$key] ?? null;
        if (\is_string($value) && ctype_digit($value)) {
            return (int) $value;
        }

        return $default;
    }
}
