<?php

declare(strict_types=1);

namespace App\Sourcing\Infrastructure\ApiResource;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\OpenApi\Model\Operation;
use App\Sourcing\Infrastructure\ApiResource\Input\CandidateAcceptInput;
use App\Sourcing\Infrastructure\ApiResource\Input\CandidateMergeInput;
use App\Sourcing\Infrastructure\ApiResource\State\CandidateLeadProvider;
use App\Sourcing\Infrastructure\ApiResource\State\CandidateTriageProcessor;
use Symfony\Component\Serializer\Attribute\Groups;

/**
 * File de tri (DTO) — API orientée cas d'usage : les décisions de tri sont des
 * POST dédiés (accepter / fusionner / rejeter), chacune mappe une commande.
 */
#[ApiResource(
    shortName: 'CandidateLead',
    normalizationContext: ['groups' => ['candidate:read']],
    operations: [
        new GetCollection(uriTemplate: '/candidate-leads', provider: CandidateLeadProvider::class),
        new Post(
            uriTemplate: '/candidate-leads/{id}/accept',
            name: 'candidate_accept',
            input: CandidateAcceptInput::class,
            read: false,
            output: false,
            status: 204,
            processor: CandidateTriageProcessor::class,
            openapi: new Operation(summary: 'Accepter : crée une nouvelle organisation + piste'),
        ),
        new Post(
            uriTemplate: '/candidate-leads/{id}/merge',
            name: 'candidate_merge',
            input: CandidateMergeInput::class,
            read: false,
            output: false,
            status: 204,
            processor: CandidateTriageProcessor::class,
            openapi: new Operation(summary: 'Fusionner : rattache à une organisation existante + piste'),
        ),
        new Post(
            uriTemplate: '/candidate-leads/{id}/reject',
            name: 'candidate_reject',
            input: false,
            read: false,
            output: false,
            status: 204,
            processor: CandidateTriageProcessor::class,
            openapi: new Operation(summary: 'Rejeter : écarte la candidate'),
        ),
    ],
)]
final class CandidateLeadResource
{
    #[ApiProperty(identifier: true)]
    #[Groups(['candidate:read'])]
    public ?string $id = null;

    #[Groups(['candidate:read'])]
    public string $source = 'MANUAL';

    #[Groups(['candidate:read'])]
    public string $status = 'PENDING';

    #[Groups(['candidate:read'])]
    public string $title = '';

    #[Groups(['candidate:read'])]
    public ?string $organizationName = null;

    #[Groups(['candidate:read'])]
    public ?string $languagePair = null;

    #[Groups(['candidate:read'])]
    public ?string $url = null;

    #[Groups(['candidate:read'])]
    public ?string $excerpt = null;

    #[Groups(['candidate:read'])]
    public ?string $postedAt = null;

    #[Groups(['candidate:read'])]
    public ?string $ingestedAt = null;
}
