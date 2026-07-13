<?php

declare(strict_types=1);

namespace App\Prospecting\Application\Query\GetLeadTimeline;

use App\Prospecting\Application\ReadModel\InteractionView;
use App\Prospecting\Application\ReadModel\LeadTimeline;
use App\Shared\Application\Query\QueryHandler;

final class GetLeadTimelineHandler implements QueryHandler
{
    public function __construct(private readonly LeadTimeline $timeline)
    {
    }

    /** @return InteractionView[] */
    public function __invoke(GetLeadTimeline $query): array
    {
        return $this->timeline->forLead($query->leadId);
    }
}
