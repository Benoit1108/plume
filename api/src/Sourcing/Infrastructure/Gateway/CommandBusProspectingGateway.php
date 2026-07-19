<?php

declare(strict_types=1);

namespace App\Sourcing\Infrastructure\Gateway;

use App\Prospecting\Application\Command\CreateLead\CreateLead;
use App\Shared\Application\Command\CommandBus;
use App\Sourcing\Application\Gateway\ProspectingGateway;

/** Frontière Sourcing → Prospection : délègue à la commande CreateLead. */
final class CommandBusProspectingGateway implements ProspectingGateway
{
    public function __construct(private readonly CommandBus $commandBus)
    {
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
}
