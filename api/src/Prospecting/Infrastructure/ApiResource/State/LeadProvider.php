<?php

declare(strict_types=1);

namespace App\Prospecting\Infrastructure\ApiResource\State;

use ApiPlatform\Metadata\CollectionOperationInterface;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\Pagination\TraversablePaginator;
use ApiPlatform\State\ProviderInterface;
use App\Prospecting\Application\Query\GetLead\GetLead;
use App\Prospecting\Application\Query\ListLeads\ListLeads;
use App\Prospecting\Application\ReadModel\LeadPage;
use App\Prospecting\Application\ReadModel\LeadView;
use App\Prospecting\Infrastructure\ApiResource\LeadResource;
use App\Shared\Application\Query\QueryBus;
use App\Shared\Domain\Exception\NotFound;

/**
 * Lecture (collection paginée + item) : délègue au QueryBus, mappe les vues vers les DTO.
 *
 * @implements ProviderInterface<LeadResource>
 */
final class LeadProvider implements ProviderInterface
{
    public function __construct(private readonly QueryBus $queryBus)
    {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        if ($operation instanceof CollectionOperationInterface) {
            /** @var array<string, mixed> $filters */
            $filters = \is_array($context['filters'] ?? null) ? $context['filters'] : [];

            /** @var LeadPage $page */
            $page = $this->queryBus->ask(new ListLeads(
                status: $this->stringFilter($filters, 'status'),
                priority: $this->stringFilter($filters, 'priority'),
                segment: $this->stringFilter($filters, 'segment'),
                page: $this->intFilter($filters, 'page', 1),
                itemsPerPage: $this->intFilter($filters, 'itemsPerPage', 100),
            ));

            return new TraversablePaginator(
                new \ArrayIterator(array_map(static fn (LeadView $view): LeadResource => self::toResource($view), $page->items)),
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
            /** @var LeadView $lead */
            $lead = $this->queryBus->ask(new GetLead($id));
        } catch (NotFound) {
            return null;
        }

        return self::toResource($lead);
    }

    public static function toResource(LeadView $view): LeadResource
    {
        $resource = new LeadResource();
        $resource->id = $view->id;
        $resource->organizationId = $view->organizationId;
        $resource->organizationName = $view->organizationName;
        $resource->contactId = $view->contactId;
        $resource->languagePair = $view->languagePair;
        $resource->source = $view->source;
        $resource->priority = $view->priority;
        $resource->segment = $view->segment;
        $resource->status = $view->status;
        $resource->allowedActions = $view->allowedActions;
        $resource->createdAt = $view->createdAt->format(\DateTimeInterface::ATOM);
        $resource->lastContactedAt = $view->lastContactedAt?->format(\DateTimeInterface::ATOM);
        $resource->lastReplyAt = $view->lastReplyAt?->format(\DateTimeInterface::ATOM);
        $resource->nextFollowUpAt = $view->nextFollowUpAt?->format('Y-m-d');
        $resource->nextFollowUpLabel = $view->nextFollowUpLabel;

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
