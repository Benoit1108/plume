<?php

declare(strict_types=1);

namespace App\Sourcing\Infrastructure\Gateway;

use App\Prospecting\Application\Command\AddLeadNote\AddLeadNote;
use App\Prospecting\Application\Command\CreateLead\CreateLead;
use App\Prospecting\Application\Query\FindActiveLead\FindActiveLead;
use App\Shared\Application\Command\CommandBus;
use App\Shared\Application\Query\QueryBus;
use App\Sourcing\Application\Gateway\ProspectingGateway;

/** Frontière Sourcing → Prospection : délègue aux commandes et à la query FindActiveLead. */
final class CommandBusProspectingGateway implements ProspectingGateway
{
    public function __construct(
        private readonly CommandBus $commandBus,
        private readonly QueryBus $queryBus,
    ) {
    }

    public function createLead(string $leadId, string $tenantId, string $organizationId, string $languagePair, string $source, string $priority, string $segment): void
    {
        $this->commandBus->dispatch(new CreateLead(
            $leadId,
            $tenantId,
            $organizationId,
            null,
            $languagePair,
            $source,
            $priority,
            $segment,
        ));
    }

    public function activeLeadId(string $tenantId, string $organizationId): ?string
    {
        $id = $this->queryBus->ask(new FindActiveLead($tenantId, $organizationId));

        return \is_string($id) ? $id : null;
    }

    public function annotateLead(string $leadId, string $text): void
    {
        $this->commandBus->dispatch(new AddLeadNote($leadId, $text));
    }
}
