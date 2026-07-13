<?php

declare(strict_types=1);

namespace App\Prospecting\Infrastructure\ApiResource\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Prospecting\Application\Command\ScheduleFollowUp\ScheduleFollowUp;
use App\Prospecting\Application\Query\GetLead\GetLead;
use App\Prospecting\Application\ReadModel\LeadView;
use App\Prospecting\Infrastructure\ApiResource\LeadFollowUpResource;
use App\Prospecting\Infrastructure\ApiResource\LeadResource;
use App\Shared\Application\Command\CommandBus;
use App\Shared\Application\Query\QueryBus;

/**
 * POST /leads/{leadId}/schedule-follow-up → ScheduleFollowUp, retourne la piste à jour.
 *
 * @implements ProcessorInterface<LeadFollowUpResource, LeadResource>
 */
final class ScheduleFollowUpProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly CommandBus $commandBus,
        private readonly QueryBus $queryBus,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): LeadResource
    {
        $leadId = $uriVariables['leadId'] ?? null;
        if (!\is_string($leadId)) {
            throw new \LogicException('Missing lead id.');
        }

        $this->commandBus->dispatch(new ScheduleFollowUp($leadId, $data->dueAt, $data->label));

        /** @var LeadView $view */
        $view = $this->queryBus->ask(new GetLead($leadId));

        return LeadProvider::toResource($view);
    }
}
