<?php

declare(strict_types=1);

namespace App\Prospecting\Infrastructure\ApiResource\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Prospecting\Application\Command\AddLeadNote\AddLeadNote;
use App\Prospecting\Infrastructure\ApiResource\LeadNoteResource;
use App\Shared\Application\Command\CommandBus;

/**
 * Ajout d'une note au journal (POST /leads/{leadId}/notes) → AddLeadNote.
 *
 * @implements ProcessorInterface<LeadNoteResource, LeadNoteResource>
 */
final class LeadNoteProcessor implements ProcessorInterface
{
    public function __construct(private readonly CommandBus $commandBus)
    {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): LeadNoteResource
    {
        $leadId = $uriVariables['leadId'] ?? null;
        if (!\is_string($leadId)) {
            throw new \LogicException('Missing lead id.');
        }

        $this->commandBus->dispatch(new AddLeadNote($leadId, $data->text));

        return $data;
    }
}
