<?php

declare(strict_types=1);

namespace App\Sourcing\Infrastructure\ApiResource\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Shared\Application\Command\CommandBus;
use App\Sourcing\Application\Command\AcceptCandidate\AcceptCandidate;
use App\Sourcing\Application\Command\MergeCandidate\MergeCandidate;
use App\Sourcing\Application\Command\RejectCandidate\RejectCandidate;
use App\Sourcing\Infrastructure\ApiResource\Input\CandidateAcceptInput;
use App\Sourcing\Infrastructure\ApiResource\Input\CandidateMergeInput;

/**
 * POST /candidate-leads/{id}/{accept|merge|reject} → commande de tri correspondante.
 *
 * @implements ProcessorInterface<CandidateAcceptInput|CandidateMergeInput|null, null>
 */
final class CandidateTriageProcessor implements ProcessorInterface
{
    public function __construct(private readonly CommandBus $commandBus)
    {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): null
    {
        $id = \is_string($uriVariables['id'] ?? null) ? $uriVariables['id'] : '';

        switch ($operation->getName()) {
            case 'candidate_accept':
                \assert($data instanceof CandidateAcceptInput);
                $this->commandBus->dispatch(new AcceptCandidate(
                    $id,
                    $data->organizationName,
                    $data->organizationType,
                    $data->languagePair,
                    $data->segment,
                    $data->priority,
                    $data->website,
                ));
                break;
            case 'candidate_merge':
                \assert($data instanceof CandidateMergeInput);
                $this->commandBus->dispatch(new MergeCandidate(
                    $id,
                    $data->organizationId,
                    $data->languagePair,
                    $data->segment,
                    $data->priority,
                ));
                break;
            case 'candidate_reject':
                $this->commandBus->dispatch(new RejectCandidate($id));
                break;
            default:
                throw new \LogicException('Unknown candidate triage operation.');
        }

        return null;
    }
}
