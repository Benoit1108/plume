<?php

declare(strict_types=1);

namespace App\Sourcing\Infrastructure\Gateway;

use App\Prospecting\Application\Command\AddLeadNote\AddLeadNote;
use App\Prospecting\Application\Command\CreateLead\CreateLead;
use App\Shared\Application\Command\CommandBus;
use App\Sourcing\Application\Gateway\ProspectingGateway;
use Doctrine\DBAL\Connection;

/** Frontière Sourcing → Prospection : délègue aux commandes, lit en DBAL fail-closed. */
final class CommandBusProspectingGateway implements ProspectingGateway
{
    public function __construct(
        private readonly CommandBus $commandBus,
        private readonly Connection $connection,
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
        // « Active » = non terminale (cf. index partiel uniq_lead_active_per_organization).
        $id = $this->connection->fetchOne(
            "SELECT id FROM lead WHERE tenant_id = :tenant AND organization_id = :org AND status NOT IN ('WON', 'LOST') LIMIT 1",
            ['tenant' => $tenantId, 'org' => $organizationId],
        );

        return \is_string($id) ? $id : null;
    }

    public function annotateLead(string $leadId, string $text): void
    {
        $this->commandBus->dispatch(new AddLeadNote($leadId, $text));
    }
}
