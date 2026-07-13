<?php

declare(strict_types=1);

namespace App\Prospecting\Infrastructure\ApiResource\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Prospecting\Application\Query\GetLeadTimeline\GetLeadTimeline;
use App\Prospecting\Application\ReadModel\InteractionView;
use App\Prospecting\Infrastructure\ApiResource\InteractionResource;
use App\Shared\Application\Query\QueryBus;

/**
 * Timeline d'une piste (GET /leads/{leadId}/interactions) — scoping tenant via le read model.
 *
 * @implements ProviderInterface<InteractionResource>
 */
final class InteractionProvider implements ProviderInterface
{
    public function __construct(private readonly QueryBus $queryBus)
    {
    }

    /** @return InteractionResource[] */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): array
    {
        $leadId = $uriVariables['leadId'] ?? null;
        if (!\is_string($leadId)) {
            return [];
        }

        /** @var InteractionView[] $interactions */
        $interactions = $this->queryBus->ask(new GetLeadTimeline($leadId));

        return array_map(static function (InteractionView $view): InteractionResource {
            $resource = new InteractionResource();
            $resource->id = $view->id;
            $resource->type = $view->type;
            $resource->payload = $view->payload;
            $resource->occurredOn = $view->occurredOn->format(\DateTimeInterface::ATOM);

            return $resource;
        }, $interactions);
    }
}
